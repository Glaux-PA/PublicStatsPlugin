<?php

/**
 * @file plugins/generic/publicStats/services/OpenAlexService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenAlexService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for OpenAlex API integration.
 *
 * Handles all communication with the OpenAlex API to retrieve
 * citation metrics, topics, funding information, and other
 * bibliometric data. Implements rate limiting and caching
 * to respect API limits.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\jobs\ComputeOpenAlexAggregateJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PKP\submission\PKPSubmission;

class OpenAlexService
{
    private const API_BASE = 'https://api.openalex.org';
    private ?string $contactEmail;

    public function __construct(?string $contactEmail = null)
    {
        $this->contactEmail = $contactEmail ?? $this->getContactEmail();
    }

    private function getContactEmail(): ?string
    {
        try {
            $request = Application::get()->getRequest();
            $context = $request?->getContext();

            if (!$context) {
                return null;
            }

            $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'publicstatsplugin');

            $email = $plugin?->getSetting($context->getId(), 'openAlexEmail')
                ?: $context->getData('contactEmail');

            return $email ?: null;
        } catch (\Exception $e) {
            Logger::error('OpenAlex: could not resolve contact email', $e);
            return null;
        }
    }

    /**
     * Perform a GET against the OpenAlex API with retries and exponential backoff.
     *
     * Retries on transient failures (network errors, 429, 5xx). Returns the
     * decoded JSON body on success, or null when all attempts fail. Each
     * non-final failure is logged as a warning so operators can see the
     * cause without the request itself failing.
     *
     * @param string $url     Full request URL (without query params).
     * @param array  $query   Query parameters to append.
     * @param int    $timeout Per-attempt timeout, in seconds.
     * @param string $context Short tag used in log lines (e.g. "DOI 10.x/y").
     * @return array|null     Decoded JSON body, or null on permanent failure.
     */
    private function httpGetWithRetry(
        string $url,
        array $query = [],
        int $timeout = 10,
        string $context = ''
    ): ?array {
        $attempts = 3;
        $backoffMs = [250, 1000, 2000]; // exponential-ish: 250ms, 1s, 2s

        // Polite-pool routing requires `mailto` as a query param; headers are ignored.
        if ($this->contactEmail && !isset($query['mailto'])) {
            $query['mailto'] = $this->contactEmail;
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->get($url, $query);

                $status = $response->status();

                if ($response->successful()) {
                    return $response->json();
                }

                // 4xx (other than 429) are permanent — don't retry.
                $isTransient = $status === 429 || $status >= 500;
                if (!$isTransient) {
                    Logger::error("OpenAlex {$context}: non-retryable HTTP {$status}");
                    return null;
                }

                if ($attempt < $attempts) {
                    Logger::warning("OpenAlex {$context}: transient HTTP {$status}, retrying (attempt {$attempt}/{$attempts})");
                    usleep($backoffMs[$attempt - 1] * 1000);
                    continue;
                }

                Logger::error("OpenAlex {$context}: HTTP {$status} after {$attempts} attempts");
                return null;
            } catch (\Throwable $e) {
                if ($attempt < $attempts) {
                    Logger::warning("OpenAlex {$context}: network error, retrying (attempt {$attempt}/{$attempts})", $e);
                    usleep($backoffMs[$attempt - 1] * 1000);
                    continue;
                }

                Logger::error("OpenAlex {$context}: failed after {$attempts} attempts", $e);
                return null;
            }
        }

        return null;
    }

    /**
     * Cache key that holds the computed aggregate for a given type/context.
     */
    public static function cacheKeyFor(string $type, int $contextId): string
    {
        return "openalex_aggregate_{$type}_{$contextId}";
    }

    /**
     * Cache key used as a "currently computing" lock to avoid enqueueing
     * the same heavy job more than once at a time.
     */
    public static function lockKeyFor(string $type, int $contextId): string
    {
        return self::cacheKeyFor($type, $contextId) . '_lock';
    }

    /**
     * TTL for computed aggregates. Exposed as a helper so the job and the
     * service share a single source of truth.
     */
    public static function aggregateCacheTtl(): int
    {
        return PublicStatsConstants::CACHE_TTL_EXTERNAL;
    }

    /**
     * Return cached aggregate data if present; otherwise enqueue the
     * background job (respecting a short lock so we don't stack duplicate
     * jobs) and return the supplied placeholder. The first request returns
     * the placeholder, the worker populates the cache, subsequent requests
     * get the real data.
     */
    public function cachedOrEnqueue(string $type, int $contextId, mixed $placeholder): mixed
    {
        $cacheKey = self::cacheKeyFor($type, $contextId);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Cache::add wins only once, so subsequent concurrent calls skip the dispatch.
        $lockKey = self::lockKeyFor($type, $contextId);
        $lockTtl = max(120, (int) (PublicStatsConstants::CACHE_TTL_EXTERNAL / 24));
        if (Cache::add($lockKey, 1, $lockTtl)) {
            ComputeOpenAlexAggregateJob::dispatch($contextId, $type);
        }

        return $placeholder;
    }

    // Chunked state shape: { processed, total, accumulator, is_complete }.
    public function getChunkedState(string $type, int $contextId): ?array
    {
        return Cache::get(self::cacheKeyFor($type, $contextId));
    }

    public function putChunkedState(string $type, int $contextId, array $state): void
    {
        Cache::put(
            self::cacheKeyFor($type, $contextId),
            $state,
            self::aggregateCacheTtl()
        );
    }

    public function forgetChunkedState(string $type, int $contextId): void
    {
        Cache::forget(self::cacheKeyFor($type, $contextId));
        Cache::forget(self::lockKeyFor($type, $contextId));
    }

    /**
     * Wrapper-side dispatch. Skips if another dispatch is already in flight.
     */
    public function dispatchChunkJob(string $type, int $contextId): bool
    {
        $lockKey = self::lockKeyFor($type, $contextId);
        if (Cache::add($lockKey, 1, 300)) {
            ComputeOpenAlexAggregateJob::dispatch($contextId, $type);
            return true;
        }
        return false;
    }

    /**
     * Re-dispatch from inside a running chunk job. The wrapper lock is still
     * held (cleared only in the parent finally), so we'd otherwise stop after
     * the first chunk.
     */
    public function dispatchNextChunk(string $type, int $contextId): void
    {
        ComputeOpenAlexAggregateJob::dispatch($contextId, $type);
    }

    public static function chunkedPlaceholder(?array $state): array
    {
        return [
            'is_computing' => true,
            'progress' => [
                'processed' => $state['processed'] ?? 0,
                'total'     => $state['total'] ?? null,
            ],
        ];
    }

    /**
     * Sanitize a DOI for safe use in API URLs.
     *
     * Strips control characters (line breaks, null bytes, etc.) that could
     * enable HTTP header injection, then URL-encodes the result for safe
     * concatenation into API request URLs. Returns null if the DOI is empty
     * after sanitization so callers can skip the API call entirely.
     *
     * @param string $doi Raw DOI string
     * @return string|null URL-encoded DOI, or null if the input is empty/invalid
     */
    private function sanitizeDoi(string $doi): ?string
    {
        $doi = trim($doi);
        $doi = preg_replace('/[\r\n\x00-\x1f]/', '', $doi);

        if ($doi === '' || $doi === null) {
            return null;
        }

        return urlencode($doi);
    }
    public function getWorkByDOI(string $doi): ?array
    {
        $sanitizedDoi = $this->sanitizeDoi($doi);
        if ($sanitizedDoi === null) {
            return null;
        }

        $cacheKey = "openalex_work_" . md5($doi);

        // Cache::remember would pin a transient null (5xx/network) for the full TTL.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
        $work = $this->httpGetWithRetry(
            self::API_BASE . '/works/doi:' . $sanitizedDoi,
            [],
            10,
            "DOI {$doi}"
        );

        if ($work !== null) {
            Cache::put($cacheKey, $work, PublicStatsConstants::CACHE_TTL_EXTERNAL);
        }
        return $work;
    }
    
    public function getWorkMetrics(string $doi): ?array
    {
        $work = $this->getWorkByDOI($doi);
        if (!$work) {
            return null;
                }
        return [
            'openalex_id' => $work['id'] ?? null,
            'cited_by_count' => $work['cited_by_count'] ?? 0,
            'fwci' => $work['fwci'] ?? null,
            'counts_by_year' => $work['counts_by_year'] ?? [],
            'topics' => $work['topics'] ?? [],
            'primary_topic' => $work['primary_topic'] ?? null,
            'sustainable_development_goals' => $work['sustainable_development_goals'] ?? [],
            'grants' => $work['grants'] ?? [],
            'oa_status' => $work['open_access']['oa_status'] ?? 'closed',
            'is_oa' => $work['open_access']['is_oa'] ?? false,
            'countries_distinct_count' => $work['countries_distinct_count'] ?? 0,
            'institutions_distinct_count' => $work['institutions_distinct_count'] ?? 0,
            'is_retracted' => $work['is_retracted'] ?? false,
            'referenced_works_count' => count($work['referenced_works'] ?? []),
        ];
    }
    
    public function getAuthorMetrics(string $orcid): ?array
    {
        $cacheKey = "openalex_author_" . md5($orcid);

        // See getWorkByDOI: avoid Cache::remember to not pin transient nulls.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);

        $orcidUrl = strpos($orcid, 'https://orcid.org/') === 0
            ? $orcid
            : 'https://orcid.org/' . $orcid;

        $author = $this->httpGetWithRetry(
            self::API_BASE . '/authors/' . $orcidUrl,
            [],
            10,
            "ORCID {$orcid}"
        );
        if ($author === null) {
            return null;
        }

        $metrics = [
            'openalex_id' => $author['id'] ?? null,
            'works_count' => $author['works_count'] ?? 0,
            'cited_by_count' => $author['cited_by_count'] ?? 0,
            'h_index' => $author['summary_stats']['h_index'] ?? 0,
            'i10_index' => $author['summary_stats']['i10_index'] ?? 0,
            '2yr_mean_citedness' => $author['summary_stats']['2yr_mean_citedness'] ?? 0,
            'counts_by_year' => $author['counts_by_year'] ?? [],
        ];

        Cache::put($cacheKey, $metrics, PublicStatsConstants::CACHE_TTL_EXTERNAL);
        return $metrics;
    }
    
    public function getCitingWorks(string $openalexId): array
    {
        $cacheKey = "openalex_citing_paginated_" . md5($openalexId);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $results   = [];
        $cursor    = '*';
        $maxPages  = 5; // safety cap: up to 1 000 citing works
        $anyFailed = false;

        for ($page = 0; $page < $maxPages; $page++) {
            usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);

            $body = $this->httpGetWithRetry(
                self::API_BASE . '/works',
                ['filter' => 'cites:' . $openalexId, 'per_page' => 200, 'cursor' => $cursor],
                10,
                "citing {$openalexId} page " . ($page + 1)
            );

            if ($body === null) {
                $anyFailed = true;
                break;
            }

            $results = array_merge($results, $body['results'] ?? []);

            $cursor = $body['meta']['next_cursor'] ?? null;
            if (!$cursor) break;
        }

        // Don't cache a partial traversal — a 5xx mid-pagination would freeze incomplete data.
        if (!$anyFailed) {
            Cache::put($cacheKey, $results, PublicStatsConstants::CACHE_TTL_EXTERNAL);
        }
        return $results;
    }
    


    /**
     * Citing journals. Returns the cached payload, or null while a queue job
     * is computing it (the caller turns that null into an `is_computing` placeholder).
     */
    public function getCitingJournals(int $contextId): ?array
    {
        return $this->cachedOrEnqueue(
            ComputeOpenAlexAggregateJob::TYPE_CITING_JOURNALS,
            $contextId,
            null
        );
    }

    /**
     * Synchronous computation of citing journals. Called from the queue job.
     */
    public function getCitingJournalsSync(int $contextId): array
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getMany();

        $journalCitations = [];

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($submissions as $submission) {
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;

                $doi = $publication->getDoi();
                if (!$doi) continue;

                $work = $this->getWorkByDOI($doi);
                if (!$work || empty($work['id'])) continue;
                
                $openalexId = $work['id'];
                $citingWorks = $this->getCitingWorks($openalexId);
                
                $datePublished = $publication->getData('datePublished');
                $articleInfo = [
                    'id' => $submission->getId(),
                    'bestId' => $publication->getData('urlPath') ?: $submission->getId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => $datePublished ? date('Y', strtotime($datePublished)) : null,
                    'doi' => $doi
                ];

               foreach ($citingWorks as $citingWork) {
                    $primaryLocation = $citingWork['primary_location'] ?? null;
                    
                    if (!$primaryLocation || !is_array($primaryLocation)) {
                        continue;
                    }
                    
                    $source = $primaryLocation['source'] ?? null;
                    
                    if (!$source || 
                        !is_array($source) ||
                        empty($source['id']) || 
                        !isset($source['display_name']) ||
                        trim((string)$source['display_name']) === '') {
                        continue;
                    }
                    
                    $citationYear = $citingWork['publication_year'] ?? null;

                    $sourceId = $source['id'];
                    $sourceName = trim((string)$source['display_name']); 
                    
                    if (!isset($journalCitations[$sourceId])) {
                        $journalCitations[$sourceId] = [
                            'id' => $sourceId,
                            'name' => $sourceName,
                            'issn' => $source['issn_l'] ?? null,
                            'type' => $source['type'] ?? 'unknown',
                            'host_organization' => $source['host_organization_name'] ?? null,
                            'citations' => 0,
                            'citations_by_year' => [],
                            'cited_articles' => []
                        ];
                    }
                    
                    $journalCitations[$sourceId]['citations']++;
                    
                    if ($citationYear) {
                        if (!isset($journalCitations[$sourceId]['citations_by_year'][$citationYear])) {
                            $journalCitations[$sourceId]['citations_by_year'][$citationYear] = 0;
                        }
                        $journalCitations[$sourceId]['citations_by_year'][$citationYear]++;
                    }
                    
                    $articleId = $articleInfo['id'];
                    $articleYearKey = $articleId . '_' . $citationYear;

                    if (!isset($journalCitations[$sourceId]['cited_articles'][$articleYearKey])) {
                        $journalCitations[$sourceId]['cited_articles'][$articleYearKey] = [
                            'id' => $articleId,
                            'bestId' => $articleInfo['bestId'],
                            'title' => $articleInfo['title'],
                            'authors' => $articleInfo['authors'],
                            'year' => $articleInfo['year'],
                            'doi' => $articleInfo['doi'],
                            'citation_year' => $citationYear,
                            'times_cited' => 0
                        ];
                    }
                    $journalCitations[$sourceId]['cited_articles'][$articleYearKey]['times_cited']++;
                }
                
                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
            }
            
            foreach ($journalCitations as &$journal) {
                $journal['cited_articles'] = array_values($journal['cited_articles']);
                usort($journal['cited_articles'], fn($a, $b) => $b['times_cited'] - $a['times_cited']);
            }
            unset($journal); // drop the foreach reference before the next usort.

        usort($journalCitations, fn($a, $b) => $b['citations'] - $a['citations']);

        return array_values($journalCitations);
    }

    /**
     * Citing institutions. Returns the cached payload, or null while a queue job
     * is computing it (the caller turns that null into an `is_computing` placeholder).
     */
    public function getCitingInstitutions(int $contextId): ?array
    {
        return $this->cachedOrEnqueue(
            ComputeOpenAlexAggregateJob::TYPE_CITING_INSTITUTIONS,
            $contextId,
            null
        );
    }

    /**
     * Synchronous computation of citing institutions. Called from the queue job.
     */
    public function getCitingInstitutionsSync(int $contextId): array
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getMany();

        $institutionCitations = [];

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($submissions as $submission) {
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;

                $doi = $publication->getDoi();
                if (!$doi) continue;

                $work = $this->getWorkByDOI($doi);
                if (!$work || empty($work['id'])) continue;
                
                $openalexId = $work['id'];
                $citingWorks = $this->getCitingWorks($openalexId);
                
                $datePublished = $publication->getData('datePublished');
                $articleInfo = [
                    'id' => $submission->getId(),
                    'bestId' => $publication->getData('urlPath') ?: $submission->getId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => $datePublished ? date('Y', strtotime($datePublished)) : null,
                    'doi' => $doi
                ];
                
                foreach ($citingWorks as $citingWork) {
                    if (empty($citingWork['authorships'])) continue;
                    
                    $citationYear = $citingWork['publication_year'] ?? null;

                    // Track unique institutions per citing work to avoid double counting
                    $seenInstitutions = [];
                    
                    foreach ($citingWork['authorships'] as $authorship) {
                        if (empty($authorship['institutions'])) continue;
                        
                        foreach ($authorship['institutions'] as $institution) {
                            $institutionId = $institution['id'] ?? null;
                            $institutionName = $institution['display_name'] ?? null;
                            
                            if (!$institutionId || !$institutionName) continue;
                            
                            if (isset($seenInstitutions[$institutionId])) continue;
                            $seenInstitutions[$institutionId] = true;
                            
                            if (!isset($institutionCitations[$institutionId])) {
                                $institutionCitations[$institutionId] = [
                                    'id' => $institutionId,
                                    'name' => $institutionName,
                                    'country_code' => $institution['country_code'] ?? null,
                                    'type' => $institution['type'] ?? 'unknown',
                                    'ror' => $institution['ror'] ?? null,
                                    'citations' => 0,
                                    'citations_by_year' => [],
                                    'cited_articles' => []
                                ];
                            }
                            
                            $institutionCitations[$institutionId]['citations']++;
                            
                            if ($citationYear) {
                                if (!isset($institutionCitations[$institutionId]['citations_by_year'][$citationYear])) {
                                    $institutionCitations[$institutionId]['citations_by_year'][$citationYear] = 0;
                                }
                                $institutionCitations[$institutionId]['citations_by_year'][$citationYear]++;
                            }
                            
                            $articleId = $articleInfo['id'];
                            $articleYearKey = $articleId . '_' . $citationYear;
                            
                            if (!isset($institutionCitations[$institutionId]['cited_articles'][$articleYearKey])) {
                                $institutionCitations[$institutionId]['cited_articles'][$articleYearKey] = [
                                    'id' => $articleId,
                                    'bestId' => $articleInfo['bestId'],
                                    'title' => $articleInfo['title'],
                                    'authors' => $articleInfo['authors'],
                                    'year' => $articleInfo['year'],
                                    'doi' => $articleInfo['doi'],
                                    'citation_year' => $citationYear,
                                    'times_cited' => 0
                                ];
                            }
                            $institutionCitations[$institutionId]['cited_articles'][$articleYearKey]['times_cited']++;
                        }
                    }
                }
                
                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
            }
            
            foreach ($institutionCitations as &$institution) {
                $institution['cited_articles'] = array_values($institution['cited_articles']);
                usort($institution['cited_articles'], fn($a, $b) => $b['times_cited'] - $a['times_cited']);
            }
            unset($institution); // drop the foreach reference before the next usort.

        usort($institutionCitations, fn($a, $b) => $b['citations'] - $a['citations']);

        return array_values($institutionCitations);
    }
}