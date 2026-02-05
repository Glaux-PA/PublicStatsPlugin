<?php
/**
 * @file plugins/generic/publicStats/helpers/StatsAggregationHelper.php
 *
 * @class StatsAggregationHelper
 * @brief Helper class for aggregating statistics by different entities (sections, issues, etc.)
 *        Eliminates code duplication across multiple services.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\helpers;

use APP\facades\Repo;
use PKP\submission\Submission;

class StatsAggregationHelper
{
    /**
     * Aggregate statistics by section
     * 
     */
    public static function aggregateBySection(
        iterable $downloadRecords,
        iterable $viewRecords,
        int $contextId
    ): array {
        return self::aggregateByEntity(
            $downloadRecords,
            $viewRecords,
            $contextId,
            'section',
            function($publication) {
                return $publication->getData('sectionId');
            },
            function($sectionId, $contextId) {
                $section = Repo::section()->get($sectionId);
                if (!$section || $section->getData('contextId') !== $contextId) {
                    return null;
                }
                return [
                    'sectionId' => $sectionId,
                    'sectionTitle' => $section->getLocalizedTitle()
                ];
            }
        );
    }

    /**
     * Aggregate statistics by issue
     * 
     */
    public static function aggregateByIssue(
        iterable $downloadRecords,
        iterable $viewRecords,
        int $contextId
    ): array {
        return self::aggregateByEntity(
            $downloadRecords,
            $viewRecords,
            $contextId,
            'issue',
            function($publication) {
                return $publication->getData('issueId');
            },
            function($issueId, $contextId) {
                $issue = Repo::issue()->get($issueId);
                if (!$issue || $issue->getData('journalId') !== $contextId) {
                    return null;
                }
                return [
                    'issueId' => $issueId,
                    'issueTitle' => $issue->getIssueIdentification()
                ];
            }
        );
    }

    /**
     * Generic aggregation by any entity (section, issue, author, etc.)
     * 
     */
    public static function aggregateByEntity(
        iterable $downloadRecords,
        iterable $viewRecords,
        int $contextId,
        string $entityType,
        callable $entityIdExtractor,
        callable $entityInfoBuilder
    ): array {
        $submissionIds = self::extractUniqueSubmissionIds($downloadRecords, $viewRecords);
        
        if (empty($submissionIds)) {
            return [];
        }


        $submissionToEntityMap = self::buildSubmissionToEntityMap(
            $submissionIds,
            $contextId,
            $entityIdExtractor
        );

        if (empty($submissionToEntityMap)) {
            return [];
        }

        $entityIds = array_unique(array_values($submissionToEntityMap));
        $validEntities = self::validateEntities($entityIds, $contextId, $entityInfoBuilder);

        if (empty($validEntities)) {
            return [];
        }

        $entityStats = self::aggregateMetrics(
            $downloadRecords,
            $submissionToEntityMap,
            $validEntities,
            'downloads'
        );

        $entityStats = self::aggregateMetrics(
            $viewRecords,
            $submissionToEntityMap,
            $entityStats,
            'views'
        );

        return self::prepareFinalResults($entityStats);
    }

    /**
     * Extract unique submission IDs from all records
     * 
     */
    private static function extractUniqueSubmissionIds(
        iterable $downloadRecords,
        iterable $viewRecords
    ): array {
        $submissionIds = [];

        foreach ($downloadRecords as $record) {
            if (isset($record->submission_id)) {
                $submissionIds[$record->submission_id] = true;
            }
        }

        foreach ($viewRecords as $record) {
            if (isset($record->submission_id)) {
                $submissionIds[$record->submission_id] = true;
            }
        }

        return array_keys($submissionIds);
    }

    /**
     * Build mapping from submission ID to entity ID
     * 
     */
    private static function buildSubmissionToEntityMap(
        array $submissionIds,
        int $contextId,
        callable $entityIdExtractor
    ): array {
        $neededIds = array_flip($submissionIds);
        
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        $submissionToEntityMap = [];

        foreach ($submissions as $submission) {
            $submissionId = $submission->getId();
            
            if (!isset($neededIds[$submissionId])) {
                continue;
            }
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }

            $entityId = $entityIdExtractor($publication);
            
            if ($entityId !== null) {
                $submissionToEntityMap[$submissionId] = $entityId;
            }
        }

        return $submissionToEntityMap;
    }

    /**
     * Validate entities and build entity info map
     * 
     */
    private static function validateEntities(
        array $entityIds,
        int $contextId,
        callable $entityInfoBuilder
    ): array {
        $validEntities = [];

        foreach ($entityIds as $entityId) {
            $entityInfo = $entityInfoBuilder($entityId, $contextId);
            
            if ($entityInfo !== null) {
                $validEntities[$entityId] = $entityInfo;
            }
        }

        return $validEntities;
    }

    /**
     * Aggregate metrics (downloads or views) by entity
     * 
     */
    private static function aggregateMetrics(
        iterable $records,
        array $submissionToEntityMap,
        array $entityStats,
        string $metricType
    ): array {
        foreach ($records as $record) {
            if (!isset($record->submission_id)) {
                continue;
            }

            $submissionId = $record->submission_id;

            if (!isset($submissionToEntityMap[$submissionId])) {
                continue;
            }

            $entityId = $submissionToEntityMap[$submissionId];

            if (!isset($entityStats[$entityId])) {
                continue;
            }

            if (!isset($entityStats[$entityId][$metricType])) {
                $entityStats[$entityId][$metricType] = 0;
            }

            $entityStats[$entityId][$metricType] += $record->metric ?? 0;

            if (!isset($entityStats[$entityId]['_counted_submissions'])) {
                $entityStats[$entityId]['_counted_submissions'] = [];
            }
            
            if (!in_array($submissionId, $entityStats[$entityId]['_counted_submissions'])) {
                $entityStats[$entityId]['_counted_submissions'][] = $submissionId;
            }
        }

        return $entityStats;
    }

    /**
     * Prepare final results with totals and article counts
     */
    private static function prepareFinalResults(array $entityStats): array
    {
        $results = [];

        foreach ($entityStats as $entityId => $stats) {
            $downloads = $stats['downloads'] ?? 0;
            $views = $stats['views'] ?? 0;
            
            $articleCount = isset($stats['_counted_submissions']) 
                ? count($stats['_counted_submissions']) 
                : 0;

            unset($stats['_counted_submissions']);

            $result = array_merge($stats, [
                'downloads' => $downloads,
                'views' => $views,
                'total' => $downloads + $views,
                'articleCount' => $articleCount
            ]);

            $results[] = $result;
        }

        usort($results, fn($a, $b) => $b['total'] <=> $a['total']);

        return $results;
    }

    /**
     * Aggregate statistics by a single metric type (for backward compatibility)
     * 
     */
    public static function aggregateSingleMetric(
        iterable $records,
        int $contextId,
        string $metricType,
        callable $entityIdExtractor,
        callable $entityInfoBuilder
    ): array {
        $emptyRecords = [];
        
        if ($metricType === 'downloads') {
            return self::aggregateByEntity(
                $records,
                $emptyRecords,
                $contextId,
                'entity',
                $entityIdExtractor,
                $entityInfoBuilder
            );
        } else {
            return self::aggregateByEntity(
                $emptyRecords,
                $records,
                $contextId,
                'entity',
                $entityIdExtractor,
                $entityInfoBuilder
            );
        }
    }

    /**
     * Count submissions by entity (without metrics)
     * Useful for editorial statistics
     */
    public static function countByEntity(
        iterable $submissions,
        callable $entityIdExtractor,
        callable $entityInfoBuilder,
        int $contextId
    ): array {
        $entityCounts = [];

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }

            $entityId = $entityIdExtractor($publication);
            if ($entityId === null) {
                continue;
            }

            if (!isset($entityCounts[$entityId])) {
                $entityInfo = $entityInfoBuilder($entityId, $contextId);
                if ($entityInfo === null) {
                    continue;
                }
                $entityCounts[$entityId] = array_merge($entityInfo, ['count' => 0]);
            }

            $entityCounts[$entityId]['count']++;
        }

        $results = array_values($entityCounts);
        usort($results, fn($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * Optimized version: Aggregate by entity when you already have submissions loaded
     * Avoids re-loading submissions from database
     */
    public static function aggregateWithPreloadedSubmissions(
        iterable $downloadRecords,
        iterable $viewRecords,
        iterable $submissions,
        int $contextId,
        callable $entityIdExtractor,
        callable $entityInfoBuilder
    ): array {
        $submissionIds = self::extractUniqueSubmissionIds($downloadRecords, $viewRecords);
        
        if (empty($submissionIds)) {
            return [];
        }

        $neededIds = array_flip($submissionIds);
        
        $submissionToEntityMap = [];
        
        foreach ($submissions as $submission) {
            $submissionId = $submission->getId();
            
            if (!isset($neededIds[$submissionId])) {
                continue;
            }
            
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }

            $entityId = $entityIdExtractor($publication);
            
            if ($entityId !== null) {
                $submissionToEntityMap[$submissionId] = $entityId;
            }
        }

        if (empty($submissionToEntityMap)) {
            return [];
        }

        $entityIds = array_unique(array_values($submissionToEntityMap));
        $validEntities = self::validateEntities($entityIds, $contextId, $entityInfoBuilder);

        if (empty($validEntities)) {
            return [];
        }

        $entityStats = self::aggregateMetrics(
            $downloadRecords,
            $submissionToEntityMap,
            $validEntities,
            'downloads'
        );

        $entityStats = self::aggregateMetrics(
            $viewRecords,
            $submissionToEntityMap,
            $entityStats,
            'views'
        );

        return self::prepareFinalResults($entityStats);
    }
}