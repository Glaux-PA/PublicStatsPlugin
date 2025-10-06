<?php
namespace APP\plugins\generic\publicStats\classes;

use APP\core\Services;
use APP\statistics\StatisticsHelper;
use APP\core\Application;
use APP\facades\Repo;

class ArticleStatsRetriever
{
    public static function getTopDownloadedArticles($request, $contextId, $limit = 20, $dateStart = null, $dateEnd = null): array 
    {
        return self::getTopArticles($request, $contextId, 'downloads', $limit, $dateStart, $dateEnd);
    }

    public static function getTopViewedArticles($request, $contextId, $limit = 20, $dateStart = null, $dateEnd = null): array 
    {
        return self::getTopArticles($request, $contextId, 'views', $limit, $dateStart, $dateEnd);
    }

    public static function getRecentTopDownloadedArticles($request, $contextId, $limit = 20): array 
    {
        $dateEnd = date('Ymd', strtotime('yesterday'));
        $dateStart = date('Ymd', strtotime('-60 days'));
        return self::getTopArticles($request, $contextId, 'downloads', $limit, $dateStart, $dateEnd);
    }

    public static function getRecentTopViewedArticles($request, $contextId, $limit = 20): array 
    {
        $dateEnd = date('Ymd', strtotime('yesterday'));
        $dateStart = date('Ymd', strtotime('-60 days'));
        return self::getTopArticles($request, $contextId, 'views', $limit, $dateStart, $dateEnd);
    }

    private static function getTopArticles($request, $contextId, $metricType, $limit, $dateStart, $dateEnd): array 
    {
        $statsService = Services::get('publicationStats');
        $userGroups = Repo::userGroup()->getCollector()->filterByContextIds([$contextId])->getMany();
        $assocType = ($metricType === 'downloads') ? Application::ASSOC_TYPE_SUBMISSION_FILE : Application::ASSOC_TYPE_SUBMISSION;

        $params = [
            'contextIds' => [$contextId],
            'assocTypes' => [$assocType],
            'count' => $limit,
            'orderBy' => 'total',
            'orderDirection' => 'DESC'
        ];

        if ($dateStart) $params['dateStart'] = $dateStart;
        if ($dateEnd) $params['dateEnd'] = $dateEnd;

        $records = $statsService->getTotals($params);
        return self::formatArticleResults($records, $request, $contextId, $userGroups, $metricType, $limit);
    }

    private static function formatArticleResults($records, $request, $contextId, $userGroups, $metricType, $limit): array 
    {
        $results = [];
        foreach ($records as $record) {
            if (!isset($record->submission_id)) continue;
            $submission = Repo::submission()->get($record->submission_id);
            if (!$submission || $submission->getData('contextId') != $contextId) continue;
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $results[] = [
                'submissionId' => $submission->getId(),
                'title' => $publication->getLocalizedTitle(),
                'authors' => $publication->getAuthorString($userGroups),
                $metricType => $record->metric ?? 0,
                'datePublished' => $publication->getData('datePublished'),
                'urlPublished' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'article', 'view', $submission->getBestId())
            ];

            if (count($results) >= $limit) break;
        }
        return $results;
    }
}