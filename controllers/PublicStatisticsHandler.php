<?php
namespace APP\plugins\generic\publicStats\controllers;

use APP\handler\Handler;
use APP\template\TemplateManager;
use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use PKP\plugins\PluginRegistry;
use APP\facades\Repo;
use APP\plugins\generic\publicStats\classes\StatisticsAggregator;
use APP\plugins\generic\publicStats\classes\CountryDataFormatter;
use APP\plugins\generic\publicStats\classes\ArticleStatsRetriever;

class PublicStatisticsHandler extends Handler
{
    private $plugin;
    private const MIN_YEAR = '2015';

    public function __construct()
    {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', 'publicstatsplugin');
    }

    /**
     * Main handler for displaying statistics page
     */
    public function total(array $args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $contextId = $context->getId();

        $selectedYear = $request->getUserVar('year');
        $dateRanges = $this->calculateDateRanges($selectedYear);

        $statsData = $this->gatherStatisticsData($request, $contextId, $dateRanges);

        $templateMgr->assign([
            'pageTitle' => 'Public Statistics',
            'topDownloadedArticles' => json_encode($statsData['topDownloadedArticles']),
            'topViewedArticles' => json_encode($statsData['topViewedArticles']),
            'monthlyStats' => json_encode($statsData['monthlyStats']),
            'annualStats' => json_encode($statsData['annualStats']),
            'countryData' => json_encode($statsData['countryData']),
            'issueStats' => json_encode($statsData['issueStats']),
            'sectionStats' => json_encode($statsData['sectionStats']),
            'recentTopDownloaded' => json_encode($statsData['recentTopDownloaded']),
            'recentTopViewed' => json_encode($statsData['recentTopViewed']),
            'editorialStats' => json_encode($statsData['editorialStats']), 
            'availableYears' => $this->getAvailableYears(),
            'selectedYear' => $selectedYear,
        ]);

        $this->addStylesAndScripts($templateMgr, $request);

        return $templateMgr->display($this->plugin->getTemplateResource('publicStats.tpl'));
    }

    /**
     * Calculate date ranges based on selected year
     */
    private function calculateDateRanges($selectedYear): array
    {
        if ($selectedYear) {
            return [
                'dateStart' => $selectedYear . '0101',
                'dateEnd' => $selectedYear . '1231'
            ];
        }

        return [
            'dateStart' => self::MIN_YEAR . '0101',
            'dateEnd' => null
        ];
    }

    /**
     * Gather all statistics data
     */
    private function gatherStatisticsData($request, $contextId, $dateRanges): array
    {
        return [
            'topDownloadedArticles' => ArticleStatsRetriever::getTopDownloadedArticles(
                $request, $contextId, 20, $dateRanges['dateStart'], $dateRanges['dateEnd']
            ),
            'topViewedArticles' => ArticleStatsRetriever::getTopViewedArticles(
                $request, $contextId, 20, $dateRanges['dateStart'], $dateRanges['dateEnd']
            ),
            'monthlyStats' => StatisticsAggregator::getMonthlyStats(
                $contextId, $dateRanges['dateStart'], $dateRanges['dateEnd']
            ),
            'annualStats' => StatisticsAggregator::getAnnualStats($contextId),
            'countryData' => CountryDataFormatter::getCountryStatistics($contextId),
            'issueStats' => $this->getIssueStats($request, $dateRanges['dateStart'], $dateRanges['dateEnd']),
            'sectionStats' => $this->getSectionStatsDetailed($request, $contextId, $dateRanges['dateStart'], $dateRanges['dateEnd']),
            'recentTopDownloaded' => ArticleStatsRetriever::getRecentTopDownloadedArticles($request, $contextId, 20),
            'recentTopViewed' => ArticleStatsRetriever::getRecentTopViewedArticles($request, $contextId, 20),
            'editorialStats' => $this->getEditorialStats($request, $dateRanges['dateStart'], $dateRanges['dateEnd']), // NUEVO
        ];
    }
    /**
     * Get available years for selector
     */
    private function getAvailableYears(): array
    {
        $currentYear = (int)date('Y');
        return range($currentYear, self::MIN_YEAR);
    }

    /**
     * Add styles and scripts to template
     */
    private function addStylesAndScripts($templateMgr, $request): void
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

    /**
     * Get issue statistics
     */
    public function getIssueStats($request, $dateStart = null, $dateEnd = null): array 
    {
        $statsService = Services::get('issueStats');
        $context = $request->getContext();
        $contextId = $context->getId();

        $params = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday')),
            'orderBy' => 'total',
            'orderDirection' => 'DESC'
        ];

        $records = $statsService->getTotals($params);
        $results = [];

        foreach ($records as $record) {
            if (!isset($record->issue_id)) {
                continue;
            }

            $issue = Repo::issue()->get($record->issue_id);
            
            if (!$issue || $issue->getData('journalId') != $contextId) {
                continue;
            }

            $articleCount = Repo::submission()
                ->getCollector()
                ->filterByContextIds([$contextId])
                ->filterByIssueIds([$issue->getId()])
                ->filterByStatus([STATUS_PUBLISHED])
                ->getCount();

            $results[] = [
                'issueId' => $issue->getId(),
                'title' => $issue->getIssueIdentification(),
                'downloads' => $record->metric ?? 0,
                'articleCount' => $articleCount
            ];
        }

        return $results;
    }

    /**
     * Get statistics by section with views and downloads
     */
    public function getSectionStatsDetailed($request, $contextId, $dateStart = null, $dateEnd = null): array 
    {
        $statsService = Services::get('publicationStats');

        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        $downloadParams = array_merge($baseParams, [
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]
        ]);
        $downloadRecords = $statsService->getTotals($downloadParams);

        $viewParams = array_merge($baseParams, [
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]
        ]);
        $viewRecords = $statsService->getTotals($viewParams);

        $sectionStats = StatisticsAggregator::aggregateBySection($downloadRecords, $contextId, 'downloads');
        $viewsBySection = StatisticsAggregator::aggregateBySection($viewRecords, $contextId, 'views');

        foreach ($viewsBySection as $sectionId => $data) {
            if (isset($sectionStats[$sectionId])) {
                $sectionStats[$sectionId]['views'] = $data['views'];
            } else {
                $sectionStats[$sectionId] = $data;
                $sectionStats[$sectionId]['downloads'] = 0;
            }
        }

        foreach ($sectionStats as $sectionId => &$stats) {
            if (!isset($stats['views'])) {
                $stats['views'] = 0;
            }
            $stats['total'] = $stats['downloads'] + $stats['views'];
        }

        $results = array_values($sectionStats);
        usort($results, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        return $results;
    }

  /**
     * Get editorial statistics by month (submissions received, declined, published, in process)
     */
    public function getEditorialStats($request, $dateStart = null, $dateEnd = null): array
    {
        $context = $request->getContext();
        $contextId = $context->getId();
        
        $dateStart = $dateStart ?? (self::MIN_YEAR . '0101');
        $dateEnd = $dateEnd ?? date('Ymd', strtotime('yesterday'));
        
        $startTime = strtotime($dateStart);
        $endTime = strtotime($dateEnd);
        
        $monthlyStats = [];
        $currentTime = $startTime;
        
        while ($currentTime <= $endTime) {
            $monthKey = date('Y-m', $currentTime);
            $monthlyStats[$monthKey] = [
                'month' => $monthKey,
                'label' => date('M Y', $currentTime), 
                'received' => 0,
                'declined' => 0,
                'published' => 0,
                'inProcess' => 0
            ];
            
            $currentTime = strtotime('+1 month', $currentTime);
        }
        
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();
        
        foreach ($submissions as $submission) {
            $dateSubmitted = $submission->getData('dateSubmitted');
            if (!$dateSubmitted) continue;
            
            $submissionTime = strtotime($dateSubmitted);
            
            if ($submissionTime < $startTime || $submissionTime > $endTime) continue;
            
            $monthKey = date('Y-m', $submissionTime);
            
            if (!isset($monthlyStats[$monthKey])) continue;

            $monthlyStats[$monthKey]['received']++;

            $status = $submission->getData('status');
            
            switch ($status) {
                case STATUS_PUBLISHED:
                    $monthlyStats[$monthKey]['published']++;
                    break;
                case STATUS_DECLINED:
                    $monthlyStats[$monthKey]['declined']++;
                    break;
                case STATUS_QUEUED:
                case STATUS_SCHEDULED:
                    $monthlyStats[$monthKey]['inProcess']++;
                    break;
            }
        }

        return array_values($monthlyStats);
    }


    /**
     * Get statistics data as JSON via AJAX
     */
    public function getStatsData(array $args, $request)
    {
        $context = $request->getContext();
        $contextId = $context->getId();

        $selectedYear = $request->getUserVar('year');
        $dateRanges = $this->calculateDateRanges($selectedYear);
        
        $statsData = $this->gatherStatisticsData($request, $contextId, $dateRanges);
        
        header('Content-Type: application/json');
        echo json_encode($statsData);
        exit;
    }
}