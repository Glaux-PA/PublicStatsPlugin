<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/AuthorReviewerStatsTrait.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing author and reviewer statistics HTTP endpoints.
 *
 * Contains handlers for geographic distribution, institutional
 * affiliations, and individual author statistics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Cache;

trait AuthorReviewerStatsTrait
{
    /**
     * Get authors by country
     */
    public function authorsByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

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
            error_log("Error in authorsByCountry: " . $e->getMessage());
            $this->outputError('Error loading authors by country', 500);
        }
    }

    /**
     * Get authors by institution
     */
    public function authorsByInstitution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

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
            error_log("Error in authorsByInstitution: " . $e->getMessage());
            $this->outputError('Error loading authors by institution', 500);
        }
    }

    /**
     * Get reviewers by country
     */
    public function reviewersByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

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
            error_log("Error in reviewersByCountry: " . $e->getMessage());
            $this->outputError('Error loading reviewers by country', 500);
        }
    }

    /**
     * Get reviewers by institution
     */
    public function reviewersByInstitution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

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
            error_log("Error in reviewersByInstitution: " . $e->getMessage());
            $this->outputError('Error loading reviewers by institution', 500);
        }
    }

    /**
     * Get authors list (for dropdown/selection)
     */
    public function authorsList(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $minPublications = InputValidator::validatePositiveInt(
                $request->getUserVar('minPublications'),
                1,  // default
                1,  // min
                100 // max
            );
            
            $cacheKey = "authors_list_{$contextId}_{$minPublications}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->authorStatsService->getAuthorsForContext($contextId, $minPublications)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            error_log("Error in authorsList: " . $e->getMessage());
            $this->outputError('Error loading authors list', 500);
        }
    }

    /**
     * Get statistics for specific author
     */
    public function authorStats(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $authorKey = InputValidator::validateAuthorKey($request->getUserVar('authorKey'));
        if (!$authorKey) {
            $this->outputError('Invalid author key', 400);
            return;
        }
        
        $authorKeySanitized = InputValidator::sanitizeForCacheKey($authorKey);

        try {
            $contextId = $context->getId();
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
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
            error_log("Error in authorStats: " . $e->getMessage());
            $this->outputError('Error loading author statistics', 500);
        }
    }

    /**
     * Get authors list for stats page (with filters)
     */
    public function authorsListForStats(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $minPublications = 1; // Minimum 1 publication required
            
            $cacheKey = "authors_list_stats_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 24,
                fn() => $this->authorStatsService->getAuthorsForContext($contextId, $minPublications)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            error_log("Error in authorsListForStats: " . $e->getMessage());
            $this->outputError('Error loading authors list for statistics', 500);
        }
    }
}