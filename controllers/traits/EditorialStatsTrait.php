<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/EditorialStatsTrait.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing editorial workflow HTTP endpoints.
 *
 * Contains handlers for submission flow, decision timing,
 * and acceptance-to-publication metrics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\Cache;

trait EditorialStatsTrait
{
    /**
     * Get editorial statistics (monthly submissions overview)
     */
    public function editorial(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "editorial_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->editorialService->getStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in editorial", $e);
            $this->outputError('Error loading editorial statistics', 500);
        }
    }

    /**
     * Get editorial annual statistics
     */
    public function editorialAnnual(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "editorial_annual_{$contextId}";
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL,
                fn() => $this->editorialService->getAnnualStats($contextId)
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in editorialAnnual", $e);
            $this->outputError('Error loading annual editorial statistics', 500);
        }
    }

    /**
     * Get first decision statistics
     */
    public function firstDecision(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "first_decision_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->decisionService->getFirstDecisionStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in firstDecision", $e);
            $this->outputError('Error loading first decision statistics', 500);
        }
    }

    /**
     * Get acceptance to publication statistics
     */
    public function acceptancePublication(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year);
            
            $cacheKey = sprintf(
                "acceptance_publication_%d_%s_%s",
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );
            
            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 2,
                fn() => $this->decisionService->getAcceptancePublicationStats(
                    $contextId,
                    $dateRanges['start'],
                    $dateRanges['end']
                )
            );
            
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in acceptancePublication", $e);
            $this->outputError('Error loading acceptance-publication statistics', 500);
        }
    }
}