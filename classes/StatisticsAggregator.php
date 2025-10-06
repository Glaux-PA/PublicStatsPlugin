<?php
namespace APP\plugins\generic\publicStats\classes;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;

class StatisticsAggregator
{
    public static function getMonthlyStats($contextId, $dateStart = null, $dateEnd = null): array 
    {
        $statsService = Services::get('publicationStats');
        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        $downloadsByMonth = $statsService->getTimeline(StatisticsHelper::STATISTICS_DIMENSION_MONTH, 
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]]));
        $viewsByMonth = $statsService->getTimeline(StatisticsHelper::STATISTICS_DIMENSION_MONTH, 
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]]));

        $allMonths = array_unique(array_merge(array_keys($downloadsByMonth), array_keys($viewsByMonth)));
        sort($allMonths);

        $results = [];
        foreach ($allMonths as $month) {
            $downloads = $downloadsByMonth[$month] ?? 0;
            $views = $viewsByMonth[$month] ?? 0;
            $results[] = ['month' => $month, 'downloads' => $downloads, 'views' => $views, 'total' => $downloads + $views];
        }
        return $results;
    }

    public static function getAnnualStats($contextId, $dateStart = null, $dateEnd = null, $minYear = '2015'): array 
    {
        $statsService = Services::get('publicationStats');
        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        $downloadsByMonth = $statsService->getTimeline(StatisticsHelper::STATISTICS_DIMENSION_MONTH, 
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]]));
        $viewsByMonth = $statsService->getTimeline(StatisticsHelper::STATISTICS_DIMENSION_MONTH, 
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]]));

        $downloadsByYear = self::aggregateByYear($downloadsByMonth, $minYear);
        $viewsByYear = self::aggregateByYear($viewsByMonth, $minYear);

        $allYears = array_unique(array_merge(array_keys($downloadsByYear), array_keys($viewsByYear)));
        sort($allYears);

        $results = [];
        foreach ($allYears as $year) {
            $downloads = $downloadsByYear[$year] ?? 0;
            $views = $viewsByYear[$year] ?? 0;
            $results[] = ['year' => $year, 'downloads' => $downloads, 'views' => $views, 'total' => $downloads + $views];
        }
        return $results;
    }

    private static function aggregateByYear(array $monthlyData, int $minYear): array 
    {
        $yearlyData = [];
        foreach ($monthlyData as $monthData) {
            $year = substr($monthData['date'], 0, 4);
            if ($year >= $minYear) {
                if (!isset($yearlyData[$year])) $yearlyData[$year] = 0;
                $yearlyData[$year] += $monthData['value'];
            }
        }
        return $yearlyData;
    }

    public static function aggregateBySection($records, $contextId, $metricType): array 
    {
        $sectionStats = [];
        foreach ($records as $record) {
            if (!isset($record->submission_id)) continue;
            $submission = \APP\facades\Repo::submission()->get($record->submission_id);
            if (!$submission || $submission->getData('contextId') != $contextId) continue;
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $sectionId = $publication->getData('sectionId');
            if (!$sectionId) continue;
            $section = \APP\facades\Repo::section()->get($sectionId);
            if (!$section) continue;

            if (!isset($sectionStats[$sectionId])) {
                $sectionStats[$sectionId] = [
                    'sectionId' => $sectionId,
                    'sectionTitle' => $section->getLocalizedTitle(),
                    $metricType => 0,
                    'articleCount' => 0
                ];
            }
            $sectionStats[$sectionId][$metricType] += $record->metric ?? 0;
            $sectionStats[$sectionId]['articleCount']++;
        }
        return $sectionStats;
    }
}