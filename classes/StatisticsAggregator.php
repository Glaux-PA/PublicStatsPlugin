<?php

/**
 * @file plugins/generic/publicStats/classes/StatisticsAggregator.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsAggregator
 * @ingroup plugins_generic_publicStats
 *
 * @brief Utility class for aggregating publication statistics.
 *
 * Provides static methods to retrieve and aggregate download/view statistics
 * at monthly and annual granularities. Uses OJS/OMP statistics services
 * to fetch timeline data and aggregates it for display purposes.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;

class StatisticsAggregator
{
    /**
     * Get monthly download and view statistics.
     *
     * Retrieves timeline data for both downloads (submission files) and
     * views (abstract pages) aggregated by month.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Monthly statistics with downloads, views, and totals
     */
    public static function getMonthlyStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $statsService = Services::get('publicationStats');

        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        // Fetch download statistics (file downloads)
        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        // Fetch view statistics (abstract page views)
        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        // Merge all months from both datasets
        $allMonths = array_unique(array_merge(
            array_keys($downloadsByMonth),
            array_keys($viewsByMonth)
        ));
        sort($allMonths);

        // Build results array
        $results = [];
        foreach ($allMonths as $month) {
            $downloads = $downloadsByMonth[$month] ?? 0;
            $views = $viewsByMonth[$month] ?? 0;

            $results[] = [
                'month' => $month,
                'downloads' => $downloads,
                'views' => $views,
                'total' => $downloads + $views
            ];
        }

        return $results;
    }

    /**
     * Get annual download and view statistics.
     *
     * Aggregates monthly timeline data into yearly totals.
     * Filters out years before the specified minimum year.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @param string $minYear Minimum year to include (default: '2015')
     * @return array Annual statistics with downloads, views, and totals
     */
    public static function getAnnualStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        string $minYear = '2015'
    ): array {
        $statsService = Services::get('publicationStats');

        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        // Fetch monthly data first
        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        // Aggregate by year
        $downloadsByYear = self::aggregateByYear($downloadsByMonth, $minYear);
        $viewsByYear = self::aggregateByYear($viewsByMonth, $minYear);

        // Merge all years
        $allYears = array_unique(array_merge(
            array_keys($downloadsByYear),
            array_keys($viewsByYear)
        ));
        sort($allYears);

        // Build results array
        $results = [];
        foreach ($allYears as $year) {
            $downloads = $downloadsByYear[$year] ?? 0;
            $views = $viewsByYear[$year] ?? 0;

            $results[] = [
                'year' => $year,
                'downloads' => $downloads,
                'views' => $views,
                'total' => $downloads + $views
            ];
        }

        return $results;
    }

    /**
     * Aggregate monthly data into yearly totals.
     *
     * @param array $monthlyData Array of monthly records with 'date' and 'value' keys
     * @param string $minYear Minimum year to include
     * @return array Associative array of year => total
     */
    private static function aggregateByYear(array $monthlyData, string $minYear): array
    {
        $yearlyData = [];

        foreach ($monthlyData as $item) {
            $year = (int)substr($item['date'], 0, 4);

            // Skip years before minimum
            if ($year < (int)$minYear) {
                continue;
            }

            if (!isset($yearlyData[$year])) {
                $yearlyData[$year] = 0;
            }

            $yearlyData[$year] += $item['value'];
        }

        return $yearlyData;
    }
}
