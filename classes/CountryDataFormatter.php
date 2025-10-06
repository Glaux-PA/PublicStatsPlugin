<?php
namespace APP\plugins\generic\publicStats\classes;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use Sokil\IsoCodes\IsoCodesFactory;

class CountryDataFormatter
{
    public static function getCountryStatistics($contextId): ?array
    {
        try {
            $geoStatsService = Services::get('geoStats');
            $params = [
                'contextIds' => [$contextId],
                'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION],
                'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
                'dateEnd' => date('Ymd', strtotime('yesterday')),
                'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
                'count' => 50,
                'offset' => 0
            ];

            $totalCountries = $geoStatsService->getCount($params, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
            if ($totalCountries == 0) return null;
            $countriesData = $geoStatsService->getTotals($params, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
            return self::formatCountryData($countriesData);
        } catch (\Exception $e) {
            error_log("Error retrieving geographic data: " . $e->getMessage());
            return null;
        }
    }

    private static function formatCountryData(array $countriesData): ?array 
    {
        $countryData = [];
        $isoCodes = app(IsoCodesFactory::class);
        
        foreach ($countriesData as $total) {
            if (empty($total->country)) continue;
            try {
                $country = $isoCodes->getCountries()->getByAlpha2($total->country);
                $countryName = $country ? $country->getLocalName() : $total->country;
                $countryData[] = [
                    'country_code' => $total->country,
                    'country_name' => $countryName,
                    'total_access' => (int) $total->metric
                ];
            } catch (\Exception $e) {
                if (strlen($total->country) == 2) {
                    $countryData[] = [
                        'country_code' => $total->country,
                        'country_name' => $total->country,
                        'total_access' => (int) $total->metric
                    ];
                }
            }
        }
        return empty($countryData) ? null : $countryData;
    }
}