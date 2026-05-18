<?php

/**
 * @file plugins/generic/publicStats/services/ArticleStatsService.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for article-level statistics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;

class ArticleStatsService
{
    public function getTopDownloadedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        return $this->getTopArticles(
            $request,
            $contextId,
            'downloads',
            $limit,
            $dateStart,
            $dateEnd
        );
    }

    public function getTopViewedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        return $this->getTopArticles(
            $request,
            $contextId,
            'views',
            $limit,
            $dateStart,
            $dateEnd
        );
    }

    public function getRecentTopDownloadedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20
    ): array {
        $dateEnd = date('Ymd', strtotime('yesterday'));
        $dateStart = date('Ymd', strtotime('-60 days'));

        return $this->getTopDownloadedArticles(
            $request,
            $contextId,
            $limit,
            $dateStart,
            $dateEnd
        );
    }

    public function getRecentTopViewedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20
    ): array {
        $dateEnd = date('Ymd', strtotime('yesterday'));
        $dateStart = date('Ymd', strtotime('-60 days'));

        return $this->getTopViewedArticles(
            $request,
            $contextId,
            $limit,
            $dateStart,
            $dateEnd
        );
    }

    private function getTopArticles(
        PKPRequest $request,
        int $contextId,
        string $metricType,
        int $limit,
        ?string $dateStart,
        ?string $dateEnd
    ): array {
        $statsService = Services::get('publicationStats');

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        $assocType = ($metricType === 'downloads')
            ? Application::ASSOC_TYPE_SUBMISSION_FILE
            : Application::ASSOC_TYPE_SUBMISSION;

        $params = [
            'contextIds' => [$contextId],
            'assocTypes' => [$assocType],
            'count' => $limit,
            'orderBy' => 'total',
            'orderDirection' => 'DESC'
        ];

        if ($dateStart) {
            $params['dateStart'] = $dateStart;
        }

        if ($dateEnd) {
            $params['dateEnd'] = $dateEnd;
        }

        $records = $statsService->getTotals($params);

        return $this->formatArticleResults(
            $records,
            $request,
            $contextId,
            $userGroups,
            $metricType,
            $limit
        );
    }

    private function formatArticleResults(
        iterable $records,
        PKPRequest $request,
        int $contextId,
        iterable $userGroups,
        string $metricType,
        int $limit
    ): array {
        $orderedIds = [];
        $metricById = [];
        foreach ($records as $record) {
            if (!isset($record->submission_id)) {
                continue;
            }
            $id = (int) $record->submission_id;
            if (!isset($metricById[$id])) {
                $orderedIds[] = $id;
            }
            $metricById[$id] = $record->metric ?? 0;
        }

        if (empty($orderedIds)) {
            return [];
        }

        $collector = Repo::submission()->getCollector()->filterByContextIds([$contextId]);
        $rows = $collector->getQueryBuilder()
            ->whereIn('s.submission_id', $orderedIds)
            ->get();

        $submissionsById = [];
        foreach ($rows as $row) {
            $submission = Repo::submission()->dao->fromRow($row);
            $submissionsById[$submission->getId()] = $submission;
        }

        $dispatcher = $request->getDispatcher();
        $results = [];
        foreach ($orderedIds as $id) {
            $submission = $submissionsById[$id] ?? null;
            if (!$submission) {
                continue;
            }

            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }

            $results[] = [
                'submissionId' => $submission->getId(),
                'title' => $publication->getLocalizedTitle(),
                'authors' => $publication->getAuthorString($userGroups),
                $metricType => $metricById[$id],
                'datePublished' => $publication->getData('datePublished'),
                'urlPublished' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'article',
                    'view',
                    $submission->getBestId()
                ),
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
