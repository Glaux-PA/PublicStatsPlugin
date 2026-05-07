<?php

/**
 * @file plugins/generic/publicStats/jobs/ComputeOpenAlexAggregateJob.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ComputeOpenAlexAggregateJob
 * @ingroup plugins_generic_publicStats
 *
 * @brief Pre-computes OpenAlex aggregates in the background so HTTP requests
 *        don't block on a long chain of external API calls. Two modes:
 *        single-shot (legacy types) and chunked (the four enriched stats).
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\jobs;

use APP\plugins\generic\publicStats\services\EnrichedStatsService;
use APP\plugins\generic\publicStats\services\OpenAlexService;
use Illuminate\Support\Facades\Cache;
use PKP\jobs\BaseJob;

class ComputeOpenAlexAggregateJob extends BaseJob
{
    public const TYPE_ENRICH_CONTEXT = 'enrich_context';
    public const TYPE_CITING_JOURNALS = 'citing_journals';
    public const TYPE_CITING_INSTITUTIONS = 'citing_institutions';
    public const TYPE_OPEN_ACCESS_STATS = 'open_access_stats';
    public const TYPE_THEMATIC_PROFILE = 'thematic_profile';
    public const TYPE_CITATION_EVOLUTION = 'citation_evolution';
    public const TYPE_TOP_CITED = 'top_cited';

    public int $timeout = 600;

    /** @var int Untyped because BaseJob declares $tries without a type. */
    public $tries = 2;

    private const CHUNKED_TYPES = [
        self::TYPE_ENRICH_CONTEXT,
        self::TYPE_OPEN_ACCESS_STATS,
        self::TYPE_THEMATIC_PROFILE,
        self::TYPE_CITATION_EVOLUTION,
        self::TYPE_TOP_CITED,
    ];

    protected int $contextId;
    protected string $type;

    public function __construct(int $contextId, string $type)
    {
        parent::__construct();

        $this->contextId = $contextId;
        $this->type = $type;
    }

    public function handle(): void
    {
        $openAlexService = app(OpenAlexService::class);
        $enrichedService = app(EnrichedStatsService::class);

        try {
            if (in_array($this->type, self::CHUNKED_TYPES, true)) {
                $this->handleChunked($openAlexService, $enrichedService);
                return;
            }

            $data = match ($this->type) {
                self::TYPE_CITING_JOURNALS     => $openAlexService->getCitingJournalsSync($this->contextId),
                self::TYPE_CITING_INSTITUTIONS => $openAlexService->getCitingInstitutionsSync($this->contextId),
                default => throw new \InvalidArgumentException("Unknown aggregate type: {$this->type}"),
            };

            Cache::put(
                OpenAlexService::cacheKeyFor($this->type, $this->contextId),
                $data,
                OpenAlexService::aggregateCacheTtl()
            );
        } finally {
            Cache::forget(OpenAlexService::lockKeyFor($this->type, $this->contextId));
        }
    }

    private function handleChunked(
        OpenAlexService $openAlexService,
        EnrichedStatsService $enrichedService
    ): void {
        $state = $openAlexService->getChunkedState($this->type, $this->contextId)
            ?? ['processed' => 0, 'total' => 0, 'accumulator' => null, 'is_complete' => false];

        // A duplicate chunk job may arrive after the chain already finalized.
        if (!empty($state['is_complete'])) {
            return;
        }

        $newState = match ($this->type) {
            self::TYPE_ENRICH_CONTEXT    => $enrichedService->enrichContextStatisticsChunk($this->contextId, $state),
            self::TYPE_OPEN_ACCESS_STATS => $enrichedService->getOpenAccessStatsChunk($this->contextId, $state),
            self::TYPE_THEMATIC_PROFILE  => $enrichedService->getThematicProfileChunk($this->contextId, $state),
            self::TYPE_CITATION_EVOLUTION => $enrichedService->getCitationEvolutionChunk($this->contextId, $state),
            self::TYPE_TOP_CITED         => $enrichedService->getTopCitedArticlesChunk($this->contextId, $state),
        };

        $openAlexService->putChunkedState($this->type, $this->contextId, $newState);

        if (empty($newState['is_complete'])) {
            $openAlexService->dispatchNextChunk($this->type, $this->contextId);
        }
    }
}
