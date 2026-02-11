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

use APP\facades\Repo;
use PKP\submission\PKPSubmission;
use Illuminate\Support\Facades\Cache;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Http;
class OpenAlexService
{
    private const API_BASE = 'https://api.openalex.org';
    private string $contactEmail;  
    
    /**
     * Constructor - Initialize contact email from plugin settings
     */
    public function __construct()  
    {
        $this->contactEmail = $this->getContactEmail();
    }

    private function getContactEmail(): string
    {
        try {
            $request = \Application::get()->getRequest();
            $context = $request?->getContext();
            
            if (!$context) {
                return 'noreply@example.com';
            }
            
            $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'publicstatsplugin');
            
            return $plugin?->getSetting($context->getId(), 'openAlexEmail') 
                ?? $context->getData('contactEmail') 
                ?? 'noreply@example.com';
        } catch (\Exception $e) {
            error_log("OpenAlexService: Could not get contact email: " . $e->getMessage());
            return 'noreply@example.com';
        }
    }

    /**
     * Sanitize a DOI for safe use in API URLs.
     *
     * Strips control characters (line breaks, null bytes, etc.) that could
     * enable HTTP header injection, then URL-encodes the result for safe
     * concatenation into API request URLs.
     *
     * @param string $doi Raw DOI string
     * @return string Sanitized and URL-encoded DOI
     */
    private function sanitizeDoi(string $doi): string
    {
        $doi = trim($doi);
        $doi = preg_replace('/[\r\n\x00-\x1f]/', '', $doi);

        return urlencode($doi);
    }
    /**
     * Get OpenAlex work by DOI
     */
    public function getWorkByDOI(string $doi): ?array
    {
        $cacheKey = "openalex_work_" . md5($doi);
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($doi) {
            try {
                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
                
                $url = self::API_BASE . '/works/doi:' . $this->sanitizeDoi($doi);
                
                $response = Http::timeout(10)
                    ->withHeaders(['mailto' => $this->contactEmail])
                    ->get($url);
                
                if (!$response->successful()) {
                    error_log("OpenAlex API: Non-200 response for DOI {$doi}: " . $response->status());
                    return null;
                }
                
                return $response->json();
                
            } catch (\Exception $e) {
                error_log("OpenAlex API error for DOI {$doi}: " . $e->getMessage());
                return null;
            }
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
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($orcid) {
            try {
                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
                
                $orcidUrl = strpos($orcid, 'https://orcid.org/') === 0 
                    ? $orcid 
                    : 'https://orcid.org/' . $orcid;
                
                $url = self::API_BASE . '/authors/' . $orcidUrl;
                
                $response = Http::timeout(10)
                    ->withHeaders(['mailto' => $this->contactEmail])
                    ->get($url);

                if (!$response->successful()) {
                    error_log("OpenAlex API: Failed for ORCID {$orcid}");
                    return null;
                }

                $author = $response->json();
                
                return [
                    'openalex_id' => $author['id'] ?? null,
                    'works_count' => $author['works_count'] ?? 0,
                    'cited_by_count' => $author['cited_by_count'] ?? 0,
                    'h_index' => $author['summary_stats']['h_index'] ?? 0,
                    'i10_index' => $author['summary_stats']['i10_index'] ?? 0,
                    '2yr_mean_citedness' => $author['summary_stats']['2yr_mean_citedness'] ?? 0,
                    'counts_by_year' => $author['counts_by_year'] ?? [],
                ];
                
            } catch (\Exception $e) {
                error_log("OpenAlex API error for ORCID {$orcid}: " . $e->getMessage());
                return null;
            }
        });
    }
    
    /**
     * Sync journal works from OpenAlex by ISSN
     */
    public function syncJournalByISSN(string $issn, int $limit = 200): array
    {
        try {
            usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
            
            $url = self::API_BASE . '/works';
            
            $response = Http::timeout(15)
                ->withHeaders(['mailto' => $this->contactEmail])
                ->get($url, [
                    'filter' => 'primary_location.source.issn:' . $issn,
                    'per-page' => $limit
                ]);

            if (!$response->successful()) {
                error_log("OpenAlex API: Failed to sync ISSN {$issn}");
                return [];
            }

            return $response->json()['results'] ?? [];
            
        } catch (\Exception $e) {
            error_log("OpenAlex sync error for ISSN {$issn}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get topics for text (title + abstract)
     */
   public function getTopicsForText(string $title, ?string $abstract = null): ?array
    {
        $cacheKey = "openalex_topics_" . md5($title . ($abstract ?? ''));
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($title, $abstract) {
            try {
                usleep(PublicStatsConstants::OPENALEX_TEXT_RATE_LIMIT_DELAY);
                
                $url = self::API_BASE . '/text';
                
                $response = Http::timeout(15)
                    ->withHeaders(['mailto' => $this->contactEmail])
                    ->get($url, [
                        'title' => $title,
                        'abstract' => $abstract
                    ]);

                if (!$response->successful()) {
                    error_log("OpenAlex API: Failed to fetch topics for text");
                    return null;
                }

                return $response->json();
                
            } catch (\Exception $e) {
                error_log("OpenAlex /text API error: " . $e->getMessage());
                return null;
            }
        });
    }
    
    /**
     * Get citation network for a work (who cites it)
     */
    public function getCitingWorks(string $openalexId, int $limit = 50): array
    {
        $cacheKey = "openalex_citing_" . md5($openalexId);
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($openalexId, $limit) {
            try {
                usleep(PublicStatsConstants::OPENALEX_RATE_LIMIT_DELAY);
                
                $url = self::API_BASE . '/works';
                
                $response = Http::timeout(10)
                    ->withHeaders(['mailto' => $this->contactEmail])
                    ->get($url, [
                        'filter' => 'cites:' . $openalexId,
                        'per-page' => $limit
                    ]);

                if (!$response->successful()) {
                    error_log("OpenAlex API: Failed to fetch citing works for {$openalexId}");
                    return [];
                }

                return $response->json()['results'] ?? [];
                
            } catch (\Exception $e) {
                error_log("OpenAlex citing works error: " . $e->getMessage());
                return [];
            }
        });
    }
    
      /**
     * Enrich context statistics with OpenAlex data
     */
    public function enrichContextStatistics(int $contextId): array
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
        ];
        
        $fwciSum = 0;
        $topicsCount = [];
        $sdgsCount = [];
        
        // Safety limit: Process maximum 1000 submissions to prevent timeout/memory issues
        $maxToProcess = PublicStatsConstants::MAX_SUBMISSIONS_TO_PROCESS;
        $processedCount = 0;
        
        foreach ($submissions as $submission) {
            // Safety check: Stop if we've processed enough
            if ($processedCount >= $maxToProcess) {
                error_log("OpenAlex enrichment: Stopped at {$maxToProcess} submissions for context {$contextId}");
                break;
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
                    error_log("Citations by country: Stopped at {$maxToProcess} submissions");
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
     * Get citing journals/sources for all published works
     */
    public function getCitingJournals(int $contextId): array
    {
        $cacheKey = "openalex_citing_journals_{$contextId}";
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($contextId) {
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
                    error_log("Citing journals: Stopped at {$maxToProcess} submissions");
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
        });
    }

    /**
     * Get citing institutions for all published works
     * 
     * Returns institutions whose authors have cited works from this journal,
     * aggregated by institution with citation counts.
     * 
     * @param int $contextId Journal/press ID
     * @return array Array of institutions with citation data
     */
    public function getCitingInstitutions(int $contextId): array
    {
        $cacheKey = "openalex_citing_institutions_{$contextId}";
        
        return Cache::remember($cacheKey, PublicStatsConstants::CACHE_TTL_EXTERNAL, function() use ($contextId) {
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
                    error_log("Citing institutions: Stopped at {$maxToProcess} submissions");
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
        });
    }
}