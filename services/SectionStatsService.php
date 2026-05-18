<?php

/**
 * @file plugins/generic/publicStats/services/SectionStatsService.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for journal section statistics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\publicStats\services\BaseStatsService;
use APP\plugins\generic\publicStats\helpers\StatsAggregationHelper;

class SectionStatsService extends BaseStatsService
{
    public function getSectionStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $statsService = Services::get('publicationStats');
        $dateRange = $this->getDateRange($dateStart, $dateEnd);

        $baseParams = [
            'contextIds' => [$contextId],
            'dateStart' => $dateRange['start'],
            'dateEnd' => $dateRange['end']
        ];

        $downloadRecords = $statsService->getTotals(
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );

        $viewRecords = $statsService->getTotals(
            array_merge($baseParams, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );

        return StatsAggregationHelper::aggregateBySection(
            $downloadRecords,
            $viewRecords,
            $contextId
        );
    }
    
}