<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/EnrichedStatsTrait.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing OpenAlex-enriched statistics HTTP endpoints.
 *
 * Contains handlers for citation metrics, thematic profiles,
 * open access statistics, and collaboration metrics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use Illuminate\Support\Facades\Cache;

trait EnrichedStatsTrait
{
    /**
     * Get total enriched statistics (combines local + OpenAlex)
     */
    public function totalEnriched(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "total_enriched_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 12,
                fn() => $this->enrichedService->getEnrichedContextStats($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in totalEnriched", $e);
            $this->outputError('Error loading enriched statistics', 500);
        }
    }

    /**
     * Get external citations count
     */
    public function externalCitations(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "external_citations_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 24,
                fn() => $this->openalexService->enrichContextStatistics($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in externalCitations", $e);
            $this->outputError('Error loading external citations', 500);
        }
    }

    /**
     * Get top cited articles from OpenAlex
     */
    public function topCitedArticles(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 20, 100);
            $cacheKey = "top_cited_{$contextId}_{$limit}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 48,
                fn() => $this->enrichedService->getTopCitedArticles($request, $contextId, $limit)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topCitedArticles", $e);
            $this->outputError('Error loading top cited articles', 500);
        }
    }

    /**
     * Get research topics distribution
     */
    public function topicsDistribution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "topics_distribution_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 48,
                fn() => $this->enrichedService->getThematicProfile($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topicsDistribution", $e);
            $this->outputError('Error loading topics distribution', 500);
        }
    }

    /**
     * Get funding sources
     */
    public function fundingSources(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "funding_sources_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 48,
                fn() => $this->enrichedService->getFundingSources($contextId, 20)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in fundingSources", $e);
            $this->outputError('Error loading funding sources', 500);
        }
    }

    /**
     * Get collaboration metrics
     */
    public function collaboration(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "collaboration_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 48,
                fn() => $this->enrichedService->getCollaborationMetrics($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in collaboration", $e);
            $this->outputError('Error loading collaboration metrics', 500);
        }
    }

    /**
     * Get citation timeline for specific article
     */
    public function articleCitationTimeline(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        $submissionId = InputValidator::validateSubmissionId($request, $request->getUserVar('submissionId'));
        if (!$submissionId) {
            $this->outputError('Invalid or unauthorized submission ID', 400);
            return;
        }

        $submission = \APP\facades\Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();

        if (!$publication) {
            $this->outputError('Publication not found', 404);
            return;
        }

        $doi = $publication->getDoi();
        if (!$doi) {
            $this->outputJson(['timeline' => [], 'message' => 'No DOI found']);
            return;
        }

        try {
            $metrics = $this->openalexService->getWorkMetrics($doi);

            if (!$metrics) {
                $this->outputJson(['timeline' => [], 'message' => 'Not found in OpenAlex']);
                return;
            }

            $this->outputJson([
                'timeline' => $metrics['counts_by_year'],
                'total_citations' => $metrics['cited_by_count'],
                'fwci' => $metrics['fwci']
            ]);
        } catch (\Exception $e) {
            Logger::error("Error in articleCitationTimeline", $e);
            $this->outputError('Error loading citation timeline', 500);
        }
    }

    /**
     * Get citations by year
     */
    public function citationsByYear(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "citations_by_year_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_INTERNAL * 48,
                fn() => $this->enrichedService->getAnnualCitationMetrics($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citationsByYear", $e);
            $this->outputError('Error loading citations by year', 500);
        }
    }

    /**
     * Get top cited articles
     */
    public function topCited(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 20, 100);
            $year = InputValidator::validateYear($request->getUserVar('year'));

            $data = $this->enrichedService->getTopCitedArticles(
                $request,
                $context->getId(),
                $limit,
                $year
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topCited", $e);
            $this->outputError('Error loading top cited articles', 500);
        }
    }

    /**
     * Get citation evolution
     */
    public function citationEvolution(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $data = $this->enrichedService->getCitationEvolution($context->getId());
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citationEvolution", $e);
            $this->outputError('Error loading citation evolution', 500);
        }
    }

    /**
     * Get open access statistics
     */
    public function openAccessStats(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $data = $this->enrichedService->getOpenAccessStats($context->getId());
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in openAccessStats", $e);
            $this->outputError('Error loading open access statistics', 500);
        }
    }

    /**
     * Get thematic profile
     */
    public function thematicProfile(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $data = $this->enrichedService->getThematicProfile($context->getId());
            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in thematicProfile", $e);
            $this->outputError('Error loading thematic profile', 500);
        }
    }
    /**
     * Get citations by country (map data)
     */
    public function citationsByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "citations_by_country_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_EXTERNAL,
                fn() => $this->enrichedService->getCitationsByCountry($contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citationsByCountry", $e);
            $this->outputError('Error loading citations by country', 500);
        }
    }

    /**
     * Get citing journals
     */
    public function citingJournals(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "citing_journals_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_EXTERNAL,
                fn() => $this->enrichedService->getCitingJournals($request, $contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citingJournals", $e);
            $this->outputError('Error loading citing journals', 500);
        }
    }

    /**
     * Get citing institutions
     */
    public function citingInstitutions(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey = "citing_institutions_{$contextId}";

            $data = Cache::remember(
                $cacheKey,
                PublicStatsConstants::CACHE_TTL_EXTERNAL,
                fn() => $this->enrichedService->getCitingInstitutions($request, $contextId)
            );

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citingInstitutions", $e);
            $this->outputError('Error loading citing institutions', 500);
        }
    }
}