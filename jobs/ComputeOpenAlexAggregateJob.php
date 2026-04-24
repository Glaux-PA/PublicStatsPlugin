<?php

/**
 * @file plugins/generic/publicStats/jobs/ComputeOpenAlexAggregateJob.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ComputeOpenAlexAggregateJob
 *
 * @brief Background job that pre-computes expensive OpenAlex aggregates
 *        (context enrichment, citing journals, citing institutions) and
 *        stores the result in cache so HTTP requests can return immediately
 *        instead of blocking on the OpenAlex API for several minutes.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\jobs;

use APP\plugins\generic\publicStats\services\OpenAlexService;
use Illuminate\Support\Facades\Cache;
use PKP\jobs\BaseJob;

class ComputeOpenAlexAggregateJob extends BaseJob
{
    public const TYPE_ENRICH_CONTEXT = 'enrich_context';
    public const TYPE_CITING_JOURNALS = 'citing_journals';
    public const TYPE_CITING_INSTITUTIONS = 'citing_institutions';

    /**
     * These aggregates may walk hundreds of OpenAlex requests sequentially,
     * so the queue timeout is generous. Keep it below the queue worker's
     * own timeout to ensure a clean failure rather than a killed worker.
     */
    public int $timeout = 600;

    /** @var int Must be untyped because BaseJob declares $tries without a type. */
    public $tries = 2;

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
        $service = app(OpenAlexService::class);

        try {
            $data = match ($this->type) {
                self::TYPE_ENRICH_CONTEXT => $service->enrichContextStatisticsSync($this->contextId),
                self::TYPE_CITING_JOURNALS => $service->getCitingJournalsSync($this->contextId),
                self::TYPE_CITING_INSTITUTIONS => $service->getCitingInstitutionsSync($this->contextId),
                default => throw new \InvalidArgumentException("Unknown aggregate type: {$this->type}"),
            };

            Cache::put(
                OpenAlexService::cacheKeyFor($this->type, $this->contextId),
                $data,
                OpenAlexService::aggregateCacheTtl()
            );
        } finally {
            // Always clear the lock so a failure doesn't block retries indefinitely.
            Cache::forget(OpenAlexService::lockKeyFor($this->type, $this->contextId));
        }
    }
}
