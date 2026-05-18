<?php

/**
 * @file plugins/generic/publicStats/services/StatisticsService.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Main statistics service for core metrics aggregation.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Sokil\IsoCodes\IsoCodesFactory;

class StatisticsService extends BaseStatsService
{
    private readonly IsoCodesFactory $isoCodes;

    public function __construct(IsoCodesFactory $isoCodes)
    {
        $this->isoCodes = $isoCodes;
    }

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

        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        $downloadsAssoc = [];
        foreach ($downloadsByMonth as $item) {
            $downloadsAssoc[$item['date']] = $item['value'];
        }

        $viewsAssoc = [];
        foreach ($viewsByMonth as $item) {
            $viewsAssoc[$item['date']] = $item['value'];
        }

        $allMonths = array_unique(array_merge(
            array_keys($downloadsAssoc),
            array_keys($viewsAssoc)
        ));
        sort($allMonths);

        $results = [];
        foreach ($allMonths as $month) {
            $downloads = $downloadsAssoc[$month] ?? 0;
            $views = $viewsAssoc[$month] ?? 0;

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

        $downloadsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        $viewsByMonth = $statsService->getTimeline(
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        $downloadsByYear = $this->aggregateByYear($downloadsByMonth, $minYear);
        $viewsByYear = $this->aggregateByYear($viewsByMonth, $minYear);

        $allYears = array_unique(array_merge(
            array_keys($downloadsByYear),
            array_keys($viewsByYear)
        ));
        sort($allYears);

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
            Logger::error("Error retrieving geographic data", $e);
            return [];
        }
    }

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

    private function formatCountryData(iterable $countriesData): array
    {
        $countryData = [];

        foreach ($countriesData as $total) {
            if (empty($total->country)) {
                continue;
            }

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

        return $countryData;
    }
}
