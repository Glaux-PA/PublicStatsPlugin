<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/AuthorReviewerStatsTrait.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing author and reviewer statistics HTTP endpoints.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Cache;

trait AuthorReviewerStatsTrait
{
    public function authorsByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('authors-by-country', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "authors_by_country_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorReviewerService->getAuthorsByCountry($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in authorsByCountry", $e);
            $this->outputError('Error loading authors by country', 500);
        }
    }

    public function authorsByInstitution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('authors-by-institution', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "authors_by_institution_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorReviewerService->getAuthorsByInstitution($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in authorsByInstitution", $e);
            $this->outputError('Error loading authors by institution', 500);
        }
    }

    public function reviewersByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('reviewers-by-country', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "reviewers_by_country_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorReviewerService->getReviewersByCountry($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in reviewersByCountry", $e);
            $this->outputError('Error loading reviewers by country', 500);
        }
    }

    public function reviewersByInstitution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('reviewers-by-institution', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "reviewers_by_institution_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorReviewerService->getReviewersByInstitution($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in reviewersByInstitution", $e);
            $this->outputError('Error loading reviewers by institution', 500);
        }
    }

    public function reviewerList(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('reviewer-list', $context)) return;

        try {
            $contextId = $context->getId();
            $year      = InputValidator::validateYear($request->getUserVar('year'));
            $yearInt   = $year !== null ? (int) $year : null;
            $cacheKey  = "reviewer_list_{$contextId}_" . ($year ?? 'all');

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorReviewerService->getReviewerList($contextId, $yearInt)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in reviewerList", $e);
            $this->outputError('Error loading reviewer list', 500);
        }
    }

    public function authorsList(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('author-individual-stats', $context)) return;

        try {
            $contextId = $context->getId();
            $minPublications = InputValidator::validatePositiveInt(
                $request->getUserVar('minPublications'),
                default: 1,
                min: 1,
                max: 100
            );
            
            $cacheKey = "authors_list_{$contextId}_{$minPublications}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorStatsService->getAuthorsForContext($contextId, $minPublications)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in authorsList", $e);
            $this->outputError('Error loading authors list', 500);
        }
    }

    public function authorStats(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('author-individual-stats', $context)) return;

        $authorKey = InputValidator::validateAuthorKey($request->getUserVar('authorKey'));
        if (!$authorKey) {
            $this->outputError('Invalid author key', 400);
            return;
        }
        
        $authorKeySanitized = InputValidator::sanitizeForCacheKey($authorKey);

        try {
            $contextId = $context->getId();
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "author_stats_%d_%s_%s_%s",
                $contextId,
                $authorKeySanitized,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->authorStatsService->getAuthorStats(
                    $request,
                    $contextId,
                    $authorKey,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in authorStats", $e);
            $this->outputError('Error loading author statistics', 500);
        }
    }

    public function authorsListForStats(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('author-individual-stats', $context)) return;

        try {
            $contextId = $context->getId();
            $minPublications = 1;
            
            $cacheKey = "authors_list_stats_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 24,
                fn() => $this->authorStatsService->getAuthorsForContext($contextId, $minPublications)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in authorsListForStats", $e);
            $this->outputError('Error loading authors list for statistics', 500);
        }
    }
}