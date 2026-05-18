<?php

/**
 * @file plugins/generic/publicStats/controllers/PublicStatisticsHandler.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
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

use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\publicStats\PublicStatsPlugin;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\classes\ColorHelper;
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
use APP\plugins\generic\publicStats\controllers\traits\ArticleStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\EditorialStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\AuthorReviewerStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\EnrichedStatsTrait;
use APP\plugins\generic\publicStats\controllers\traits\CsvExportTrait;
use Illuminate\Support\Facades\Cache;

class PublicStatisticsHandler extends Handler
{
    use ArticleStatsTrait;
    use EditorialStatsTrait;
    use AuthorReviewerStatsTrait;
    use EnrichedStatsTrait;
    use CsvExportTrait;

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


    public function __construct()
    {
        parent::__construct();

        $plugin = PluginRegistry::getPlugin('generic', 'publicstatsplugin');
        if (!$plugin instanceof PublicStatsPlugin) {
            throw new \RuntimeException('publicStats plugin is not registered or disabled.');
        }
        $this->plugin = $plugin;

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
            'primaryColor' => $colorVariants['primary'],
            'primaryColorLight' => $colorVariants['light'],
            'primaryColorDark' => $colorVariants['dark'],
            'primaryColorDarker' => $colorVariants['darker'],
            'primaryColorRgb' => $colorVariants['rgb'],
            'enabledSubsections' => $enabledSubsections,
            'defaultSection'     => $defaultSection,
            'availableIssues'    => $this->languageStatsService->getPublishedIssues($contextId),
            'publicStatsI18nJson' => $this->buildJsI18nJson(),
        ]);

        $this->setupAssets($templateMgr, $request);

        return $templateMgr->display($this->plugin->getTemplateResource('publicStats.tpl'));
    }

    private function getColorSettings(int $contextId): array
    {
        $primaryColor = $this->plugin->getSetting($contextId, 'primaryColor');

        if (empty($primaryColor) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $primaryColor)) {
            $primaryColor = ColorHelper::DEFAULT_COLOR;
        }

        return ColorHelper::calculateVariants($primaryColor);
    }

    private function getEnabledSubsections(int $contextId): array
    {
        return $this->plugin->getEnabledSubsections($contextId);
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

    public function monthly(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        if (!$this->requireSubsection('monthly-trends', $context)) return;

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

    public function annual(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        if (!$this->requireSubsection('annual-trends', $context)) return;

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

    public function countries(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        if (!$this->requireSubsection('geographic-distribution', $context)) return;

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

    public function languages(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        if (!$this->requireSubsection('general-languages', $context)) return;

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

    public function languageTrends(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        if (!$this->requireSubsection('language-trends', $context)) return;

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

    private function getAvailableYears(): array
    {
        $currentYear = (int)date('Y');
        $minYear = PublicStatsConstants::MIN_YEAR;
        return range($currentYear, $minYear);
    }

    /**
     * i18n bag for templates/js/*.js. Built in PHP and json_encoded so a
     * translator using a quote or backslash in a msgstr can't break the inline script.
     */
    private function buildJsI18nJson(): string
    {
        $bag = [
            'downloads' => __('plugins.generic.publicStats.downloads'),
            'views' => __('plugins.generic.publicStats.views'),
            'received' => __('plugins.generic.publicStats.received'),
            'published' => __('plugins.generic.publicStats.published'),
            'declined' => __('plugins.generic.publicStats.declined'),
            'inProcess' => __('plugins.generic.publicStats.inProcess'),
            'totalReceived' => __('plugins.generic.publicStats.totalReceived'),
            'totalAccesses' => __('plugins.generic.publicStats.totalAccesses'),
            'errorLoading' => __('plugins.generic.publicStats.errorLoading'),
            'monthlyOverview' => __('plugins.generic.publicStats.monthlyOverview'),
            'mostDownloadedArticles' => __('plugins.generic.publicStats.mostDownloadedArticles'),
            'mostViewedArticles' => __('plugins.generic.publicStats.mostViewedArticles'),
            'downloadsByIssue' => __('plugins.generic.publicStats.downloadsByIssue'),
            'downloadsBySection' => __('plugins.generic.publicStats.downloadsBySection'),
            'submissionsOverview' => __('plugins.generic.publicStats.submissionsOverview'),
            'totalAuthors' => __('plugins.generic.publicStats.totalAuthors'),
            'totalReviewers' => __('plugins.generic.publicStats.totalReviewers'),
            'otherInstitutions' => __('plugins.generic.publicStats.otherInstitutions'),
            'acceptancePublicationStats' => __('plugins.generic.publicStats.acceptancePublicationDaysTitle'),
            'firstDecisionStats' => __('plugins.generic.publicStats.firstDecisionDaysTitle'),
            'noDataAvailable' => __('plugins.generic.publicStats.noDataAvailable'),
            'noDataMessage' => __('plugins.generic.publicStats.noDataMessage'),
            'noAuthorsData' => __('plugins.generic.publicStats.noAuthorsData'),
            'noReviewersData' => __('plugins.generic.publicStats.noReviewersData'),
            'noReviewerListData' => __('plugins.generic.publicStats.noReviewerListData'),
            'reviewerListCardTitle' => __('plugins.generic.publicStats.reviewerListCardTitle'),
            'noInstitutionData' => __('plugins.generic.publicStats.noInstitutionData'),
            'noMonthlyData' => __('plugins.generic.publicStats.noMonthlyData'),
            'noAnnualData' => __('plugins.generic.publicStats.noAnnualData'),
            'noIssueData' => __('plugins.generic.publicStats.noIssueData'),
            'noSectionData' => __('plugins.generic.publicStats.noSectionData'),
            'noEditorialData' => __('plugins.generic.publicStats.noEditorialData'),
            'noEditorialAnnualData' => __('plugins.generic.publicStats.noEditorialAnnualData'),
            'noDownloadsData' => __('plugins.generic.publicStats.noDownloadsData'),
            'noViewsData' => __('plugins.generic.publicStats.noViewsData'),
            'noGeographicData' => __('plugins.generic.publicStats.noGeographicData'),
            'noRecentDownloadsData' => __('plugins.generic.publicStats.noRecentDownloadsData'),
            'noRecentViewsData' => __('plugins.generic.publicStats.noRecentViewsData'),
            'externalCitations' => __('plugins.generic.publicStats.externalCitations'),
            'year' => __('plugins.generic.publicStats.year'),
            'noCitationData' => __('plugins.generic.publicStats.noCitationData'),
            'computingPlaceholder' => __('plugins.generic.publicStats.computingPlaceholder'),
            'citationsReceived' => __('plugins.generic.publicStats.citationsReceived'),
            'citationsReceivedInYear' => __('plugins.generic.publicStats.citationsReceivedInYear'),
            'openAccess' => __('plugins.generic.publicStats.openAccess'),
            'oaPercentage' => __('plugins.generic.publicStats.oaPercentage'),
            'oaDiamond' => __('plugins.generic.publicStats.oaDiamond'),
            'oaGold' => __('plugins.generic.publicStats.oaGold'),
            'oaHybrid' => __('plugins.generic.publicStats.oaHybrid'),
            'oaGreen' => __('plugins.generic.publicStats.oaGreen'),
            'oaBronze' => __('plugins.generic.publicStats.oaBronze'),
            'oaClosed' => __('plugins.generic.publicStats.oaClosed'),
            'oaUnknown' => __('plugins.generic.publicStats.oaUnknown'),
            'articlesAnalyzed' => __('plugins.generic.publicStats.articlesAnalyzed'),
            'noOaData' => __('plugins.generic.publicStats.noOaData'),
            'articlesInArea' => __('plugins.generic.publicStats.articlesInArea'),
            'noThematicData' => __('plugins.generic.publicStats.noThematicData'),
            'citationsFromCountry' => __('plugins.generic.publicStats.citationsFromCountry'),
            'noCitationMapData' => __('plugins.generic.publicStats.noCitationMapData'),
            'noCitingJournalsData' => __('plugins.generic.publicStats.noCitingJournalsData'),
            'citationsFromJournal' => __('plugins.generic.publicStats.citationsFromJournal'),
            'citedArticlesFromJournal' => __('plugins.generic.publicStats.citedArticlesFromJournal'),
            'timesCited' => __('plugins.generic.publicStats.timesCited'),
            'allTime' => __('plugins.generic.publicStats.allTime'),
            'noLanguageData' => __('plugins.generic.publicStats.noLanguageData'),
            'noLanguageTrendsData' => __('plugins.generic.publicStats.noLanguageTrendsData'),
            'totalArticles' => __('plugins.generic.publicStats.totalArticles'),
            'languagesIdentified' => __('plugins.generic.publicStats.languagesIdentified'),
            'leadingLanguage' => __('plugins.generic.publicStats.leadingLanguage'),
            'dataPeriod' => __('plugins.generic.publicStats.dataPeriod'),
            'affiliation' => __('plugins.generic.publicStats.affiliation'),
            'annualStats' => __('plugins.generic.publicStats.annualStats'),
            'articleTitle' => __('plugins.generic.publicStats.articleTitle'),
            'authors' => __('plugins.generic.publicStats.authors'),
            'authorsByCountry' => __('plugins.generic.publicStats.authorsByCountry'),
            'citations' => __('plugins.generic.publicStats.citations'),
            'country' => __('plugins.generic.publicStats.country'),
            'countryStats' => __('plugins.generic.publicStats.countryStats'),
            'days' => __('plugins.generic.publicStats.days'),
            'daysAverage' => __('plugins.generic.publicStats.daysAverage'),
            'editorialStats' => __('plugins.generic.publicStats.editorialStats'),
            'export' => __('plugins.generic.publicStats.export'),
            'exportCsv' => __('plugins.generic.publicStats.exportCsv'),
            'exportOptions' => __('plugins.generic.publicStats.exportOptions'),
            'fullReport' => __('plugins.generic.publicStats.fullReport'),
            'monthlyStats' => __('plugins.generic.publicStats.monthlyStats'),
            'name' => __('plugins.generic.publicStats.name'),
            'noArticles' => __('plugins.generic.publicStats.noArticles'),
            'noArticlesData' => __('plugins.generic.publicStats.noArticlesData'),
            'noCoAuthors' => __('plugins.generic.publicStats.noCoAuthors'),
            'noDecisionData' => __('plugins.generic.publicStats.noDecisionData'),
            'noPublicationData' => __('plugins.generic.publicStats.noPublicationData'),
            'publications' => __('plugins.generic.publicStats.publications'),
            'publicationsReviewed' => __('plugins.generic.publicStats.publicationsReviewed'),
            'reviewersByCountry' => __('plugins.generic.publicStats.reviewersByCountry'),
            'selectAuthorPlaceholder' => __('plugins.generic.publicStats.selectAuthorPlaceholder'),
            'submissionsReviewed' => __('plugins.generic.publicStats.submissionsReviewed'),
            'topCited' => __('plugins.generic.publicStats.topCited'),
            'topCitedArticles' => __('plugins.generic.publicStats.topCitedArticles'),
            'topDownloaded' => __('plugins.generic.publicStats.topDownloaded'),
            'topViewed' => __('plugins.generic.publicStats.topViewed'),
            'totalPublications' => __('plugins.generic.publicStats.totalPublications'),
            'totalSubmissions' => __('plugins.generic.publicStats.totalSubmissions'),
        ];
        return json_encode($bag, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    protected function outputJson(mixed $data): void
    {
        // Let OJS finish naturally so shutdown hooks (queue runner, cache flush) run.
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    protected function outputError(string $message, int $code = 500): void
    {
        http_response_code($code);
        $this->outputJson([
            'error' => true,
            'message' => $message,
            'code' => $code
        ]);
    }

    // Disabled subsections must not be reachable via direct HTTP even when the
    // sidebar link is hidden — reviewer-list and author-stats expose personal data.
    protected function requireSubsection(string $subsectionId, object $context): bool
    {
        if (!in_array($subsectionId, $this->getEnabledSubsections($context->getId()), true)) {
            $this->outputError('Not found', 404);
            return false;
        }
        return true;
    }

    private function setupAssets(TemplateManager $templateMgr, PKPRequest $request): void
    {
        $baseUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath();
        $jsBase = $baseUrl . '/templates/js';
        $cssBase = $baseUrl . '/templates/styles';

        // Vendor at CORE priority and registered before the plugin's own scripts.
        $vendorJs = [
            'publicStatsVendorChart'    => '/vendor/chart.min.js',
            'publicStatsVendorHammer'   => '/vendor/hammer.min.js',
            'publicStatsVendorZoom'     => '/vendor/chartjs-plugin-zoom.min.js',
            'publicStatsVendorLeaflet'  => '/vendor/leaflet.js',
        ];
        foreach ($vendorJs as $handle => $path) {
            $templateMgr->addJavaScript(
                $handle,
                $jsBase . $path,
                ['contexts' => 'frontend', 'priority' => TemplateManager::STYLE_SEQUENCE_CORE]
            );
        }

        $vendorCss = [
            'publicStatsVendorLeafletCss'    => '/vendor/leaflet.css',
            'publicStatsVendorFontAwesome'   => '/vendor/fontawesome/css/fontawesome.min.css',
            'publicStatsVendorFaSolid'       => '/vendor/fontawesome/css/solid.min.css',
        ];
        foreach ($vendorCss as $handle => $path) {
            $templateMgr->addStyleSheet(
                $handle,
                $cssBase . $path,
                ['contexts' => 'frontend', 'priority' => TemplateManager::STYLE_SEQUENCE_CORE]
            );
        }

        // Plugin scripts: helpers > feature modules > orchestrator.
        // Each script writes to window.PublicStats; statistics.js destructures it.
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
            $cssBase . '/styles.css',
            ['contexts' => 'frontend']
        );
    }
}