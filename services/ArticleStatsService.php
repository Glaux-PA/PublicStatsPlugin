<?php

/**
 * @file plugins/generic/publicStats/services/ArticleStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for article-level statistics.
 *
 * Provides methods to retrieve top downloaded and viewed articles,
 * including both all-time rankings and recent (60-day) rankings.
 * Results include article metadata and direct links to publications.
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
    /**
     * Get top downloaded articles.
     *
     * Returns articles ranked by download count within the specified date range.
     *
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param int $limit Maximum number of articles to return
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Array of article data with download counts
     */
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

    /**
     * Get top viewed articles.
     *
     * Returns articles ranked by abstract page view count within the specified date range.
     *
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param int $limit Maximum number of articles to return
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Array of article data with view counts
     */
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

    /**
     * Get recent top downloaded articles.
     *
     * Returns articles ranked by downloads in the last 60 days.
     * Useful for highlighting currently popular content.
     *
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param int $limit Maximum number of articles to return
     * @return array Array of article data with recent download counts
     */
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

    /**
     * Get recent top viewed articles.
     *
     * Returns articles ranked by views in the last 60 days.
     *
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param int $limit Maximum number of articles to return
     * @return array Array of article data with recent view counts
     */
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

    /**
     * Get top articles by metric type.
     *
     * Internal method that retrieves ranked articles based on the specified metric.
     *
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param string $metricType Either 'downloads' or 'views'
     * @param int $limit Maximum results
     * @param string|null $dateStart Start date
     * @param string|null $dateEnd End date
     * @return array Array of formatted article results
     */
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

        // Select appropriate association type based on metric
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

    /**
     * Format article results with metadata.
     *
     * Enriches raw statistics records with article title, authors,
     * publication date, and URL.
     *
     * @param iterable $records Raw statistics records
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @param iterable $userGroups User groups for author string formatting
     * @param string $metricType Type of metric (downloads/views)
     * @param int $limit Maximum results
     * @return array Formatted article data array
     */
    private function formatArticleResults(
        iterable $records,
        PKPRequest $request,
        int $contextId,
        iterable $userGroups,
        string $metricType,
        int $limit
    ): array {
        // Collect submission ids preserving record order and metric mapping.
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

        // Batch-load all submissions in a single query instead of one per record.
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
