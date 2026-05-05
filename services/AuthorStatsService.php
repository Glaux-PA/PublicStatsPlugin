<?php

/**
 * @file plugins/generic/publicStats/services/AuthorStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for individual author statistics and deduplication.
 *
 * Per-author publication counts, download/view metrics, co-author
 * networks and temporal distribution. Authors are deduplicated by
 * ORCID, email and fuzzy name matching (in that order of preference).
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Cache;
use PKP\core\PKPRequest;
use PKP\submission\PKPSubmission;

class AuthorStatsService extends BaseStatsService
{
    /**
     * Build the per-author dashboard payload (info, summary, charts, articles, co-authors).
     */
    public function getAuthorStats(
        PKPRequest $request,
        int $contextId,
        string $authorKey,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $authorMap = $this->buildAuthorMap($contextId);
        $entry = $authorMap[$authorKey] ?? null;

        if (!$entry || empty($entry['ids'])) {
            return $this->getEmptyStats();
        }

        $authorIds = $entry['ids'];
        $authorData = [
            'id'          => $authorIds[0],
            'fullName'    => $entry['name'],
            'email'       => $entry['email'] ?? null,
            'affiliation' => $entry['affiliation'] ?? null,
            'country'     => $entry['country'] ?? null,
            'orcid'       => $entry['orcid'] ?? null,
        ];

        $submissions = $this->getAuthorSubmissions($contextId, $authorIds);
        
        if (empty($submissions)) {
            return $this->getEmptyStats();
        }
        
        // Get statistics
        $statsService = Services::get('publicationStats');
        $submissionIds = array_keys($submissions);
        
        $params = [
            'contextIds' => [$contextId],
            'submissionIds' => $submissionIds
        ];
        
        if ($dateStart) $params['dateStart'] = $dateStart;
        if ($dateEnd) $params['dateEnd'] = $dateEnd;
        
        $downloadRecords = $statsService->getTotals(
            array_merge($params, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE]])
        );
        
        $viewRecords = $statsService->getTotals(
            array_merge($params, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION]])
        );
        
        $sectionsMap = $this->getSectionsMap($contextId);

        $articleStats = $this->processArticleStats(
            $request,
            $submissions,
            $downloadRecords,
            $viewRecords,
            $contextId,
            $sectionsMap
        );

        return [
            'author' => $authorData,
            'summary' => $this->calculateSummary($articleStats),
            'articles' => $articleStats,
            'orcid' => $authorData['orcid'] ?? null,
            'temporal' => $this->getTemporalDistribution($submissions),
            'coAuthors' => $this->getCoAuthors($submissions, $authorIds),
            'sections' => $this->getSectionsDistribution($submissions, $sectionsMap),
        ];
    }
    
    /**
     * Author map keyed by author key, after ORCID/email/name merging. Single
     * source of truth for both the public list and the per-author lookup.
     *
     * Cached 6h: building it is O(n) over publications plus O(n²) fuzzy name
     * matching for authors without email.
     */
    private function buildAuthorMap(int $contextId): array
    {
        return Cache::remember(
            "author_map_{$contextId}",
            PublicStatsConstants::CACHE_TTL_INTERNAL * 6,
            fn() => $this->buildAuthorMapUncached($contextId)
        );
    }

    private function buildAuthorMapUncached(int $contextId): array
    {
        $submissions = $this->getPublishedSubmissions($contextId);
        $authorMap = [];

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $authors = $publication->getData('authors');
            if (!$authors) continue;

            foreach ($authors as $author) {
                $key = $this->createAuthorKey($author);

                if (!isset($authorMap[$key])) {
                    $authorMap[$key] = [
                        'name'        => $author->getFullName(),
                        'email'       => $author->getEmail(),
                        'affiliation' => $author->getLocalizedAffiliation(),
                        'orcid'       => $author->getOrcid(),
                        'country'     => $author->getCountry(),
                        'ids'         => [],
                        'submissionIds' => []
                    ];
                }

                if (!in_array($author->getId(), $authorMap[$key]['ids'])) {
                    $authorMap[$key]['ids'][] = $author->getId();
                }

                if (!in_array($submission->getId(), $authorMap[$key]['submissionIds'])) {
                    $authorMap[$key]['submissionIds'][] = $submission->getId();
                }

                if (strlen($author->getFullName()) > strlen($authorMap[$key]['name'])) {
                    $authorMap[$key]['name'] = $author->getFullName();
                }

                if (empty($authorMap[$key]['email']) && !empty($author->getEmail())) {
                    $authorMap[$key]['email'] = $author->getEmail();
                }

                if (empty($authorMap[$key]['affiliation']) && !empty($author->getLocalizedAffiliation())) {
                    $authorMap[$key]['affiliation'] = $author->getLocalizedAffiliation();
                }

                if (empty($authorMap[$key]['orcid']) && !empty($author->getOrcid())) {
                    $authorMap[$key]['orcid'] = $author->getOrcid();
                }
            }
        }

        $authorMap = $this->mergeByOrcid($authorMap);
        $authorMap = $this->mergeSimilarAuthorsWithoutEmail($authorMap);

        return $authorMap;
    }

    /**
     * Get authors for context with PRACTICAL deduplication
     *
     * @param int $contextId
     * @param int $minPublications Minimum publications to include
     * @return array Deduplicated authors as a sorted list for the frontend
     */
    public function getAuthorsForContext(int $contextId, int $minPublications = 1): array
    {
        $authorMap = $this->buildAuthorMap($contextId);

        $result = [];
        foreach ($authorMap as $key => $authorData) {
            $publicationCount = count($authorData['submissionIds']);

            if ($publicationCount >= $minPublications) {
                $result[] = [
                    'key'             => $key,
                    'ids'             => implode(',', $authorData['ids']),
                    'name'            => $authorData['name'],
                    'email'           => $authorData['email'],
                    'affiliation'     => $authorData['affiliation'],
                    'orcid'           => $authorData['orcid'],
                    'publicationCount' => $publicationCount
                ];
            }
        }

        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $result;
    }
    
    /**
     * Create author key based on ORCID or Name+Email combination
     * 
     */
    private function createAuthorKey($author): string
    {
        $orcid = $author->getOrcid();
        if (!empty($orcid)) {
            return 'orcid:' . $this->normalizeOrcid($orcid);
        }
        
        $name = $this->normalizeString($author->getFullName());
        $emailRaw = $author->getEmail() ?? '';

        if (!empty($emailRaw)) {
            $nameWords = array_filter(
                explode(' ', $name),
                fn($word) => strlen($word) >= 2
            );
            sort($nameWords);
            $nameKey = implode('_', $nameWords);

            $atPos = strpos($emailRaw, '@');
            $emailDomain = $atPos !== false ? substr($emailRaw, $atPos + 1) : $emailRaw;
            $emailKey = preg_replace('/[^a-z0-9]/i', '', mb_strtolower($emailDomain, 'UTF-8'));
            
            return 'name_email:' . $nameKey . '__' . $emailKey;
        }
        
        $nameWords = array_filter(
            explode(' ', $name),
            fn($word) => strlen($word) >= 2
        );
        sort($nameWords);
        $nameKey = implode('_', $nameWords);
        
        return 'name_only:' . $nameKey;
    }

    /**
     * Merge author entries that share the same ORCID but were keyed differently.
     *
     * This handles the case where the same author appears with ORCID in one
     * submission and without ORCID in another, producing different map keys
     * (e.g. 'orcid:0000-...' vs 'name_only:john_doe').
     */
    private function mergeByOrcid(array $authorMap): array
    {
        // Group entries by normalized ORCID
        $orcidGroups = []; // normalized_orcid => [key1, key2, ...]
        
        foreach ($authorMap as $key => $data) {
            $orcid = $data['orcid'] ?? '';
            if (empty($orcid)) continue;
            
            $normalizedOrcid = $this->normalizeOrcid($orcid);
            if (empty($normalizedOrcid)) continue;
            
            $orcidGroups[$normalizedOrcid][] = $key;
        }
        
        // Merge groups with more than one entry
        foreach ($orcidGroups as $normalizedOrcid => $keys) {
            if (count($keys) <= 1) continue;
            
            // Use the orcid-prefixed key if it exists, otherwise the first key
            $primaryKey = null;
            foreach ($keys as $k) {
                if (str_starts_with($k, 'orcid:')) {
                    $primaryKey = $k;
                    break;
                }
            }
            if ($primaryKey === null) {
                $primaryKey = $keys[0];
            }
            
            foreach ($keys as $k) {
                if ($k === $primaryKey) continue;
                
                $secondary = $authorMap[$k];
                
                // Merge IDs and submission IDs
                $authorMap[$primaryKey]['ids'] = array_unique(
                    array_merge($authorMap[$primaryKey]['ids'], $secondary['ids'])
                );
                $authorMap[$primaryKey]['submissionIds'] = array_unique(
                    array_merge($authorMap[$primaryKey]['submissionIds'], $secondary['submissionIds'])
                );
                
                // Keep the most complete info
                if (strlen($secondary['name']) > strlen($authorMap[$primaryKey]['name'])) {
                    $authorMap[$primaryKey]['name'] = $secondary['name'];
                }
                if (empty($authorMap[$primaryKey]['email']) && !empty($secondary['email'])) {
                    $authorMap[$primaryKey]['email'] = $secondary['email'];
                }
                if (empty($authorMap[$primaryKey]['affiliation']) && !empty($secondary['affiliation'])) {
                    $authorMap[$primaryKey]['affiliation'] = $secondary['affiliation'];
                }
                if (empty($authorMap[$primaryKey]['orcid']) && !empty($secondary['orcid'])) {
                    $authorMap[$primaryKey]['orcid'] = $secondary['orcid'];
                }
                
                unset($authorMap[$k]);
            }
        }
        
        return $authorMap;
    }
    
    /**
     * Merge similar authors that don't have email
     * 
     */
    private function mergeSimilarAuthorsWithoutEmail(array $authorMap): array
    {
        $noEmailGroups = [];
        $withEmailGroups = [];
        
        foreach ($authorMap as $key => $group) {
            if (str_starts_with($key, 'name_only:')) {
                $noEmailGroups[$key] = $group;
            } else {
                $withEmailGroups[$key] = $group;
            }
        }
        
        $merged = [];
        $processed = [];
        
        foreach ($noEmailGroups as $key1 => $group1) {
            if (in_array($key1, $processed)) continue;
            
            $mergedGroup = $group1;
            
            foreach ($noEmailGroups as $key2 => $group2) {
                if ($key1 === $key2 || in_array($key2, $processed)) continue;
                
                if ($this->areNamesSimilar($group1['name'], $group2['name'])) {
                    $mergedGroup['ids'] = array_merge($mergedGroup['ids'], $group2['ids']);
                    $mergedGroup['submissionIds'] = array_merge($mergedGroup['submissionIds'], $group2['submissionIds']);
                    
                    if (strlen($group2['name']) > strlen($mergedGroup['name'])) {
                        $mergedGroup['name'] = $group2['name'];
                    }
                    
                    if (empty($mergedGroup['affiliation']) && !empty($group2['affiliation'])) {
                        $mergedGroup['affiliation'] = $group2['affiliation'];
                    }
                    if (empty($mergedGroup['orcid']) && !empty($group2['orcid'])) {
                        $mergedGroup['orcid'] = $group2['orcid'];
                    }
                    
                    $processed[] = $key2;
                }
            }
            
            $merged[$key1] = $mergedGroup;
        }
        
        return array_merge($withEmailGroups, $merged);
    }
    
    /**
     * Check if one name is a subset of another
     * 
     */
    private function areNamesSimilar(string $name1, string $name2): bool {
        $words1 = explode(' ', $this->normalizeString($name1));
        $words2 = explode(' ', $this->normalizeString($name2));
        
        
        if (end($words1) !== end($words2)) return false;
        
        
        if (reset($words1) !== reset($words2)) return false;
        
       
        $similarity = 0;
        similar_text($name1, $name2, $similarity);
        return $similarity > 80;
    }
    
    /**
     * Normalize string for comparison
     * 
     * Elimina: acentos, puntuación, mayúsculas, espacios múltiples
     */
    private function normalizeString(string $text): string
    {
        if (empty($text)) return '';
        
        $text = mb_strtolower($text, 'UTF-8');
        
        $replacements = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'ý' => 'y', 'ÿ' => 'y',
            'æ' => 'ae', 'œ' => 'oe'
        ];
        
        $text = strtr($text, $replacements);
        
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }


    /**
     * Normalize ORCID to bare ID format (e.g. 0000-0002-1234-5678)
     * Handles full URLs, http/https variants, and bare IDs.
     */
    private function normalizeOrcid(string $orcid): string
    {
        $orcid = strtolower(trim($orcid));
        // Strip URL prefix: https://orcid.org/, http://orcid.org/, orcid.org/
        $orcid = preg_replace('#^https?://orcid\.org/#', '', $orcid);
        $orcid = preg_replace('#^orcid\.org/#', '', $orcid);
        return trim($orcid, '/');
    }
    
    /**
     * Get author submissions
     */
    private function getAuthorSubmissions(int $contextId, array $authorIds): array
    {
        $submissions = $this->getPublishedSubmissions($contextId);
        
        $authorSubmissions = [];
        
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $authors = $publication->getData('authors');
            foreach ($authors as $pubAuthor) {
                if (in_array($pubAuthor->getId(), $authorIds)) {
                    $authorSubmissions[$submission->getId()] = $submission;
                    break;
                }
            }
        }
        
        return $authorSubmissions;
    }
    
    /**
     * Process article statistics
     */
    private function processArticleStats(
        PKPRequest $request,
        array $submissions,
        iterable $downloadRecords,
        iterable $viewRecords,
        int $contextId,
        array $sectionsMap
    ): array {
        $downloadsBySubmission = [];
        foreach ($downloadRecords as $record) {
            if (isset($record->submission_id)) {
                $downloadsBySubmission[$record->submission_id] = $record->metric ?? 0;
            }
        }
        
        $viewsBySubmission = [];
        foreach ($viewRecords as $record) {
            if (isset($record->submission_id)) {
                $viewsBySubmission[$record->submission_id] = $record->metric ?? 0;
            }
        }
        
        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();
        
        $articleStats = [];
        foreach ($submissions as $submissionId => $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $downloads = $downloadsBySubmission[$submissionId] ?? 0;
            $views = $viewsBySubmission[$submissionId] ?? 0;
            
            $articleStats[] = [
                'submissionId' => $submissionId,
                'title' => $publication->getLocalizedTitle(),
                'authors' => $publication->getAuthorString($userGroups),
                'datePublished' => $publication->getData('datePublished'),
                'downloads' => $downloads,
                'views' => $views,
                'total' => $downloads + $views,
                'urlPublished' => $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'article',
                    'view',
                    $submission->getBestId()
                ),
                'section' => $this->getSectionName($publication, $sectionsMap)
            ];
        }
        
        usort($articleStats, fn($a, $b) => $b['total'] - $a['total']);
        
        return $articleStats;
    }
    
    /**
     * Calculate summary
     */
    private function calculateSummary(array $articleStats): array
    {
        $totalArticles = count($articleStats);
        $totalDownloads = 0;
        $totalViews = 0;
        
        foreach ($articleStats as $article) {
            $totalDownloads += $article['downloads'];
            $totalViews += $article['views'];
        }
        
        return [
            'totalArticles' => $totalArticles,
            'totalDownloads' => $totalDownloads,
            'totalViews' => $totalViews,
            'avgDownloadsPerArticle' => $totalArticles > 0 ? round($totalDownloads / $totalArticles, 1) : 0,
            'avgViewsPerArticle' => $totalArticles > 0 ? round($totalViews / $totalArticles, 1) : 0,
            'totalAccesses' => $totalDownloads + $totalViews
        ];
    }
    
    /**
     * Get temporal distribution
     */
    private function getTemporalDistribution(array $submissions): array
    {
        $byYear = [];
        
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $datePublished = $publication->getData('datePublished');
            if (!$datePublished) continue;
            
            $year = date('Y', strtotime($datePublished));
            
            if (!isset($byYear[$year])) {
                $byYear[$year] = 0;
            }
            $byYear[$year]++;
        }
        
        ksort($byYear);
        
        $result = [];
        foreach ($byYear as $year => $count) {
            $result[] = ['year' => $year, 'count' => $count];
        }
        
        return $result;
    }
    
    /**
     * Get co-authors
     */
    private function getCoAuthors(array $submissions, array $excludeAuthorIds): array
    {
        $coAuthors = [];
        
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $authors = $publication->getData('authors');
            
            foreach ($authors as $author) {
                if (in_array($author->getId(), $excludeAuthorIds)) continue;
                
                $key = $this->createAuthorKey($author);
                
                if (!isset($coAuthors[$key])) {
                    $coAuthors[$key] = [
                        'name' => $author->getFullName(),
                        'affiliation' => $author->getLocalizedAffiliation(),
                        'collaborations' => 0
                    ];
                }
                
                $coAuthors[$key]['collaborations']++;
            }
        }
        
        $result = array_values($coAuthors);
        usort($result, fn($a, $b) => $b['collaborations'] - $a['collaborations']);
        
        return array_slice($result, 0, 10);
    }
    
    /**
     * `sectionId => localizedTitle` for the context, cached 24h. Only scalars
     * are stored — the opcache-backed cache uses var_export() and chokes on
     * Section objects (no __set_state).
     *
     * @return array<int, string>
     */
    private function getSectionsMap(int $contextId): array
    {
        return Cache::remember(
            "author_sections_map_{$contextId}",
            PublicStatsConstants::CACHE_TTL_INTERNAL * 24,
            function () use ($contextId) {
                $map = [];
                foreach (Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany() as $section) {
                    $map[(int) $section->getId()] = (string) $section->getLocalizedTitle();
                }
                return $map;
            }
        );
    }

    /**
     * @param array<int, string> $sectionsMap `sectionId => title` from getSectionsMap()
     */
    private function getSectionsDistribution(array $submissions, array $sectionsMap): array
    {
        $sections = [];

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $sectionId = $publication->getData('sectionId');
            if (!$sectionId) continue;

            $sectionTitle = $sectionsMap[$sectionId] ?? null;
            if ($sectionTitle === null) continue;

            if (!isset($sections[$sectionId])) {
                $sections[$sectionId] = [
                    'sectionId' => $sectionId,
                    'sectionTitle' => $sectionTitle,
                    'count' => 0
                ];
            }

            $sections[$sectionId]['count']++;
        }

        usort($sections, fn($a, $b) => $b['count'] - $a['count']);

        return array_values($sections);
    }

    private function getSectionName($publication, array $sectionsMap): ?string
    {
        $sectionId = $publication->getData('sectionId');
        if (!$sectionId) return null;

        return $sectionsMap[$sectionId] ?? null;
    }
    
    /**
     * Get empty stats
     */
    private function getEmptyStats(): array
    {
        return [
            'author' => null,
            'summary' => [
                'totalArticles' => 0,
                'totalDownloads' => 0,
                'totalViews' => 0,
                'avgDownloadsPerArticle' => 0,
                'avgViewsPerArticle' => 0,
                'totalAccesses' => 0
            ],
            'articles' => [],
            'temporal' => [],
            'coAuthors' => [],
            'sections' => []
        ];
    }
}