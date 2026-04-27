<?php

/**
 * @file plugins/generic/publicStats/services/OpenAlexService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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

    /**
     * Constructor - Initialize contact email from plugin settings
     */
    public function __construct()
    {
        $this->contactEmail = $this->getContactEmail();
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

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($this->apiHeaders())
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
     * Build HTTP headers for OpenAlex API requests.
     * Only includes the 'mailto' header when a real contact email is available
     * (so we don't pollute OpenAlex's polite-pool logs with fake addresses).
     */
    private function apiHeaders(): array
    {
        return $this->contactEmail ? ['mailto' => $this->contactEmail] : [];
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
    private function cachedOrEnqueue(string $type, int $contextId, mixed $placeholder): mixed
    {
        $cacheKey = self::cacheKeyFor($type, $contextId);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $lockKey = self::lockKeyFor($type, $contextId);
        // Cache::add returns true only when the key didn't exist, which makes
        // this our "first request wins, subsequent requests short-circuit" gate.
        $lockTtl = max(120, (int) (PublicStatsConstants::CACHE_TTL_EXTERNAL / 24)); // ~1h default
        if (Cache::add($lockKey, 1, $lockTtl)) {
            ComputeOpenAlexAggregateJob::dispatch($contextId, $type);
        }

        return $placeholder;
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
    /**
     * Get OpenAlex work by DOI
     */
    public function getWorkByDOI(string $doi): ?array
    {
        $sanitizedDoi = $this->sanitizeDoi($doi);
        if ($sanitizedDoi === null) {
            return null;
        }

        $cacheKey = "openalex_work_" . md5($doi);

        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function () use ($doi, $sanitizedDoi) {
            usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
            return $this->httpGetWithRetry(
                self::API_BASE . '/works/doi:' . $sanitizedDoi,
                [],
                10,
                "DOI {$doi}"
            );
        });
    }
    
    /**
     * Get enriched metrics for a submission
     */
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
    
    /**
     * Get author metrics by ORCID
     */
    public function getAuthorMetrics(string $orcid): ?array
    {
        $cacheKey = "openalex_author_" . md5($orcid);

        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function () use ($orcid) {
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

            return [
                'openalex_id' => $author['id'] ?? null,
                'works_count' => $author['works_count'] ?? 0,
                'cited_by_count' => $author['cited_by_count'] ?? 0,
                'h_index' => $author['summary_stats']['h_index'] ?? 0,
                'i10_index' => $author['summary_stats']['i10_index'] ?? 0,
                '2yr_mean_citedness' => $author['summary_stats']['2yr_mean_citedness'] ?? 0,
                'counts_by_year' => $author['counts_by_year'] ?? [],
            ];
        });
    }
    
    /**
     * Sync journal works from OpenAlex by ISSN
     */
    public function syncJournalByISSN(string $issn, int $limit = 200): array
    {
        usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);

        $body = $this->httpGetWithRetry(
            self::API_BASE . '/works',
            [
                'filter' => 'primary_location.source.issn:' . $issn,
                'per-page' => $limit,
            ],
            15,
            "ISSN {$issn}"
        );

        return $body['results'] ?? [];
    }
    
    /**
     * Get topics for text (title + abstract)
     */
    public function getTopicsForText(string $title, ?string $abstract = null): ?array
    {
        $cacheKey = "openalex_topics_" . md5($title . ($abstract ?? ''));

        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function () use ($title, $abstract) {
            usleep(PublicStatsConstants::OPENALEX_TEXT_RATE_LIMIT_DELAY);

            return $this->httpGetWithRetry(
                self::API_BASE . '/text',
                ['title' => $title, 'abstract' => $abstract],
                15,
                '/text endpoint'
            );
        });
    }
    
    /**
     * Get citation network for a work (who cites it)
     */
    public function getCitingWorks(string $openalexId, int $limit = 50): array
    {
        $cacheKey = "openalex_citing_" . md5($openalexId);

        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function () use ($openalexId, $limit) {
            usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);

            $body = $this->httpGetWithRetry(
                self::API_BASE . '/works',
                ['filter' => 'cites:' . $openalexId, 'per-page' => $limit],
                10,
                "citing {$openalexId}"
            );

            return $body['results'] ?? [];
        });
    }
    
    /**
     * Enrich context statistics with OpenAlex data.
     *
     * Returns cached data if available; otherwise enqueues a background job
     * to compute it and returns an empty placeholder so the caller doesn't
     * block on hundreds of OpenAlex requests inside a web request.
     */
    public function enrichContextStatistics(int $contextId): array
    {
        return $this->cachedOrEnqueue(
            ComputeOpenAlexAggregateJob::TYPE_ENRICH_CONTEXT,
            $contextId,
            [
                'total_external_citations' => 0,
                'avg_fwci' => 0,
                'works_with_data' => 0,
                'top_topics' => [],
                'top_sdgs' => [],
                'retracted_count' => 0,
                'funded_works' => 0,
                'processed_count' => 0,
                'total_submissions' => 0,
                'is_partial' => false,
                'is_computing' => true,
            ]
        );
    }

    /**
     * Synchronous computation of context enrichment. Called from the queue job;
     * kept out of the request path because it can make hundreds of external
     * calls.
     */
    public function enrichContextStatisticsSync(int $contextId): array
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getMany();
        
        $stats = [
            'total_external_citations' => 0,
            'avg_fwci' => 0,
            'works_with_data' => 0,
            'top_topics' => [],
            'top_sdgs' => [],
            'retracted_count' => 0,
            'funded_works' => 0,
            'processed_count' => 0,
            'total_submissions' => 0,
            'is_partial' => false,
        ];

        $fwciSum = 0;
        $topicsCount = [];
        $sdgsCount = [];

        // Safety limit: Process maximum 1000 submissions to prevent timeout/memory issues
        $maxToProcess = PublicStatsConstants::MAX_SUBMISSIONS_TO_PROCESS;
        $processedCount = 0;
        $totalSubmissions = 0;

        foreach ($submissions as $submission) {
            $totalSubmissions++;

            // Safety check: Stop if we've processed enough
            if ($processedCount >= $maxToProcess) {
                $stats['is_partial'] = true;
                Logger::warning("OpenAlex enrichment truncated at {$maxToProcess} submissions for context {$contextId}");
                continue;
            }
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $doi = $publication->getDoi();
            if (!$doi) continue;
            
            $processedCount++;
            
            $metrics = $this->getWorkMetrics($doi);
            if (!$metrics) continue;

            $stats['works_with_data']++;
            $stats['total_external_citations'] += $metrics['cited_by_count'];
            
            if ($metrics['fwci']) {
                $fwciSum += $metrics['fwci'];
            }
            
            if ($metrics['is_retracted']) {
                $stats['retracted_count']++;
            }
            
            if (!empty($metrics['grants'])) {
                $stats['funded_works']++;
            }
            
            // Aggregate topics
            foreach ($metrics['topics'] as $topic) {
                $name = $topic['display_name'] ?? 'Unknown';
                $topicsCount[$name] = ($topicsCount[$name] ?? 0) + 1;
            }
            
            // Aggregate SDGs
            foreach ($metrics['sustainable_development_goals'] as $sdg) {
                $name = $sdg['display_name'] ?? 'Unknown';
                $sdgsCount[$name] = ($sdgsCount[$name] ?? 0) + 1;
            }
        }
        
        if ($stats['works_with_data'] > 0) {
            $stats['avg_fwci'] = round($fwciSum / $stats['works_with_data'], 2);
        }

        // Sort and get top 10
        arsort($topicsCount);
        $stats['top_topics'] = array_slice($topicsCount, 0, 10, true);

        arsort($sdgsCount);
        $stats['top_sdgs'] = array_slice($sdgsCount, 0, 10, true);

        $stats['processed_count'] = $processedCount;
        $stats['total_submissions'] = $totalSubmissions;

        return $stats;
    }


    /**
     * Get citations by country for all published works
     * 
     */
    public function getCitationsByCountry(int $contextId): array
    {
        $cacheKey = "openalex_citations_by_country_{$contextId}";
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($contextId) {
            $submissions = Repo::submission()
                ->getCollector()
                ->filterByContextIds([$contextId])
                ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
                ->getMany();
            
            $countryCitations = [];
            $processedCount = 0;
            $maxToProcess = min(PublicStatsConstants::MAX_SUBMISSIONS_TO_PROCESS, 200);
            
            foreach ($submissions as $submission) {
                if ($processedCount >= $maxToProcess) {
                    Logger::warning("Citations-by-country truncated at {$maxToProcess} submissions");
                    break;
                }
                
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                
                $doi = $publication->getDoi();
                if (!$doi) continue;
                
                $processedCount++;
                
                $work = $this->getWorkByDOI($doi);
                if (!$work || empty($work['id'])) continue;
                
                $openalexId = $work['id'];
                
                $citingWorks = $this->getCitingWorks($openalexId, 100);
                

                foreach ($citingWorks as $citingWork) {
                    if (empty($citingWork['authorships'])) continue;
                    
                    foreach ($citingWork['authorships'] as $authorship) {
                        if (empty($authorship['institutions'])) continue;
                        
                        foreach ($authorship['institutions'] as $institution) {
                            $countryCode = $institution['country_code'] ?? null;
                            
                            if ($countryCode) {
                                if (!isset($countryCitations[$countryCode])) {
                                    $countryCitations[$countryCode] = 0;
                                }
                                $countryCitations[$countryCode]++;
                                break; 
                            }
                        }
                        break; 
                    }
                }
                

                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
            }
            
            return $countryCitations;
        });
    }

    /**
     * Get citing journals/sources for all published works.
     *
     * Returns cached data when available; otherwise schedules a background
     * job and returns an empty array so the request returns immediately.
     */
    public function getCitingJournals(int $contextId): array
    {
        return $this->cachedOrEnqueue(
            ComputeOpenAlexAggregateJob::TYPE_CITING_JOURNALS,
            $contextId,
            []
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
        $processedCount = 0;
        $maxToProcess = min(PublicStatsConstants::MAX_SUBMISSIONS_TO_PROCESS, 200);

        // Get user groups for author strings
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($submissions as $submission) {
            if ($processedCount >= $maxToProcess) {
                Logger::warning("Citing-journals truncated at {$maxToProcess} submissions");
                break;
            }
                
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                
                $doi = $publication->getDoi();
                if (!$doi) continue;
                
                $processedCount++;
                
                $work = $this->getWorkByDOI($doi);
                if (!$work || empty($work['id'])) continue;
                
                $openalexId = $work['id'];
                $citingWorks = $this->getCitingWorks($openalexId, 100);
                
                // Article info for tracking which articles are cited
                $articleInfo = [
                    'id' => $submission->getId(),
                    'bestId' => $submission->getBestId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => date('Y', strtotime($publication->getData('datePublished'))),
                    'doi' => $doi
                ];
                
               foreach ($citingWorks as $citingWork) {
                    // Get journal/source from primary_location
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
                    
                    // Get the year when the citation was made (publication year of citing work)
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
                    
                    // Track citations by year
                    if ($citationYear) {
                        if (!isset($journalCitations[$sourceId]['citations_by_year'][$citationYear])) {
                            $journalCitations[$sourceId]['citations_by_year'][$citationYear] = 0;
                        }
                        $journalCitations[$sourceId]['citations_by_year'][$citationYear]++;
                    }
                    
                    // Track which article was cited (with citation year)
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
            
            // Convert cited_articles from associative to indexed array and sort by times_cited
            foreach ($journalCitations as &$journal) {
                $journal['cited_articles'] = array_values($journal['cited_articles']);
                usort($journal['cited_articles'], fn($a, $b) => $b['times_cited'] - $a['times_cited']);
            }
            
   
        usort($journalCitations, fn($a, $b) => $b['citations'] - $a['citations']);

        return array_values($journalCitations);
    }

    /**
     * Get citing institutions for all published works.
     *
     * Returns institutions whose authors have cited works from this journal,
     * aggregated by institution with citation counts.
     *
     * Returns cached data when available; otherwise schedules a background
     * job and returns an empty array so the request returns immediately.
     *
     * @param int $contextId Journal/press ID
     * @return array Array of institutions with citation data
     */
    public function getCitingInstitutions(int $contextId): array
    {
        return $this->cachedOrEnqueue(
            ComputeOpenAlexAggregateJob::TYPE_CITING_INSTITUTIONS,
            $contextId,
            []
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
        $processedCount = 0;
        $maxToProcess = min(PublicStatsConstants::MAX_SUBMISSIONS_TO_PROCESS, 200);

        // Get user groups for author strings
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($submissions as $submission) {
            if ($processedCount >= $maxToProcess) {
                Logger::warning("Citing-institutions truncated at {$maxToProcess} submissions");
                break;
            }
                
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                
                $doi = $publication->getDoi();
                if (!$doi) continue;
                
                $processedCount++;
                
                $work = $this->getWorkByDOI($doi);
                if (!$work || empty($work['id'])) continue;
                
                $openalexId = $work['id'];
                $citingWorks = $this->getCitingWorks($openalexId, 100);
                
                // Article info for tracking which articles are cited
                $articleInfo = [
                    'id' => $submission->getId(),
                    'bestId' => $submission->getBestId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => date('Y', strtotime($publication->getData('datePublished'))),
                    'doi' => $doi
                ];
                
                foreach ($citingWorks as $citingWork) {
                    if (empty($citingWork['authorships'])) continue;
                    
                    // Get the year when the citation was made
                    $citationYear = $citingWork['publication_year'] ?? null;
                    
                    // Track unique institutions per citing work to avoid double counting
                    $seenInstitutions = [];
                    
                    foreach ($citingWork['authorships'] as $authorship) {
                        if (empty($authorship['institutions'])) continue;
                        
                        foreach ($authorship['institutions'] as $institution) {
                            $institutionId = $institution['id'] ?? null;
                            $institutionName = $institution['display_name'] ?? null;
                            
                            if (!$institutionId || !$institutionName) continue;
                            
                            // Skip if we already counted this institution for this citing work
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
                            
                            // Track citations by year
                            if ($citationYear) {
                                if (!isset($institutionCitations[$institutionId]['citations_by_year'][$citationYear])) {
                                    $institutionCitations[$institutionId]['citations_by_year'][$citationYear] = 0;
                                }
                                $institutionCitations[$institutionId]['citations_by_year'][$citationYear]++;
                            }
                            
                            // Track which article was cited
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
            
            // Convert cited_articles from associative to indexed array and sort
            foreach ($institutionCitations as &$institution) {
                $institution['cited_articles'] = array_values($institution['cited_articles']);
                usort($institution['cited_articles'], fn($a, $b) => $b['times_cited'] - $a['times_cited']);
            }
            
        // Sort by citation count (descending)
        usort($institutionCitations, fn($a, $b) => $b['citations'] - $a['citations']);

        return array_values($institutionCitations);
    }
}