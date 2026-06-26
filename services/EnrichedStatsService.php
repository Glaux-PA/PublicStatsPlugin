<?php

/**
 * @file plugins/generic/publicStats/services/EnrichedStatsService.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnrichedStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for combining local statistics with OpenAlex data.
 *
 * Drives the Impact subsections (top-cited, citation evolution, OA, thematic
 * profile, citing journals/institutions, citations-by-country) and the
 * aggregate context-level counters. Heavy aggregations run as chunked jobs
 * and return `is_computing` placeholders until the worker finishes.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use PKP\submission\PKPSubmission;
use PKP\userGroup\UserGroup;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\jobs\ComputeOpenAlexAggregateJob;
use APP\plugins\generic\publicStats\services\OpenAlexService;
use APP\plugins\generic\publicStats\services\ArticleStatsService;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\services\BaseStatsService;
class EnrichedStatsService extends BaseStatsService
{
    private OpenAlexService $openAlexService;
    private ArticleStatsService $articleStatsService;

    public function __construct(
        OpenAlexService $openAlexService,
        ArticleStatsService $articleStatsService
    ) {
        $this->openAlexService = $openAlexService;
        $this->articleStatsService = $articleStatsService;
    }

    /**
     * Top articles enriched with OpenAlex metrics.
     *
     * @param string $metricType 'downloads', 'views', or 'citations'.
     *                           For 'citations' the call is delegated to
     *                           getTopCitedArticles, which scans the whole
     *                           journal instead of starting from the top
     *                           downloaded subset.
     */
    public function getEnrichedTopArticles(
        PKPRequest $request,
        int $contextId,
        string $metricType = 'downloads',
        int $limit = 20,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        if ($metricType === 'citations') {
            $response = $this->getTopCitedArticles($request, $contextId, $limit);
            return $response['articles'] ?? [];
        }

        $baseArticles = $this->getBaseTopArticles(
            $request,
            $contextId,
            $metricType,
            $limit,
            $dateStart,
            $dateEnd
        );

        foreach ($baseArticles as &$article) {
            $this->enrichArticleWithExternalData($article);
        }

        return $baseArticles;
    }

    public function getEnrichedContextStats(int $contextId): array
    {
        $localStats = $this->getBaseContextStats($contextId);
        $externalStats = $this->getExternalEnrichmentStats($contextId);

        // Combined metrics depend on external keys that don't exist yet while computing.
        if (!empty($externalStats['is_computing'])) {
            return [
                'local'    => $localStats,
                'external' => $externalStats,
                'combined' => ['is_computing' => true],
            ];
        }

        $combined = $this->combineContextStats($localStats, $externalStats);

        return [
            'local'    => $localStats,
            'external' => $externalStats,
            'combined' => $combined,
        ];
    }

    /**
     * Returns either { __complete__: true, accumulator } when ready, or a
     * computing placeholder. Kicks off (or resumes) the chunk chain as needed.
     */
    private function readOrAdvanceChunked(string $type, int $contextId): array
    {
        $state = $this->openAlexService->getChunkedState($type, $contextId);

        if ($state === null) {
            $this->openAlexService->dispatchChunkJob($type, $contextId);
            return OpenAlexService::chunkedPlaceholder(null);
        }

        if (empty($state['is_complete'])) {
            $this->openAlexService->dispatchChunkJob($type, $contextId);
            return OpenAlexService::chunkedPlaceholder($state);
        }

        // Stale state from an older schema (or a partial write). Restart.
        if (!is_array($state['accumulator'] ?? null)) {
            $this->openAlexService->forgetChunkedState($type, $contextId);
            $this->openAlexService->dispatchChunkJob($type, $contextId);
            return OpenAlexService::chunkedPlaceholder(null);
        }

        return ['__complete__' => true, 'accumulator' => $state['accumulator']];
    }

    /**
     * Pin the list of submission IDs with a DOI on the first call, then return
     * a window of Submission objects for the current offset.
     */
    private function loadNextChunk(int $contextId, array $state): array
    {
        if (!isset($state['submission_ids'])) {
            $ids = [];
            foreach ($this->getPublishedSubmissions($contextId) as $submission) {
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                if (!$publication->getDoi()) continue;
                $ids[] = (int) $submission->getId();
            }
            $state['submission_ids'] = $ids;
            $state['total'] = count($ids);
            $state['processed'] = 0;
        }

        $offset = (int) ($state['processed'] ?? 0);
        $chunkIds = array_slice(
            $state['submission_ids'],
            $offset,
            PublicStatsConstants::MAX_INLINE_JOB_SUBMISSIONS
        );

        // Advance by the slice size, not by the loaded count: if a submission
        // got deleted in between, skip its slot instead of reprocessing.
        $state['processed'] = $offset + count($chunkIds);
        $state['is_complete'] = $state['processed'] >= (int) ($state['total'] ?? 0);

        if (empty($chunkIds)) {
            return ['submissions' => [], 'state' => $state];
        }

        $rows = Repo::submission()->getCollector()
            ->filterByContextIds([$contextId])
            ->getQueryBuilder()
            ->whereIn('s.submission_id', $chunkIds)
            ->get();

        $byId = [];
        foreach ($rows as $row) {
            $submission = Repo::submission()->dao->fromRow($row);
            $byId[(int) $submission->getId()] = $submission;
        }

        // Preserve submission_ids order for deterministic chunks.
        $submissions = [];
        foreach ($chunkIds as $id) {
            if (isset($byId[$id])) {
                $submissions[] = $byId[$id];
            }
        }

        return ['submissions' => $submissions, 'state' => $state];
    }

    private function getBaseTopArticles(
        PKPRequest $request,
        int $contextId,
        string $metricType,
        int $limit,
        ?string $dateStart,
        ?string $dateEnd
    ): array {
        if ($metricType === 'views') {
            return $this->articleStatsService->getTopViewedArticles(
                $request,
                $contextId,
                $limit,
                $dateStart,
                $dateEnd
            );
        }

        return $this->articleStatsService->getTopDownloadedArticles(
            $request,
            $contextId,
            $limit,
            $dateStart,
            $dateEnd
        );
    }

    private function enrichArticleWithExternalData(array &$article): void
    {
        $submission = Repo::submission()->get($article['submissionId']);
        if (!$submission) {
            $this->addEmptyExternalMetrics($article);
            return;
        }

        $publication = $submission->getCurrentPublication();
        if (!$publication) {
            $this->addEmptyExternalMetrics($article);
            return;
        }

        $doi = $publication->getDoi();
        if (!$doi) {
            $this->addEmptyExternalMetrics($article);
            return;
        }

        $externalMetrics = $this->openAlexService->getWorkMetrics($doi);

        if ($externalMetrics) {
            $article['citations'] = $externalMetrics['cited_by_count'] ?? 0;
            $article['fwci'] = $externalMetrics['fwci'];
            $article['is_oa'] = $externalMetrics['is_oa'] ?? false;
            $article['oa_status'] = $externalMetrics['oa_status'] ?? 'closed';
            $article['has_external_data'] = true;
        } else {
            $this->addEmptyExternalMetrics($article);
        }

        $article['doi'] = $doi;
    }

    private function addEmptyExternalMetrics(array &$article): void
    {
        $article['citations'] = 0;
        $article['fwci'] = null;
        $article['is_oa'] = false;
        $article['oa_status'] = null;
        $article['has_external_data'] = false;
        $article['doi'] = null;
    }

    private function getBaseContextStats(int $contextId): array
    {
        $totalArticles = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getCount();

        return [
            'total_articles' => $totalArticles,
        ];
    }

    private function combineContextStats(array $local, array $external): array
    {
        $totalArticles = $local['total_articles'];
        $worksWithData = $external['works_with_data'];
        
        $enrichmentRate = $totalArticles > 0 
            ? round(($worksWithData / $totalArticles) * 100, 1) 
            : 0;
        
        $avgCitationsPerArticle = $worksWithData > 0
            ? round($external['total_external_citations'] / $worksWithData, 2)
            : 0;

        return [
            'total_external_citations' => $external['total_external_citations'],
            'avg_fwci' => $external['avg_fwci'],
            'enrichment_rate' => $enrichmentRate,
            'articles_with_external_data' => $worksWithData,
            'avg_citations_per_article' => $avgCitationsPerArticle,
            'funded_works_count' => $external['funded_works'],
            'funded_works_percentage' => $totalArticles > 0 
                ? round(($external['funded_works'] / $totalArticles) * 100, 1) 
                : 0,
        ];
    }


    /**
     * Top cited articles. The job builds a top-100 list once across the
     * journal; this wrapper applies the year filter and slices to $limit.
     */
    public function getTopCitedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20,
        ?string $year = null
    ): array {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_TOP_CITED,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        $articles = $result['accumulator']['articles'] ?? [];

        if ($year !== null) {
            $filtered = [];
            foreach ($articles as $article) {
                $countsByYear = $article['counts_by_year'] ?? [];
                $yearCitations = 0;
                foreach ($countsByYear as $yearData) {
                    if (isset($yearData['year']) && (string)$yearData['year'] === $year) {
                        $yearCitations = (int)($yearData['cited_by_count'] ?? 0);
                        break;
                    }
                }
                if ($yearCitations > 0) {
                    $article['citations'] = $yearCitations;
                    $filtered[] = $article;
                }
            }
            usort($filtered, fn($a, $b) => $b['citations'] - $a['citations']);
            $articles = $filtered;
        }

        // The job has no PKPRequest, so URLs are built here.
        $dispatcher = $request->getDispatcher();
        $sliced = array_slice($articles, 0, $limit);
        foreach ($sliced as &$article) {
            $bestId = $article['bestId'] ?? $article['submissionId'] ?? null;
            if ($bestId !== null) {
                $article['urlPublished'] = $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'article',
                    'view',
                    [$bestId]
                );
            }
            unset($article['counts_by_year'], $article['bestId']);
        }
        unset($article);

        return [
            'articles' => $sliced,
            'is_computing' => false,
        ];
    }

    public function getTopCitedArticlesChunk(int $contextId, array $state): array
    {
        // Skip re-runs over already finalized state (retry, race).
        if (!empty($state['is_complete']) && isset($state['accumulator']['articles'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? ['articles' => []];

        if (!empty($chunk['submissions'])) {
            $userGroups = UserGroup::withContextIds([$contextId])->get();

            foreach ($chunk['submissions'] as $submission) {
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                $doi = $publication->getDoi();
                if (!$doi) continue;

                $metrics = $this->openAlexService->getWorkMetrics($doi);
                if (!$metrics) continue;

                $totalCitations = (int)($metrics['cited_by_count'] ?? 0);
                if ($totalCitations === 0) continue;

                $datePublished = $publication->getData('datePublished');
                $accumulator['articles'][] = [
                    'submissionId' => $submission->getId(),
                    'bestId' => $publication->getData('urlPath') ?: $submission->getId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => $datePublished ? date('Y', strtotime($datePublished)) : null,
                    'citations' => $totalCitations,
                    'counts_by_year' => $metrics['counts_by_year'] ?? [],
                ];
            }
        }

        $state['accumulator'] = $accumulator;

        if (!empty($state['is_complete'])) {
            usort($state['accumulator']['articles'], fn($a, $b) => $b['citations'] - $a['citations']);
            $state['accumulator']['articles'] = array_slice($state['accumulator']['articles'], 0, 100);
        }

        return $state;
    }
    /**
     * Citations aggregated per year across the journal. The wrapper filters
     * to $minYear at request time over the full cached series.
     */
    public function getCitationEvolution(int $contextId, int $minYear = 2015): array
    {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_CITATION_EVOLUTION,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        $allYears = $result['accumulator']['data'] ?? [];
        $filtered = array_values(array_filter(
            $allYears,
            fn($entry) => (int)($entry['year'] ?? 0) >= $minYear
        ));

        return [
            'data' => $filtered,
            'is_computing' => false,
        ];
    }

    public function getCitationEvolutionChunk(int $contextId, array $state): array
    {
        if (!empty($state['is_complete']) && isset($state['accumulator']['data'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? ['years_map' => []];

        foreach ($chunk['submissions'] as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $doi = $publication->getDoi();
            if (!$doi) continue;

            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics || empty($metrics['counts_by_year'])) continue;

            foreach ($metrics['counts_by_year'] as $yearData) {
                $year = (int)($yearData['year'] ?? 0);
                if ($year <= 0) continue;
                $accumulator['years_map'][$year] = ($accumulator['years_map'][$year] ?? 0)
                    + (int)($yearData['cited_by_count'] ?? 0);
            }
        }

        $state['accumulator'] = $accumulator;

        if (!empty($state['is_complete'])) {
            $yearsMap = $state['accumulator']['years_map'];
            ksort($yearsMap);
            $data = [];
            foreach ($yearsMap as $year => $count) {
                $data[] = ['year' => (string) $year, 'citations' => $count];
            }
            $state['accumulator']['data'] = $data;
            unset($state['accumulator']['years_map']);
        }

        return $state;
    }


    public function getOpenAccessStats(int $contextId): array
    {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_OPEN_ACCESS_STATS,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        return array_merge($result['accumulator'], ['is_computing' => false]);
    }

    public function getOpenAccessStatsChunk(int $contextId, array $state): array
    {
        if (!empty($state['is_complete']) && isset($state['accumulator']['total'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? [
            'total' => 0,
            'open_access' => 0,
            'by_type' => [
                'diamond' => 0,
                'gold' => 0,
                'hybrid' => 0,
                'green' => 0,
                'bronze' => 0,
                'closed' => 0,
                'unknown' => 0,
            ],
        ];

        foreach ($chunk['submissions'] as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $doi = $publication->getDoi();
            if (!$doi) continue;

            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics) continue;

            $accumulator['total']++;
            if ($metrics['is_oa']) {
                $accumulator['open_access']++;
            }

            $oaStatus = $metrics['oa_status'] ?? 'closed';
            if (isset($accumulator['by_type'][$oaStatus])) {
                $accumulator['by_type'][$oaStatus]++;
            } else {
                $accumulator['by_type']['closed']++;
            }
        }

        $state['accumulator'] = $accumulator;
        return $state;
    }

    public function getThematicProfile(int $contextId): array
    {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_THEMATIC_PROFILE,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        return array_merge($result['accumulator'], ['is_computing' => false]);
    }

    public function getThematicProfileChunk(int $contextId, array $state): array
    {
        if (!empty($state['is_complete']) && isset($state['accumulator']['topics'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? [
            'topics_count' => [],
            'total_articles' => 0,
        ];

        foreach ($chunk['submissions'] as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $doi = $publication->getDoi();
            if (!$doi) continue;

            $accumulator['total_articles']++;

            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics || empty($metrics['topics'])) continue;

            foreach ($metrics['topics'] as $topic) {
                $name = $topic['display_name'] ?? 'Unknown';
                $accumulator['topics_count'][$name] = ($accumulator['topics_count'][$name] ?? 0) + 1;
                break; // primary topic only
            }
        }

        $state['accumulator'] = $accumulator;

        if (!empty($state['is_complete'])) {
            $topicsCount = $state['accumulator']['topics_count'];
            arsort($topicsCount);
            $topics = [];
            foreach ($topicsCount as $name => $count) {
                $topics[] = ['name' => $name, 'count' => $count];
            }
            $state['accumulator']['topics'] = $topics;
            unset($state['accumulator']['topics_count']);
        }

        return $state;
    }

    public function getExternalEnrichmentStats(int $contextId): array
    {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_ENRICH_CONTEXT,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        return array_merge($result['accumulator'], ['is_computing' => false]);
    }

    public function enrichContextStatisticsChunk(int $contextId, array $state): array
    {
        if (!empty($state['is_complete']) && isset($state['accumulator']['total_external_citations'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? [
            'total_external_citations' => 0,
            'fwci_sum'                 => 0.0,
            'fwci_count'               => 0,
            'works_with_data'          => 0,
            'topics_count'             => [],
            'sdgs_count'               => [],
            'retracted_count'          => 0,
            'funded_works'             => 0,
        ];

        foreach ($chunk['submissions'] as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $doi = $publication->getDoi();
            if (!$doi) continue;

            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics) continue;

            $accumulator['works_with_data']++;
            $accumulator['total_external_citations'] += (int)($metrics['cited_by_count'] ?? 0);

            // 0.0 is a valid FWCI; only null means OpenAlex hasn't computed it.
            if (isset($metrics['fwci']) && $metrics['fwci'] !== null) {
                $accumulator['fwci_sum'] += (float)$metrics['fwci'];
                $accumulator['fwci_count']++;
            }
            if (!empty($metrics['is_retracted'])) {
                $accumulator['retracted_count']++;
            }
            if (!empty($metrics['grants'])) {
                $accumulator['funded_works']++;
            }
            foreach ($metrics['topics'] ?? [] as $topic) {
                $name = $topic['display_name'] ?? 'Unknown';
                $accumulator['topics_count'][$name] = ($accumulator['topics_count'][$name] ?? 0) + 1;
            }
            foreach ($metrics['sustainable_development_goals'] ?? [] as $sdg) {
                $name = $sdg['display_name'] ?? 'Unknown';
                $accumulator['sdgs_count'][$name] = ($accumulator['sdgs_count'][$name] ?? 0) + 1;
            }
        }

        $state['accumulator'] = $accumulator;

        if (!empty($state['is_complete'])) {
            $acc = $state['accumulator'];
            arsort($acc['topics_count']);
            arsort($acc['sdgs_count']);
            $state['accumulator'] = [
                'total_external_citations' => $acc['total_external_citations'],
                'avg_fwci'                 => ($acc['fwci_count'] ?? 0) > 0
                    ? round($acc['fwci_sum'] / $acc['fwci_count'], 2)
                    : 0,
                'works_with_data'          => $acc['works_with_data'],
                'top_topics'               => array_slice($acc['topics_count'], 0, 10, true),
                'top_sdgs'                 => array_slice($acc['sdgs_count'], 0, 10, true),
                'retracted_count'          => $acc['retracted_count'],
                'funded_works'             => $acc['funded_works'],
                'processed_count'          => $state['processed'] ?? 0,
                'total_submissions'        => $state['total'] ?? 0,
                'is_partial'               => false,
            ];
        }

        return $state;
    }

    /**
     * Citations-by-country (chunked). Returns the formatted array once the
     * chunked job is complete, or an `is_computing` placeholder while the
     * queue worker is still walking the journal's submissions.
     */
    public function getCitationsByCountry(int $contextId): array
    {
        $result = $this->readOrAdvanceChunked(
            ComputeOpenAlexAggregateJob::TYPE_CITATIONS_BY_COUNTRY,
            $contextId
        );
        if (empty($result['__complete__'])) {
            return $result;
        }
        return $result['accumulator']['data'] ?? [];
    }

    public function getCitationsByCountryChunk(int $contextId, array $state): array
    {
        if (!empty($state['is_complete']) && isset($state['accumulator']['data'])) {
            return $state;
        }

        $chunk = $this->loadNextChunk($contextId, $state);
        $state = $chunk['state'];

        $accumulator = $state['accumulator'] ?? ['country_counts' => []];

        foreach ($chunk['submissions'] as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $doi = $publication->getDoi();
            if (!$doi) continue;

            $work = $this->openAlexService->getWorkByDOI($doi);
            if (!$work || empty($work['id'])) continue;

            $citingWorks = $this->openAlexService->getCitingWorks($work['id']);

            foreach ($citingWorks as $citingWork) {
                if (empty($citingWork['authorships'])) continue;

                // One country per citing work: first institution of the first author.
                foreach ($citingWork['authorships'] as $authorship) {
                    if (empty($authorship['institutions'])) continue;
                    foreach ($authorship['institutions'] as $institution) {
                        $countryCode = $institution['country_code'] ?? null;
                        if ($countryCode) {
                            $accumulator['country_counts'][$countryCode] =
                                ($accumulator['country_counts'][$countryCode] ?? 0) + 1;
                            break;
                        }
                    }
                    break;
                }
            }
        }

        $state['accumulator'] = $accumulator;

        if (!empty($state['is_complete'])) {
            $isoCodes  = app(\Sokil\IsoCodes\IsoCodesFactory::class);
            $formatted = [];
            foreach ($accumulator['country_counts'] as $countryCode => $count) {
                $countryCode = (string) $countryCode;
                if (strlen($countryCode) !== 2) continue;
                try {
                    $country = $isoCodes->getCountries()->getByAlpha2($countryCode);
                    $countryName = $country ? $country->getLocalName() : $countryCode;
                } catch (\Exception $e) {
                    $countryName = $countryCode;
                }
                $formatted[] = [
                    'country_code'    => $countryCode,
                    'country_name'    => $countryName,
                    'citations_count' => $count,
                ];
            }
            usort($formatted, fn($a, $b) => $b['citations_count'] - $a['citations_count']);
            $state['accumulator']['data'] = $formatted;
            unset($state['accumulator']['country_counts']);
        }

        return $state;
    }

    public function getCitingJournals(PKPRequest $request, int $contextId): ?array
    {
        try {
            $journals = $this->openAlexService->getCitingJournals($contextId);

            if ($journals === null) {
                return ['is_computing' => true];
            }

            if (empty($journals)) {
                return null;
            }
            
            $formattedData = [];
            $allYears = [];
            
            foreach ($journals as $journal) {
                $citedArticles = [];
                foreach ($journal['cited_articles'] ?? [] as $article) {
                    $citationYear = $article['citation_year'] ?? null;
                    if ($citationYear) {
                        $allYears[$citationYear] = true;
                    }
                    
                    $citedArticles[] = [
                        'id' => $article['id'],
                        'title' => $article['title'],
                        'authors' => $article['authors'],
                        'year' => $article['year'],
                        'citation_year' => $citationYear,
                        'times_cited' => $article['times_cited'],
                        'url' => $request->getDispatcher()->url(
                            $request,
                            Application::ROUTE_PAGE,
                            null,
                            'article',
                            'view',
                            [$article['bestId']]
                        )
                    ];
                }
                
                $formattedData[] = [
                    'id' => $journal['id'],
                    'name' => $journal['name'],
                    'issn' => $journal['issn'],
                    'type' => $journal['type'],
                    'host_organization' => $journal['host_organization'],
                    'citations' => $journal['citations'],
                    'citations_by_year' => $journal['citations_by_year'] ?? [],
                    'cited_articles' => $citedArticles
                ];
            }
            
            $sortedYears = array_keys($allYears);
            rsort($sortedYears);
            
            return [
                'journals' => $formattedData,
                'available_years' => $sortedYears
            ];
            
        } catch (\Exception $e) {
            Logger::error("Error getting citing journals", $e);
            return null;
        }
    }

    public function getCitingInstitutions(PKPRequest $request, int $contextId): ?array
    {
        try {
            $institutions = $this->openAlexService->getCitingInstitutions($contextId);

            if ($institutions === null) {
                return ['is_computing' => true];
            }

            if (empty($institutions)) {
                return null;
            }
            
            $isoCodes = app(\Sokil\IsoCodes\IsoCodesFactory::class);
            $formattedData = [];
            $allYears = [];
            
            foreach ($institutions as $institution) {
                $countryCode = $institution['country_code'] ?? null;
                $countryName = null;
                
                if ($countryCode && strlen($countryCode) === 2) {
                    try {
                        $country = $isoCodes->getCountries()->getByAlpha2($countryCode);
                        $countryName = $country ? $country->getLocalName() : $countryCode;
                    } catch (\Exception $e) {
                        $countryName = $countryCode;
                    }
                }
                
                $citedArticles = [];
                foreach ($institution['cited_articles'] ?? [] as $article) {
                    $citationYear = $article['citation_year'] ?? null;
                    if ($citationYear) {
                        $allYears[$citationYear] = true;
                    }
                    
                    $citedArticles[] = [
                        'id' => $article['id'],
                        'title' => $article['title'],
                        'authors' => $article['authors'],
                        'year' => $article['year'],
                        'citation_year' => $citationYear,
                        'times_cited' => $article['times_cited'],
                        'url' => $request->getDispatcher()->url(
                            $request,
                            Application::ROUTE_PAGE,
                            null,
                            'article',
                            'view',
                            [$article['bestId']]
                        )
                    ];
                }
                
                $formattedData[] = [
                    'id' => $institution['id'],
                    'name' => $institution['name'],
                    'country_code' => $countryCode,
                    'country_name' => $countryName,
                    'type' => $institution['type'],
                    'ror' => $institution['ror'],
                    'citations' => $institution['citations'],
                    'citations_by_year' => $institution['citations_by_year'] ?? [],
                    'cited_articles' => $citedArticles
                ];
            }
            
            $sortedYears = array_keys($allYears);
            rsort($sortedYears);
            
            return [
                'institutions' => $formattedData,
                'available_years' => $sortedYears
            ];
            
        } catch (\Exception $e) {
            Logger::error("Error getting citing institutions", $e);
            return null;
        }
    }
}