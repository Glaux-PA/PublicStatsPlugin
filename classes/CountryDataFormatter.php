<?php

/**
 * @file plugins/generic/publicStats/classes/CountryDataFormatter.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CountryDataFormatter
 * @ingroup plugins_generic_publicStats
 *
 * @brief Utility class for geographic statistics formatting.
 *
 * Retrieves and formats country-level access statistics using OJS/OMP
 * geo-statistics service. Converts ISO country codes to human-readable
 * country names using the sokil/php-isocodes library.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use Sokil\IsoCodes\IsoCodesFactory;

class CountryDataFormatter
{
    /**
     * Get country-level access statistics.
     *
     * Retrieves aggregated download and view counts grouped by country.
     * Returns top 50 countries ordered by total access count.
     *
     * @param int $contextId Journal/press ID
     * @return array|null Country statistics or null if no data available
     */
    public static function getCountryStatistics(int $contextId): ?array
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
                return null;
            }

            $countriesData = $geoStatsService->getTotals(
                $params,
                StatisticsHelper::STATISTICS_DIMENSION_COUNTRY
            );

            return self::formatCountryData($countriesData);

        } catch (\Exception $e) {
            error_log("Error retrieving geographic data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format country data with human-readable names.
     *
     * Converts ISO 3166-1 alpha-2 country codes to localized country names.
     * Invalid or unrecognized codes are displayed as-is.
     *
     * @param iterable $countriesData Raw country statistics from geo service
     * @return array|null Formatted country data or null if empty
     */
    private static function formatCountryData(iterable $countriesData): ?array
    {
        $countryData = [];
        $isoCodes = app(IsoCodesFactory::class);

        foreach ($countriesData as $total) {
            if (empty($total->country)) {
                continue;
            }

            // Validate country code format (ISO 3166-1 alpha-2)
            $countryCode = (string)$total->country;
            if (strlen($countryCode) !== 2) {
                continue;
            }

            try {
                $country = $isoCodes->getCountries()->getByAlpha2($countryCode);
                $countryName = $country ? $country->getLocalName() : $countryCode;

                $countryData[] = [
                    'country_code' => $countryCode,
                    'country_name' => $countryName,
                    'total_access' => (int)$total->metric
                ];
            } catch (\Exception $e) {
                // Fall back to country code if lookup fails
                if (strlen($countryCode) == 2) {
                    $countryData[] = [
                        'country_code' => $countryCode,
                        'country_name' => $countryCode,
                        'total_access' => (int)$total->metric
                    ];
                }
            }
        }

        return empty($countryData) ? null : $countryData;
    }
}
