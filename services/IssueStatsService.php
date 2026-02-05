<?php

/**
 * @file plugins/generic/publicStats/services/IssueStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for issue-level statistics.
 *
 * Aggregates download and view statistics at the issue level,
 * providing metrics useful for understanding issue popularity.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\services\BaseStatsService;
use APP\plugins\generic\publicStats\helpers\StatsAggregationHelper;

class IssueStatsService extends BaseStatsService
{
    /**
    * Get issue statistics
    */
    public function getIssueStats(
        PKPRequest $request,
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

        $issueStats = StatsAggregationHelper::aggregateByIssue(
            $downloadRecords,
            $viewRecords,
            $contextId
        );

        return $this->enrichWithUrls($issueStats, $request, $contextId);
    }

    private function enrichWithUrls(array $issueStats, PKPRequest $request, int $contextId): array
    {
        foreach ($issueStats as &$stats) {
            $issue = Repo::issue()->get($stats['issueId']);
            if ($issue) {
                $stats['urlPublished'] = $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'issue',
                    'view',
                    $issue->getBestIssueId()
                );
            }
        }
        return $issueStats;
    }


}