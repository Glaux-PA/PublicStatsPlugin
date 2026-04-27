<?php

/**
 * @file plugins/generic/publicStats/services/EnrichedStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnrichedStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for combining local statistics with OpenAlex data.
 *
 * Provides enriched article and context statistics by combining
 * local OJS metrics (downloads, views) with external citation data
 * from OpenAlex. Includes citation counts, FWCI scores, and OA status.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use PKP\submission\PKPSubmission;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\services\OpenAlexService;
use APP\plugins\generic\publicStats\services\ArticleStatsService;
use Illuminate\Support\Facades\Cache;
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
     * Get top articles enriched with external citation metrics
     * 
     * @param PKPRequest $request Current request
     * @param int $contextId Context/journal ID
     * @param string $metricType 'downloads', 'views', or 'citations'
     * @param int $limit Number of articles to return
     * @param string|null $dateStart Start date (Ymd format)
     * @param string|null $dateEnd End date (Ymd format)
     * @return array Articles with combined local and external metrics
     */
    public function getEnrichedTopArticles(
        PKPRequest $request,
        int $contextId,
        string $metricType = 'downloads',
        int $limit = 20,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        // Get base articles from local stats
        $baseArticles = $this->getBaseTopArticles(
            $request,
            $contextId,
            $metricType,
            $limit,
            $dateStart,
            $dateEnd
        );

        // Enrich each article with OpenAlex data
        foreach ($baseArticles as &$article) {
            $this->enrichArticleWithExternalData($article);
        }

        // If sorting by citations, re-order the array
        if ($metricType === 'citations') {
            usort($baseArticles, function($a, $b) {
                return ($b['citations'] ?? 0) <=> ($a['citations'] ?? 0);
            });
        }

        return $baseArticles;
    }

    /**
     * Get enriched statistics for total overview
     * 
     * @param int $contextId Context/journal ID
     * @return array Combined local and external statistics
     */
    public function getEnrichedContextStats(int $contextId): array
    {
        // Get local stats
        $localStats = $this->getBaseContextStats($contextId);
        
        // Get external stats from OpenAlex
        $externalStats = $this->openAlexService->enrichContextStatistics($contextId);
        
        // Combine and calculate derived metrics
        $combined = $this->combineContextStats($localStats, $externalStats);
        
        return [
            'local' => $localStats,
            'external' => $externalStats,
            'combined' => $combined
        ];
    }

    /**
     * Get annual citation metrics from OpenAlex
     * 
     * @param int $contextId Context/journal ID
     * @param int $minYear Minimum year to include (default: 2015)
     * @return array Year-by-year citation counts
     */
   public function getAnnualCitationMetrics(int $contextId, int $minYear = 2015): array
    {
        $submissions = $this->getPublishedSubmissions($contextId);
        $citationsByYear = [];
        
        // Safety limit
        $maxToProcess = PublicStatsConstants::MAX_OPENALEX_REQUESTS;
        $processedCount = 0;

        foreach ($submissions as $submission) {
            if ($processedCount >= $maxToProcess) {
                break;
            }
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $doi = $publication->getDoi();
            if (!$doi) continue;
            
            $processedCount++;

            // Get citation data from OpenAlex
            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics || empty($metrics['counts_by_year'])) continue;

            // Aggregate citations by year
            foreach ($metrics['counts_by_year'] as $yearData) {
                $year = $yearData['year'];
                
                // Skip years before minimum
                if ($year < $minYear) continue;
                
                $citations = $yearData['cited_by_count'] ?? 0;
                
                if (!isset($citationsByYear[$year])) {
                    $citationsByYear[$year] = 0;
                }
                $citationsByYear[$year] += $citations;
            }
        }

        // Sort by year
        ksort($citationsByYear);
        
        // Convert to format expected by frontend
        $result = [];
        foreach ($citationsByYear as $year => $count) {
            $result[] = [
                'year' => (string)$year,
                'citations' => $count
            ];
        }
        
        return $result;
    }

    /**
     * Get base top articles without external enrichment
     */
    private function getBaseTopArticles(
        PKPRequest $request,
        int $contextId,
        string $metricType,
        int $limit,
        ?string $dateStart,
        ?string $dateEnd
    ): array {
        // Use existing ArticleStatsService to get base data
        if ($metricType === 'downloads') {
            return $this->articleStatsService->getTopDownloadedArticles(
                $request,
                $contextId,
                $limit,
                $dateStart,
                $dateEnd
            );
        } elseif ($metricType === 'views') {
            return $this->articleStatsService->getTopViewedArticles(
                $request,
                $contextId,
                $limit,
                $dateStart,
                $dateEnd
            );
        }
        
        // For 'citations' type, start with downloads and will re-sort later
        return $this->articleStatsService->getTopDownloadedArticles(
            $request,
            $contextId,
            $limit * 2, // Get more to ensure we have enough with DOIs
            $dateStart,
            $dateEnd
        );
    }

    /**
     * Enrich a single article with OpenAlex data
     */
    private function enrichArticleWithExternalData(array &$article): void
    {
        // Get the submission to access DOI
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

        // Fetch OpenAlex metrics
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
        $article['oa_status'] = $externalMetrics['oa_status'] ?? null;
        $article['is_oa'] = $externalMetrics['is_oa'] ?? false;
    }

    /**
     * Add empty external metrics when data is not available
     */
    private function addEmptyExternalMetrics(array &$article): void
    {
        $article['citations'] = 0;
        $article['fwci'] = null;
        $article['is_oa'] = false;
        $article['oa_status'] = null;
        $article['has_external_data'] = false;
        $article['doi'] = null;
    }

    /**
     * Get base context statistics (local data only)
     */
    private function getBaseContextStats(int $contextId): array
    {
        $totalArticles = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getCount();

        // Get total downloads and views from existing stats
        $statsService = Services::get('publicationStats');
        
        return [
            'total_articles' => $totalArticles,
            // These could be expanded with actual download/view totals if needed
        ];
    }

    /**
     * Combine local and external statistics
     */
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
     * Get top cited articles (with cache)
     * @param PKPRequest $request
     * @param int $contextId
     * @param int $limit
     * @param string|null $year If specified, filter by citations received in this year
     */
    public function getTopCitedArticles(
        PKPRequest $request,
        int $contextId,
        int $limit = 20,
        ?string $year = null
    ): array {
        $cacheKey = "top_cited_articles_{$contextId}_{$limit}" . ($year ? "_{$year}" : "");
        
        return Cache::remember($cacheKey, 86400, function() use ($request, $contextId, $limit, $year) {
            $submissions = $this->getPublishedSubmissions($contextId);
            $articlesWithCitations = [];
            
            $maxSubmissionsToCheck = PublicStatsConstants::MAX_OPENALEX_REQUESTS; 
            $targetArticles = $limit * 3; 
            $processedCount = 0;
            
            $userGroups = Repo::userGroup()->getCollector()
                ->filterByContextIds([$contextId])
                ->getMany();
            
            foreach ($submissions as $submission) {
                if ($processedCount >= $maxSubmissionsToCheck) {
                    break;
                }
                
                $publication = $submission->getCurrentPublication();
                if (!$publication) continue;
                
                $doi = $publication->getDoi();
                if (!$doi) continue;
                
                $processedCount++;
                
                $metrics = $this->openAlexService->getWorkMetrics($doi);
                if (!$metrics) continue;
                
                // Determine citations count based on year filter
                $citationsCount = 0;
                if ($year !== null) {
                    // Get citations received in the specified year
                    $countsByYear = $metrics['counts_by_year'] ?? [];
                    foreach ($countsByYear as $yearData) {
                        if (isset($yearData['year']) && (string)$yearData['year'] === $year) {
                            $citationsCount = $yearData['cited_by_count'] ?? 0;
                            break;
                        }
                    }
                    // Skip articles with no citations in the selected year
                    if ($citationsCount === 0) continue;
                } else {
                    // Use total citations
                    $citationsCount = $metrics['cited_by_count'] ?? 0;
                    if ($citationsCount === 0) continue;
                }
                
                $articlesWithCitations[] = [
                    'submissionId' => $submission->getId(),
                    'title' => $publication->getLocalizedTitle(),
                    'authors' => $publication->getAuthorString($userGroups),
                    'year' => date('Y', strtotime($publication->getData('datePublished'))),
                    'citations' => $citationsCount,
                    'urlPublished' => $request->getDispatcher()->url(
                        $request,
                        Application::ROUTE_PAGE,
                        null,
                        'article',
                        'view',
                        $submission->getBestId()
                    )
                ];
                
                if (count($articlesWithCitations) >= $targetArticles) {
                    break;
                }
            }
            
            usort($articlesWithCitations, fn($a, $b) => $b['citations'] - $a['citations']);
            
            return array_slice($articlesWithCitations, 0, $limit);
        });
    }
    /**
     * Get citation evolution (with cache)
     */
    public function getCitationEvolution(int $contextId, int $minYear = 2015): array
    {
        $cacheKey = "citation_evolution_{$contextId}_{$minYear}";
        
        return Cache::remember($cacheKey, 86400, function() use ($contextId, $minYear) {
            return $this->getAnnualCitationMetrics($contextId, $minYear);
        });
    }


    /**
     * Get open access statistics
     */
     public function getOpenAccessStats(int $contextId): array
    {
        $submissions = $this->getPublishedSubmissions($contextId);
        
        $stats = [
            'total' => 0,
            'open_access' => 0,
            'by_type' => [
                'diamond' => 0,
                'gold' => 0,
                'hybrid' => 0,
                'green' => 0,
                'bronze' => 0,
                'closed' => 0,
                'unknown' => 0
            ]
        ];
        
        // Safety limit

        $processedCount = 0;
        
        foreach ($submissions as $submission) {
     
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $doi = $publication->getDoi();
            if (!$doi) continue;
            
            $processedCount++;
            
            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics) continue;
            
            $stats['total']++;
            
            if ($metrics['is_oa']) {
                $stats['open_access']++;
            }
            
            $oaStatus = $metrics['oa_status'] ?? 'closed';
            if (isset($stats['by_type'][$oaStatus])) {
                $stats['by_type'][$oaStatus]++;
            } else {
                $stats['by_type']['closed']++;
            }
        }
        
        return $stats;
    }
    /**
     * Get thematic profile (research areas)
     */
      public function getThematicProfile(int $contextId): array
    {
        $submissions = $this->getPublishedSubmissions($contextId);
        $topicsCount = [];
        
        $processedCount = 0;
        $totalArticles = 0;
        
        foreach ($submissions as $submission) {
 
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $doi = $publication->getDoi();
            if (!$doi) continue;
            
            $processedCount++;
            $totalArticles++;
            
            $metrics = $this->openAlexService->getWorkMetrics($doi);
            if (!$metrics || empty($metrics['topics'])) continue;
            
            foreach ($metrics['topics'] as $topic) {
                $name = $topic['display_name'] ?? 'Unknown';
                
                if (!isset($topicsCount[$name])) {
                    $topicsCount[$name] = 0;
                }
                $topicsCount[$name]++;
                
                break;
            }
        }
        
        arsort($topicsCount);
        
        $topics = [];
        foreach ($topicsCount as $name => $count) {
            $topics[] = [
                'name' => $name,
                'count' => $count
            ];
        }
        
        return [
            'topics' => $topics,
            'total_articles' => $totalArticles 
        ];
    }

    /**
     * Get formatted citations by country data
     */
    public function getCitationsByCountry(int $contextId): ?array
    {
        try {
            $countryCitations = $this->openAlexService->getCitationsByCountry($contextId);
            
            if (empty($countryCitations)) {
                return null;
            }
            
            $isoCodes = app(\Sokil\IsoCodes\IsoCodesFactory::class);
            $formattedData = [];
            
            foreach ($countryCitations as $countryCode => $citationCount) {
                // Ensure countryCode is a valid 2-letter string
                $countryCode = (string) $countryCode;
                if (strlen($countryCode) !== 2) {
                    continue;
                }
                
                try {
                    $country = $isoCodes->getCountries()->getByAlpha2($countryCode);
                    $countryName = $country ? $country->getLocalName() : $countryCode;
                } catch (\Exception $e) {
                    $countryName = $countryCode;
                }
                
                $formattedData[] = [
                    'country_code' => $countryCode,
                    'country_name' => $countryName,
                    'citations_count' => $citationCount
                ];
            }
            
            usort($formattedData, fn($a, $b) => $b['citations_count'] - $a['citations_count']);
            
            return $formattedData;
            
        } catch (\Exception $e) {
            Logger::error("Error getting citations by country", $e);
            return null;
        }
    }

    /**
     * Get formatted citing journals data
     * 
     */
    public function getCitingJournals(PKPRequest $request, int $contextId): ?array
    {
        try {
            $journals = $this->openAlexService->getCitingJournals($contextId);
            
            if (empty($journals)) {
                return null;
            }
            
            $formattedData = [];
            $allYears = [];
            
            foreach ($journals as $journal) {
                // Build URLs for cited articles and collect years
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
                            $article['bestId']
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
            
            // Sort years descending
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

    /**
     * Get formatted citing institutions data
     * 
     * @param PKPRequest $request Current request for URL generation
     * @param int $contextId Journal/press ID
     * @return array|null Formatted institutions data or null if no data
     */
    public function getCitingInstitutions(PKPRequest $request, int $contextId): ?array
    {
        try {
            $institutions = $this->openAlexService->getCitingInstitutions($contextId);
            
            if (empty($institutions)) {
                return null;
            }
            
            $isoCodes = app(\Sokil\IsoCodes\IsoCodesFactory::class);
            $formattedData = [];
            $allYears = [];
            
            foreach ($institutions as $institution) {
                // Get country name from code
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
                
                // Build URLs for cited articles and collect years
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
                            $article['bestId']
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
            
            // Sort years descending
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