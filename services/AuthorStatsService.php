<?php

/**
 * @file plugins/generic/publicStats/services/AuthorStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for individual author statistics and deduplication.
 *
 * Provides comprehensive statistics for individual authors including
 * publication counts, download/view metrics, co-author networks,
 * and temporal distribution. Implements author deduplication using
 * ORCID, email, and fuzzy name matching.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\core\Services;
use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use PKP\submission\PKPSubmission;

class AuthorStatsService extends BaseStatsService
{
    /**
     * Get comprehensive author statistics
     */
    public function getAuthorStats(
        PKPRequest $request,
        int $contextId,
        string $authorKey,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $authorData = $this->getAuthorDataFromKey($authorKey, $contextId);
        
        if (!$authorData) {
            return $this->getEmptyStats();
        }
        
        $authorIds = $this->getMatchingAuthorIds($contextId, $authorKey);
        
        if (empty($authorIds)) {
            return $this->getEmptyStats();
        }
        
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
        
        $articleStats = $this->processArticleStats(
            $request,
            $submissions,
            $downloadRecords,
            $viewRecords,
            $contextId
        );
        
        return [
            'author' => $authorData,
            'summary' => $this->calculateSummary($articleStats),
            'articles' => $articleStats,
            'orcid' => $authorData['orcid'] ?? null,
            'temporal' => $this->getTemporalDistribution($submissions),
            'coAuthors' => $this->getCoAuthors($submissions, $authorIds),
            'sections' => $this->getSectionsDistribution($submissions),
        ];
    }
    
    /**
     * Get authors for context with PRACTICAL deduplication
     * 
     * @param int $contextId
     * @param int $minPublications Minimum publications to include
     * @return array Deduplicated authors
     */
    public function getAuthorsForContext(int $contextId, int $minPublications = 1): array
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
                        'name' => $author->getFullName(),
                        'email' => $author->getEmail(),
                        'affiliation' => $author->getLocalizedAffiliation(),
                        'orcid' => $author->getOrcid(),
                        'country' => $author->getCountry(),
                        'ids' => [],
                        'submissionIds' => []
                    ];
                }
                
                if (!in_array($author->getId(), $authorMap[$key]['ids'])) {
                    $authorMap[$key]['ids'][] = $author->getId();
                }
                
                if (!in_array($submission->getId(), $authorMap[$key]['submissionIds'])) {
                    $authorMap[$key]['submissionIds'][] = $submission->getId();
                }
                
                // Update with most complete information
                if (strlen($author->getFullName()) > strlen($authorMap[$key]['name'])) {
                    $authorMap[$key]['name'] = $author->getFullName();
                }
                
                if (empty($authorMap[$key]['email'])) {
                    $email = $author->getEmail();
                    if (!empty($email)) {
                        $authorMap[$key]['email'] = $email;
                    }
                }
                
                if (empty($authorMap[$key]['affiliation'])) {
                    $affiliation = $author->getLocalizedAffiliation();
                    if (!empty($affiliation)) {
                        $authorMap[$key]['affiliation'] = $affiliation;
                    }
                }
                
                if (empty($authorMap[$key]['orcid'])) {
                    $orcid = $author->getOrcid();
                    if (!empty($orcid)) {
                        $authorMap[$key]['orcid'] = $orcid;
                    }
                }
            }
        }
        
        $authorMap = $this->mergeSimilarAuthorsWithoutEmail($authorMap);
        
        $result = [];
        foreach ($authorMap as $key => $authorData) {
            $publicationCount = count($authorData['submissionIds']);
            
            if ($publicationCount >= $minPublications) {
                $result[] = [
                    'key' => $key,
                    'ids' => implode(',', $authorData['ids']),
                    'name' => $authorData['name'],
                    'email' => $authorData['email'],
                    'affiliation' => $authorData['affiliation'],
                    'orcid' => $authorData['orcid'],
                    'publicationCount' => $publicationCount
                ];
            }
        }
        
        // Sort: most publications first, then alphabetically
       usort($result, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
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
            return 'orcid:' . strtolower(trim($orcid));
        }
        
        $name = $this->normalizeString($author->getFullName());
        $email = $this->normalizeString($author->getEmail() ?? '');
        
        if (!empty($email)) {
            $nameWords = array_filter(
                explode(' ', $name),
                fn($word) => strlen($word) >= 2
            );
            sort($nameWords);
            $nameKey = implode('_', $nameWords);
            
            $emailKey = str_replace(['@', '.', '-', '_'], '', $email);
            
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
     * Get matching author IDs for a key
     */
    private function getMatchingAuthorIds(int $contextId, string $authorKey): array
    {
        $authors = Repo::author()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();
        
        $matchingIds = [];
        
        foreach ($authors as $author) {
            $key = $this->createAuthorKey($author);
            
            if ($key === $authorKey) {
                $matchingIds[] = $author->getId();
            }
        }
        
        return array_unique($matchingIds);
    }
    
    /**
     * Get author data from key
     */
    private function getAuthorDataFromKey(string $authorKey, int $contextId): ?array
    {
        $matchingIds = $this->getMatchingAuthorIds($contextId, $authorKey);
        
        if (empty($matchingIds)) {
            return null;
        }
        
        $authors = Repo::author()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();
        
        $bestAuthor = null;
        $maxNameLength = 0;
        
        foreach ($authors as $author) {
            if (in_array($author->getId(), $matchingIds)) {
                $nameLength = strlen($author->getFullName());
                
                if ($nameLength > $maxNameLength) {
                    $bestAuthor = $author;
                    $maxNameLength = $nameLength;
                }
            }
        }
        
        if (!$bestAuthor) {
            return null;
        }
        
        return [
            'id' => $bestAuthor->getId(),
            'fullName' => $bestAuthor->getFullName(),
            'email' => $bestAuthor->getEmail(),
            'affiliation' => $bestAuthor->getLocalizedAffiliation(),
            'country' => $bestAuthor->getCountry(),
            'orcid' => $bestAuthor->getOrcid()
        ];
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
        int $contextId
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
                'section' => $this->getSectionName($publication)
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
     * Get sections distribution
     */
    private function getSectionsDistribution(array $submissions): array
    {
        $sections = [];
        
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $sectionId = $publication->getData('sectionId');
            if (!$sectionId) continue;
            
            $section = Repo::section()->get($sectionId);
            if (!$section) continue;
            
            $sectionTitle = $section->getLocalizedTitle();
            
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
    
    /**
     * Get section name
     */
    private function getSectionName($publication): ?string
    {
        $sectionId = $publication->getData('sectionId');
        if (!$sectionId) return null;
        
        $section = Repo::section()->get($sectionId);
        return $section ? $section->getLocalizedTitle() : null;
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