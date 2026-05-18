<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/ArticleStatsTrait.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing article statistics HTTP endpoints.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Cache;

trait ArticleStatsTrait
{
    public function topDownloaded(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('general-downloads', $context)) return;

        $year = InputValidator::validateYear($request->getUserVar('year'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "top_downloaded_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->articleService->getTopDownloadedArticles(
                    $request,
                    $contextId,
                    20,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topDownloaded", $e);
            $this->outputError('Error loading top downloaded articles', 500);
        }
    }

    public function topViewed(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('general-views', $context)) return;

        $year = InputValidator::validateYear($request->getUserVar('year'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "top_viewed_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->articleService->getTopViewedArticles(
                    $request,
                    $contextId,
                    20,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topViewed", $e);
            $this->outputError('Error loading top viewed articles', 500);
        }
    }

    public function recentDownloaded(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('recent-downloads', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "recent_downloaded_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL / 2, // 30 minutes
                fn() => $this->articleService->getRecentTopDownloadedArticles(
                    $request,
                    $contextId,
                    20
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in recentDownloaded", $e);
            $this->outputError('Error loading recent downloads', 500);
        }
    }

    public function recentViewed(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('recent-views', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "recent_viewed_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL / 2,
                fn() => $this->articleService->getRecentTopViewedArticles(
                    $request,
                    $contextId,
                    20
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in recentViewed", $e);
            $this->outputError('Error loading recent views', 500);
        }
    }

    public function issues(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('general-issues', $context)) return;

        $year = InputValidator::validateYear($request->getUserVar('year'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "issues_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->issueService->getIssueStats(
                    $request,
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in issues", $e);
            $this->outputError('Error loading issue statistics', 500);
        }
    }

    public function sections(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('general-sections', $context)) return;

        $year = InputValidator::validateYear($request->getUserVar('year'));

        try {
            $contextId = $context->getId();
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "sections_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->sectionService->getSectionStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in sections", $e);
            $this->outputError('Error loading section statistics', 500);
        }
    }

    public function sectionsList(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }
        if (!$this->requireSubsection('general-sections', $context)) return;

        try {
            $contextId = $context->getId();
            $cacheKey = "sections_list_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 24,
                function() use ($contextId) {
                    $sections = \APP\facades\Repo::section()
                        ->getCollector()
                        ->filterByContextIds([$contextId])
                        ->getMany();
                    
                    $result = [];
                    foreach ($sections as $section) {
                        $result[] = [
                            'id' => $section->getId(),
                            'title' => $section->getLocalizedTitle()
                        ];
                    }
                    return $result;
                }
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in sectionsList", $e);
            $this->outputError('Error loading sections list', 500);
        }
    }
}