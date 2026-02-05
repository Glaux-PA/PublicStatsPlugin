<?php
/**
 * @file plugins/generic/publicStats/controllers/PublicStatisticsHandler.php
 *
 * @class PublicStatisticsHandler
 * @brief Main handler for public statistics display
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers;

// Core OJS
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\plugins\PluginRegistry;

// Plugin classes
use APP\plugins\generic\publicStats\classes\InputValidator;
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
    
    private ?object $plugin = null;
    private StatisticsService $statsService;
    private ArticleStatsService $articleService;
    private EditorialStatsService $editorialService;
    private DecisionStatsService $decisionService;
    private AuthorReviewerStatsService $authorReviewerService;
    private IssueStatsService $issueService;
    private SectionStatsService $sectionService;
    private AuthorStatsService $authorStatsService;
    private OpenAlexService $openalexService;
    private EnrichedStatsService $enrichedService;

    // ========================================
    // Constructor
    // ========================================

    /**
     * Initialize handler with all service dependencies
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->plugin = PluginRegistry::getPlugin('generic', 'publicstatsplugin');
        
        // Initialize all services
        $this->statsService = app(StatisticsService::class);
        $this->articleService = app(ArticleStatsService::class);
        $this->editorialService = app(EditorialStatsService::class);
        $this->decisionService = app(DecisionStatsService::class);
        $this->authorReviewerService = app(AuthorReviewerStatsService::class);
        $this->issueService = app(IssueStatsService::class);
        $this->sectionService = app(SectionStatsService::class);
        $this->authorStatsService = app(AuthorStatsService::class);
        $this->openalexService = app(OpenAlexService::class);
        $this->enrichedService = new EnrichedStatsService();
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

        // Get color settings
        $colorVariants = $this->getColorSettings($context->getId());

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
        $sectionId = InputValidator::validateSectionId($request, $request->getUserVar('sectionId'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year, $sectionId);
            
            $cacheKey = sprintf(
                'monthly_%d_%s_%s_%s',
                $contextId,
                $dateRanges['start'],
                $dateRanges['end'],
                $sectionId ?? 'all'
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->statsService->getMonthlyStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end'],
                    $sectionId
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            error_log("Error in monthly stats: " . $e->getMessage());
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
        $sectionId = InputValidator::validateSectionId($request, $request->getUserVar('sectionId'));
        
        $cacheKey = sprintf(
            'annual_stats_%d_%s',
            $contextId,
            $sectionId ?? 'all'
        );
        
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
            error_log("Error in annual stats: " . $e->getMessage());
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
            error_log("Error in countries: " . $e->getMessage());
            $this->outputError('Error loading country statistics', 500);
        }
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Get date ranges based on selected year and section
     *
     * @param string|null $selectedYear Year in YYYY format
     * @param int|null $sectionId Optional section filter
     * @return array Date ranges with start/end keys
     */
    protected function getDateRanges(?string $selectedYear, ?int $sectionId = null): array
    {
        $ranges = [
            'start' => $selectedYear 
                ? $selectedYear . '0101' 
                : PublicStatsConstants::MIN_YEAR . '0101',
            'end' => $selectedYear 
                ? $selectedYear . '1231' 
                : date('Ymd', strtotime('yesterday')),
        ];
        
        if ($sectionId !== null) {
            $ranges['sectionId'] = $sectionId;
        }
        
        return $ranges;
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
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
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

        $templateMgr->addJavaScript(
            'publicStatsScript',
            $baseUrl . '/templates/js/statistics.js',
            ['contexts' => 'frontend']
        );

        $templateMgr->addStyleSheet(
            'publicStatsStyles',
            $baseUrl . '/templates/styles/styles.css',
            ['contexts' => 'frontend']
        );
    }
}