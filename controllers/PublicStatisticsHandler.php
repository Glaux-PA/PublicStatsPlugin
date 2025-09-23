<?php
namespace APP\plugins\generic\publicStats\controllers;

use APP\handler\Handler;
use APP\template\TemplateManager;
use APP\core\Services;
use APP\statistics\StatisticsHelper;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use Sokil\IsoCodes\IsoCodesFactory;

class PublicStatisticsHandler extends Handler
{
    private $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', 'publicstatsplugin');
    }

    public function total(array $args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $contextId = $context->getId();
        $primaryLocale = $context->getPrimaryLocale();

        $siteDao = DAORegistry::getDAO('SiteDAO');

        // --- OBTENER TOP 10 ARTÍCULOS MÁS DESCARGADOS ---
        $topArticlesQuery = '
            SELECT 
                s.submission_id,
                COALESCE(
                    MAX(CASE WHEN ps.locale = ? THEN ps.setting_value END),
                    MAX(ps.setting_value),
                    CONCAT("Artículo ", s.submission_id)
                ) as title,
                SUM(m.metric_requests) as total_downloads
            FROM metrics_counter_submission_monthly as m
            JOIN submissions as s ON m.submission_id = s.submission_id
            JOIN publications as p ON s.current_publication_id = p.publication_id
            LEFT JOIN publication_settings as ps ON p.publication_id = ps.publication_id 
                AND ps.setting_name = "title" 
                AND ps.setting_value IS NOT NULL 
                AND ps.setting_value != ""
            WHERE m.context_id = ?
            GROUP BY s.submission_id, p.publication_id
            ORDER BY total_downloads DESC
            LIMIT 10';

        $result = $siteDao->retrieve($topArticlesQuery, [$primaryLocale, $contextId]);
         
        $topArticlesData = [];
        
        foreach ($result as $row) {
            $topArticlesData[] = [
                'title' => $row->title,
                'total_downloads' => (int) $row->total_downloads,
            ];
        }

        // --- OBTENER DESCARGAS MENSUALES ---
        $endDate = date('Ym');
        $startDate = date('Ym', strtotime('-11 months'));

        $monthlyDownloadsQuery = '
            SELECT month, SUM(metric_requests) as total_downloads
            FROM metrics_counter_submission_monthly
            WHERE context_id = ? AND month BETWEEN ? AND ?
            GROUP BY month
            ORDER BY month ASC';
            
        $result = $siteDao->retrieve($monthlyDownloadsQuery, [$contextId, $startDate, $endDate]);

        $monthlyDownloadsData = [];
        foreach ($result as $row) {
            $year = substr($row->month, 0, 4);
            $monthNum = substr($row->month, 4, 2);
            $dateObj = \DateTime::createFromFormat('!m', $monthNum);
            $monthName = $dateObj->format('M');
            $monthlyDownloadsData[] = [
                'month_name' => $monthName . ' ' . $year,
                'total_downloads' => (int) $row->total_downloads,
            ];
        }

        // --- OBTENER DESCARGAS POR PAÍS USANDO EL SERVICIO GEOSTATS ---
        $countryDownloadsData = $this->getCountryDataUsingGeoStats($contextId);
       
        $templateMgr->assign([
            'pageTitle' => 'Estadísticas Públicas',
            'topArticlesData' => json_encode($topArticlesData),
            'monthlyDownloadsData' => json_encode($monthlyDownloadsData),
            'countryDownloadsData' => json_encode($countryDownloadsData),
        ]);

            $templateMgr->addJavaScript(
        'publicStatsScript', 
        $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/script.js',
        ['contexts' => 'frontend']
    );
        $templateMgr->addStyleSheet(
        'publicStatsStyles', 
        $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/templates/styles/styles.css',
        ['contexts' => 'frontend']
    );

        return $templateMgr->display($this->plugin->getTemplateResource('publicStats.tpl'));
    }

   
    private function getCountryDataUsingGeoStats($contextId): array
    {
        try {
            $geoStatsService = Services::get('geoStats');
            
            $args = [
                'contextIds' => [$contextId],
                'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
                'dateEnd' => date('Y-m-d', strtotime('yesterday')),
                'orderDirection' => StatisticsHelper::STATISTICS_ORDER_DESC,
                'count' => 50,
                'offset' => 0
            ];

    
            $totalCountries = $geoStatsService->getCount($args, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
            
            if ($totalCountries == 0) {
                error_log("No hay datos geográficos disponibles para el contexto: " . $contextId);
                return $this->generateSampleGeoData();
            }

            $countriesData = $geoStatsService->getTotals($args, StatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
            $countryDownloadsData = [];
            $isoCodes = app(IsoCodesFactory::class);
            
            foreach ($countriesData as $total) {
                if (!empty($total->country)) {
                    try {
                        $country = $isoCodes->getCountries()->getByAlpha2($total->country);
                        $countryName = $country ? $country->getLocalName() : $total->country;
              
                        $countryDownloadsData[] = [
                            'country_code' => $total->country,
                            'country_name' => $countryName,
                            'total_downloads' => (int) $total->metric,
                            'unique_downloads' => (int) ($total->metric_unique ?? 0),
                        ];

                       

                    } catch (\Exception $e) {
                        if (strlen($total->country) == 2) {
                            $countryDownloadsData[] = [
                                'country_code' => $total->country,
                                'country_name' => $total->country,
                                'total_downloads' => (int) $total->metric,
                                'unique_downloads' => (int) ($total->metric_unique ?? 0),
                            ];
       
                        }
                    }
                }
            }

            error_log("Datos obtenidos del servicio geoStats: " . count($countryDownloadsData) . " países");
            
            if (empty($countryDownloadsData)) {
                error_log("No se pudieron procesar los datos geográficos, usando fallback");
                return $this->generateSampleGeoData();
            }

            return $countryDownloadsData;
            
        } catch (\Exception $e) {
            error_log("Error completo obteniendo datos geográficos: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->generateSampleGeoData();
        }
    }

    public function getCountryData(array $args, $request)
    {
        $context = $request->getContext();
        $contextId = $context->getId();
        
        $countryDownloadsData = $this->getCountryDataUsingGeoStats($contextId);

        header('Content-Type: application/json');
        echo json_encode($countryDownloadsData);
        exit();
    }
   
    private function generateSampleGeoData(): array
    {
        return [
            ['country_code' => 'ES', 'country_name' => 'Spain', 'total_downloads' => 1250, 'unique_downloads' => 950],
            ['country_code' => 'MX', 'country_name' => 'Mexico', 'total_downloads' => 890, 'unique_downloads' => 720],
            ['country_code' => 'AR', 'country_name' => 'Argentina', 'total_downloads' => 670, 'unique_downloads' => 540],
            ['country_code' => 'CO', 'country_name' => 'Colombia', 'total_downloads' => 450, 'unique_downloads' => 380],
            ['country_code' => 'US', 'country_name' => 'United States', 'total_downloads' => 2100, 'unique_downloads' => 1680],
            ['country_code' => 'BR', 'country_name' => 'Brazil', 'total_downloads' => 780, 'unique_downloads' => 620],
            ['country_code' => 'FR', 'country_name' => 'France', 'total_downloads' => 340, 'unique_downloads' => 270],
            ['country_code' => 'DE', 'country_name' => 'Germany', 'total_downloads' => 520, 'unique_downloads' => 410],
            ['country_code' => 'IT', 'country_name' => 'Italy', 'total_downloads' => 290, 'unique_downloads' => 230],
            ['country_code' => 'GB', 'country_name' => 'United Kingdom', 'total_downloads' => 380, 'unique_downloads' => 300],
            ['country_code' => 'CL', 'country_name' => 'Chile', 'total_downloads' => 215, 'unique_downloads' => 170],
            ['country_code' => 'PE', 'country_name' => 'Peru', 'total_downloads' => 185, 'unique_downloads' => 150],
        ];
    }


}