<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/EnrichedStatsTrait.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing OpenAlex-enriched statistics HTTP endpoints.
 *
 * Contains handlers for citation metrics, thematic profiles, open access
 * statistics, citing journals/institutions, and citations by country.
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

            // Don't use Cache::remember — it would freeze any is_computing placeholder.
            $data = Cache::get($cacheKey);
            if ($data === null) {
                $data = $this->enrichedService->getEnrichedContextStats($contextId);
                if (empty($data['external']['is_computing'])) {
                    Cache::put($cacheKey, $data, PublicStatsConstants::CACHE_TTL_INTERNAL * 12);
                }
            }

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in totalEnriched", $e);
            $this->outputError('Error loading enriched statistics', 500);
        }
    }

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

            // Don't use Cache::remember — it would freeze any is_computing placeholder.
            $data = Cache::get($cacheKey);
            if ($data === null) {
                $data = $this->enrichedService->getExternalEnrichmentStats($contextId);
                if (empty($data['is_computing'])) {
                    Cache::put($cacheKey, $data, PublicStatsConstants::CACHE_TTL_INTERNAL * 24);
                }
            }

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in externalCitations", $e);
            $this->outputError('Error loading external citations', 500);
        }
    }

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

            // The service caches via the chunked state itself; wrapping it in
            // Cache::remember would freeze the "is_computing" placeholder.
            $data = $this->enrichedService->getTopCitedArticles($request, $contextId, $limit);

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in topCitedArticles", $e);
            $this->outputError('Error loading top cited articles', 500);
        }
    }

    public function topicsDistribution(array $args, PKPRequest $request): void
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
            Logger::error("Error in topicsDistribution", $e);
            $this->outputError('Error loading topics distribution', 500);
        }
    }

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
    public function citationsByCountry(array $args, PKPRequest $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return;
        }

        try {
            $contextId = $context->getId();
            $cacheKey  = "citations_by_country_{$contextId}";

            // Manual get/put: skip the cache when the service returns is_computing.
            $data = Cache::get($cacheKey);
            if ($data === null) {
                $data = $this->enrichedService->getCitationsByCountry($contextId);
                if (is_array($data) && empty($data['is_computing'])) {
                    Cache::put($cacheKey, $data, PublicStatsConstants::CACHE_TTL_EXTERNAL);
                }
            }

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citationsByCountry", $e);
            $this->outputError('Error loading citations by country', 500);
        }
    }

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

            // Manual get/put: skip the cache when the service returns is_computing.
            $data = Cache::get($cacheKey);
            if ($data === null) {
                $data = $this->enrichedService->getCitingJournals($request, $contextId);
                if ($data !== null && empty($data['is_computing'])) {
                    Cache::put($cacheKey, $data, PublicStatsConstants::CACHE_TTL_EXTERNAL);
                }
            }

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citingJournals", $e);
            $this->outputError('Error loading citing journals', 500);
        }
    }

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

            $data = Cache::get($cacheKey);
            if ($data === null) {
                $data = $this->enrichedService->getCitingInstitutions($request, $contextId);
                if ($data !== null && empty($data['is_computing'])) {
                    Cache::put($cacheKey, $data, PublicStatsConstants::CACHE_TTL_EXTERNAL);
                }
            }

            $this->outputJson($data);
        } catch (\Exception $e) {
            Logger::error("Error in citingInstitutions", $e);
            $this->outputError('Error loading citing institutions', 500);
        }
    }
}