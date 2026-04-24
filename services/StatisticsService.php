<?php

/**
 * @file plugins/generic/publicStats/services/StatisticsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Main statistics service for core metrics aggregation.
 *
 * Provides methods to retrieve monthly, annual, and geographic statistics
 * for downloads and views. This is the primary service for basic access metrics
 * and is used by the main statistics display.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Sokil\IsoCodes\IsoCodesFactory;

class StatisticsService extends BaseStatsService
{
    /**
     * @var IsoCodesFactory ISO codes factory for country name lookups
     */
    private readonly IsoCodesFactory $isoCodes;

    /**
     * Constructor.
     *
     * @param IsoCodesFactory $isoCodes Factory for ISO country code lookups
     */
    public function __construct(IsoCodesFactory $isoCodes)
    {
        $this->isoCodes = $isoCodes;
    }

    /**
     * Get monthly statistics.
     *
     * Returns download and view counts aggregated by month in the format
     * expected by the frontend JavaScript.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Monthly statistics array
     */
    public function getMonthlyStats(
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

        // Get timeline data for downloads (file access)
        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        // Get timeline data for views (abstract page)
        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        // Convert to associative arrays for easier merging
        $downloadsAssoc = [];
        foreach ($downloadsByMonth as $item) {
            $downloadsAssoc[$item['date']] = $item['value'];
        }

        $viewsAssoc = [];
        foreach ($viewsByMonth as $item) {
            $viewsAssoc[$item['date']] = $item['value'];
        }

        // Merge all months and sort chronologically
        $allMonths = array_unique(array_merge(
            array_keys($downloadsAssoc),
            array_keys($viewsAssoc)
        ));
        sort($allMonths);

        // Build results in format expected by JavaScript frontend
        $results = [];
        foreach ($allMonths as $month) {
            $downloads = $downloadsAssoc[$month] ?? 0;
            $views = $viewsAssoc[$month] ?? 0;

            // Format month label (e.g., "2024-01" -> "Jan 2024")
            $timestamp = strtotime($month . '-01');
            $label = date('M Y', $timestamp);

            $results[] = [
                'month' => $month,
                'downloads' => [
                    'label' => $label,
                    'value' => $downloads
                ],
                'views' => [
                    'label' => $label,
                    'value' => $views
                ],
                'total' => $downloads + $views
            ];
        }

        return $results;
    }

    /**
     * Get annual statistics.
     *
     * Returns download and view counts aggregated by year.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @param int|null $minYear Minimum year to include (defaults to PublicStatsConstants::MIN_YEAR)
     * @return array Annual statistics array
     */
    public function getAnnualStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        ?int $minYear = null
    ): array {
        $minYear ??= PublicStatsConstants::MIN_YEAR;
        $statsService = Services::get('publicationStats');

        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];

        // Get monthly data first, then aggregate
        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        // Aggregate by year
        $downloadsByYear = $this->aggregateByYear($downloadsByMonth, $minYear);
        $viewsByYear = $this->aggregateByYear($viewsByMonth, $minYear);

        // Combine all years
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
                'year' => (string)$year,
                'downloads' => $downloads,
                'views' => $views,
                'total' => $downloads + $views
            ];
        }

        return $results;
    }

    /**
     * Get country statistics.
     *
     * Returns access counts aggregated by country with localized names.
     * Always returns an array (possibly empty) so the frontend can iterate safely.
     *
     * @param int $contextId Journal/press ID
     * @return array Country statistics (empty array if no data or on error)
     */
    public function getCountryStatistics(int $contextId): array
    {
        try {
            $geoStatsService = Services::get('geoStats');

            $params = [
                'contextIds' => [$contextId],
                'assocTypes' => [
                    Application::ASSOC_TYPE_SUBMISSION_FILE,
                    Application::ASSOC_TYPE_SUBMISSION
                ],
                'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
                'dateEnd' => date('Ymd', strtotime('yesterday')),
                'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
                'count' => 50,
                'offset' => 0
            ];

            $totalCountries = $geoStatsService->getCount(
                $params,
                StatisticsHelper::STATISTICS_DIMENSION_COUNTRY
            );

            if ($totalCountries == 0) {
                return [];
            }

            $countriesData = $geoStatsService->getTotals(
                $params,
                StatisticsHelper::STATISTICS_DIMENSION_COUNTRY
            );

            return $this->formatCountryData($countriesData);

        } catch (\Exception $e) {
            error_log("Error retrieving geographic data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aggregate monthly data by year.
     *
     * @param array $monthlyData Array of monthly records
     * @param int $minYear Minimum year to include
     * @return array Associative array of year => total
     */
    private function aggregateByYear(array $monthlyData, int $minYear): array
    {
        $yearlyData = [];

        foreach ($monthlyData as $monthData) {
            $year = (int)substr($monthData['date'], 0, 4);

            if ($year < $minYear) {
                continue;
            }

            if (!isset($yearlyData[$year])) {
                $yearlyData[$year] = 0;
            }

            $yearlyData[$year] += $monthData['value'];
        }

        return $yearlyData;
    }

    /**
     * Format country data with localized names.
     *
     * @param iterable $countriesData Raw country data from geo service
     * @return array|null Formatted country data or null if empty
     */
    private function formatCountryData(iterable $countriesData): ?array
    {
        $countryData = [];

        foreach ($countriesData as $total) {
            if (empty($total->country)) {
                continue;
            }

            // Validate ISO 3166-1 alpha-2 format
            $countryCode = (string)$total->country;
            if (strlen($countryCode) !== 2) {
                continue;
            }

            try {
                $country = $this->isoCodes->getCountries()->getByAlpha2($countryCode);
                $countryName = $country ? $country->getLocalName() : $countryCode;
            } catch (\Exception $e) {
                $countryName = $countryCode;
            }

            $countryData[] = [
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'total_access' => (int)$total->metric
            ];
        }

        return empty($countryData) ? null : $countryData;
    }
}
