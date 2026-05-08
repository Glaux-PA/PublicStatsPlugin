<?php

/**
 * @file plugins/generic/publicStats/controllers/PublicStatisticsHandler.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatisticsHandler
 * @ingroup plugins_generic_publicStats
 *
 * @brief Main handler for the public statistics page.
 *
 * Hosts the `/total` HTML endpoint plus the JSON endpoints consumed by the
 * dashboard JavaScript. Heavy lifting is delegated to the services in
 * services/; the traits group endpoints by feature area.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers;

// Core OJS
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\plugins\PluginRegistry;

// Plugin
use APP\plugins\generic\publicStats\PublicStatsPlugin;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\classes\ColorHelper;

// Services
use APP\plugins\generic\publicStats\services\StatisticsService;
use APP\plugins\generic\publicStats\services\ArticleStatsService;
use APP\plugins\generic\publicStats\services\EditorialStatsService;
use APP\plugins\generic\publicStats\services\DecisionStatsService;
use APP\plugins\generic\publicStats\services\AuthorReviewerStatsService;
use APP\plugins\generic\publicStats\services\IssueStatsService;
use APP\plugins\generic\publicStats\services\SectionStatsService;
use APP\plugins\generic\publicStats\services\AuthorStatsService;
use APP\plugins\generic\publicStats\services\LanguageStatsService;
use APP\plugins\generic\publicStats\services\CsvExporter;
use APP\plugins\generic\publicStats\services\OpenAlexService;
use APP\plugins\generic\publicStats\services\EnrichedStatsService;

// Traits
use APP\plugins\generic\publicStats\controllers\traits\ArticleStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\EditorialStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\AuthorReviewerStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\EnrichedStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\CsvExportTrait;

// External
use Illuminate\Support\Facades\Cache;

/**
 * Main handler for public statistics
 */
class PublicStatisticsHandler extends Handler
{
    use ArticleStatsTrait;
    use EditorialStatsTrait;
    use AuthorReviewerStatsTrait;
    use EnrichedStatsTrait;
    use CsvExportTrait;
    
    // ========================================
    // Properties
    // ========================================
    
    private PublicStatsPlugin $plugin;
    private StatisticsService $statsService;
    private ArticleStatsService $articleService;
    private EditorialStatsService $editorialService;
    private DecisionStatsService $decisionService;
    private AuthorReviewerStatsService $authorReviewerService;
    private IssueStatsService $issueService;
    private SectionStatsService $sectionService;
    private AuthorStatsService $authorStatsService;
    private LanguageStatsService $languageStatsService;
    private OpenAlexService $openalexService;
    private EnrichedStatsService $enrichedService;
    private CsvExporter $csvExporter;

    // ========================================
    // Constructor
    // ========================================

    /**
     * Initialize handler with all service dependencies
     */
    public function __construct()
    {
        parent::__construct();

        $plugin = PluginRegistry::getPlugin('generic', 'publicstatsplugin');
        if (!$plugin instanceof PublicStatsPlugin) {
            throw new \RuntimeException('publicStats plugin is not registered or disabled.');
        }
        $this->plugin = $plugin;

        // Initialize all services
        $this->statsService = app(StatisticsService::class);
        $this->articleService = app(ArticleStatsService::class);
        $this->editorialService = app(EditorialStatsService::class);
        $this->decisionService = app(DecisionStatsService::class);
        $this->authorReviewerService = app(AuthorReviewerStatsService::class);
        $this->issueService = app(IssueStatsService::class);
        $this->sectionService = app(SectionStatsService::class);
        $this->authorStatsService = app(AuthorStatsService::class);
        $this->languageStatsService = app(LanguageStatsService::class);
        $this->openalexService = app(OpenAlexService::class);
        $this->enrichedService = app(EnrichedStatsService::class);
        $this->csvExporter = app(CsvExporter::class);
    }

    // ========================================
    // Core Endpoints
    // ========================================

    /**
     * Display main statistics page with year selector and navigation
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return string Rendered template
     */
    public function total(array $args, PKPRequest $request)
    {
        $context = $request->getContext();
        if (!$context) {
            return $request->getDispatcher()->handle404();
        }

        $selectedYear = InputValidator::validateYear($request->getUserVar('year'));
        $templateMgr = TemplateManager::getManager($request);

        $contextId = $context->getId();
        $colorVariants = $this->getColorSettings($contextId);
        $enabledSubsections = $this->getEnabledSubsections($contextId);
        $defaultSection = $this->getDefaultSection($enabledSubsections);

        $templateMgr->assign([
            'pageTitleTranslated' => __('plugins.generic.publicStats.statistics'),
            'availableYears' => $this->getAvailableYears(),
            'selectedYear' => $selectedYear,
            // Color variables
            'primaryColor' => $colorVariants['primary'],
            'primaryColorLight' => $colorVariants['light'],
            'primaryColorDark' => $colorVariants['dark'],
            'primaryColorDarker' => $colorVariants['darker'],
            'primaryColorRgb' => $colorVariants['rgb'],
            // Section visibility
            'enabledSubsections' => $enabledSubsections,
            'defaultSection'     => $defaultSection,
            // Language-section issue filter
            'availableIssues'    => $this->languageStatsService->getPublishedIssues($contextId),
        ]);

        $this->setupAssets($templateMgr, $request);

        return $templateMgr->display($this->plugin->getTemplateResource('publicStats.tpl'));
    }

    /**
     * Get color settings from plugin configuration
     *
     * @param int $contextId Context ID
     * @return array Color variants
     */
    private function getColorSettings(int $contextId): array
    {
        $primaryColor = $this->plugin->getSetting($contextId, 'primaryColor');
        
        if (empty($primaryColor) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $primaryColor)) {
            $primaryColor = '#8b2635'; // Default burgundy
        }
        
        return ColorHelper::calculateVariants($primaryColor);
    }

    /**
     * Get enabled subsection ids from plugin settings, defaulting to all.
     */
    private function getEnabledSubsections(int $contextId): array
    {
        $all = array_merge(...array_values(array_map('array_keys', PublicStatsConstants::SUBSECTIONS)));
        $saved = $this->plugin->getSetting($contextId, 'enabledSubsections');

        // Never saved → enable everything.
        if (!is_array($saved)) {
            return $all;
        }

        $stillValid = array_values(array_intersect($saved, $all));

        // Auto-include subsections added in code after the last save.
        // Without a snapshot we can't distinguish "newly added" from "user unchecked",
        // so fall back to the saved list verbatim.
        $known = $this->plugin->getSetting($contextId, 'knownSubsections');
        if (!is_array($known)) {
            return $stillValid;
        }

        $newlyAdded = array_values(array_diff($all, $known));
        return array_values(array_merge($stillValid, $newlyAdded));
    }

    /**
     * Return the first enabled subsection id (following sidebar order).
     */
    private function getDefaultSection(array $enabledSubsections): string
    {
        foreach (PublicStatsConstants::SUBSECTIONS as $groupSections) {
            foreach (array_keys($groupSections) as $sectionId) {
                if (in_array($sectionId, $enabledSubsections, true)) {
                    return $sectionId;
                }
            }
        }
        return 'monthly-trends';
    }

    /**
     * Get monthly statistics with optional year and section filters
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return void Outputs JSON
     */
    public function monthly(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $year = InputValidator::validateYear($request->getUserVar('year'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year);

            $cacheKey = sprintf(
                'monthly_%d_%s_%s',
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->statsService->getMonthlyStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in monthly stats", $e);
            $this->outputError('Error loading monthly statistics', 500);
        }
    }

    /**
     * Get annual statistics aggregated by year
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return void Outputs JSON
     */
    public function annual(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $contextId = $context->getId();

        $cacheKey = sprintf('annual_stats_%d', $contextId);

        try {
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->statsService->getAnnualStats(
                    $contextId,
                    null,
                    null,
                    PublicStatsConstants::MIN_YEAR
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in annual stats", $e);
            $this->outputError('Error loading annual statistics', 500);
        }
    }

    /**
     * Get country statistics for geographic distribution
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return void Outputs JSON
     */
    public function countries(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $contextId = $context->getId();
        
        try {
            $data = Cache::remember(
                "country_data_{$contextId}",
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->statsService->getCountryStatistics($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in countries", $e);
            $this->outputError('Error loading country statistics', 500);
        }
    }

    /**
     * Get language distribution statistics for published articles.
     * Optional issueId filter via request var.
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return void Outputs JSON
     */
    public function languages(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $contextId = $context->getId();
        $issueIdRaw = $request->getUserVar('issueId');
        // Match the strict ctype_digit pattern used in CsvExportTrait::exportLanguages
        // and InputValidator: only accept positive whole numbers, never floats.
        $issueId = ($issueIdRaw !== null && ctype_digit((string) $issueIdRaw))
            ? (int) $issueIdRaw
            : null;

        $cacheKey = sprintf('language_stats_%d_%s', $contextId, $issueId ?? 'all');

        try {
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->languageStatsService->getLanguageStats($contextId, $issueId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in language stats", $e);
            $this->outputError('Error loading language statistics', 500);
        }
    }

    /**
     * Get language distribution trends grouped by year of publication.
     *
     * @param array $args URL arguments
     * @param PKPRequest $request Current request
     * @return void Outputs JSON
     */
    public function languageTrends(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $contextId = $context->getId();
        $cacheKey = "language_trends_{$contextId}";

        try {
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->languageStatsService->getLanguageTrends($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in language trends", $e);
            $this->outputError('Error loading language trend statistics', 500);
        }
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Get date ranges based on selected year.
     *
     * @param string|null $selectedYear Year in YYYY format
     * @return array Date ranges with start/end keys
     */
    protected function getDateRanges(?string $selectedYear): array
    {
        return [
            'start' => $selectedYear
                ? $selectedYear . '0101'
                : PublicStatsConstants::MIN_YEAR . '0101',
            'end' => $selectedYear
                ? $selectedYear . '1231'
                : date('Ymd', strtotime('yesterday')),
        ];
    }

    /**
     * Get available years for year selector
     *
     * @return array Years from current to MIN_YEAR
     */
    private function getAvailableYears(): array
    {
        $currentYear = (int)date('Y');
        $minYear = PublicStatsConstants::MIN_YEAR;
        return range($currentYear, $minYear);
    }

    /**
     * Output JSON response with proper headers
     *
     * @param mixed $data Data to encode
     * @return void
     */
    protected function outputJson(mixed $data): void
    {
        // Let OJS finish naturally so shutdown hooks (queue runner, cache flush) run.
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Output error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return void
     */
    protected function outputError(string $message, int $code = 500): void
    {
        http_response_code($code);
        $this->outputJson([
            'error' => true,
            'message' => $message,
            'code' => $code
        ]);
    }

    /**
     * Setup CSS and JavaScript assets for statistics page
     *
     * @param TemplateManager $templateMgr Template manager instance
     * @param PKPRequest $request Current request
     * @return void
     */
    private function setupAssets(TemplateManager $templateMgr, PKPRequest $request): void
    {
        $baseUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath();

        // Load order matters: helpers → feature modules → orchestrator.
        // Each script writes to window.PublicStats; statistics.js destructures it.
        $jsBase = $baseUrl . '/templates/js';

        $templateMgr->addJavaScript(
            'publicStatsHelpers',
            $jsBase . '/statistics-helpers.js',
            ['contexts' => 'frontend', 'priority' => TemplateManager::STYLE_SEQUENCE_CORE]
        );

        $modules = [
            'publicStatsApi'     => '/statistics-api.js',
            'publicStatsCharts'  => '/statistics-charts.js',
            'publicStatsTables'  => '/statistics-tables.js',
            'publicStatsMaps'    => '/statistics-maps.js',
            'publicStatsImpact'  => '/statistics-impact.js',
        ];
        foreach ($modules as $handle => $path) {
            $templateMgr->addJavaScript(
                $handle,
                $jsBase . $path,
                ['contexts' => 'frontend']
            );
        }

        $templateMgr->addJavaScript(
            'publicStatsScript',
            $jsBase . '/statistics.js',
            ['contexts' => 'frontend']
        );

        $templateMgr->addStyleSheet(
            'publicStatsStyles',
            $baseUrl . '/templates/styles/styles.css',
            ['contexts' => 'frontend']
        );
    }
}