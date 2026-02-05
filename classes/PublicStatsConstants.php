<?php

/**
 * @file plugins/generic/publicStats/classes/PublicStatsConstants.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsConstants
 * @ingroup plugins_generic_publicStats
 *
 * @brief Centralized constants for the Public Statistics plugin.
 *
 * This class defines configuration values used throughout the plugin,
 * including date boundaries, cache durations, and API rate limits.
 * Centralizing these values ensures consistency and simplifies maintenance.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

class PublicStatsConstants
{
    /**
     * Minimum year for statistics queries.
     * Prevents excessive data processing for historical records
     * and establishes a reasonable lower bound for statistics display.
     */
    public const MIN_YEAR = 2010;

    /**
     * Cache TTL for internal statistics in seconds (1 hour).
     * Used for frequently changing journal-specific data such as
     * download counts and view metrics.
     */
    public const CACHE_TTL_INTERNAL = 3600;

    /**
     * Cache TTL for external API data in seconds (7 days).
     * Used for OpenAlex data which updates less frequently.
     * Longer TTL reduces API calls and improves performance.
     */
    public const CACHE_TTL_EXTERNAL = 604800;

    /**
     * Maximum submissions to process in resource-intensive operations.
     * Prevents timeout and memory exhaustion in large journals.
     * Applied to operations like batch statistics aggregation.
     */
    public const MAX_SUBMISSIONS_TO_PROCESS = 1000;

    /**
     * Maximum submissions for OpenAlex API enrichment.
     * Lower than internal limit due to external API rate constraints.
     * Balances data completeness with API usage limits.
     */
    public const MAX_OPENALEX_REQUESTS = 500;

    /**
     * OpenAlex API rate limit delay in microseconds.
     * 100,000 microseconds = 100ms, allowing ~10 requests per second.
     * Ensures compliance with OpenAlex polite pool rate limits.
     */
    public const OPENALEX_RATE_LIMIT_DELAY = 100000;

    /**
     * OpenAlex /text endpoint rate limit delay in microseconds.
     * 1,000,000 microseconds = 1 second, allowing 1 request per second.
     * The /text endpoint has stricter rate limits than other endpoints.
     */
    public const OPENALEX_TEXT_RATE_LIMIT_DELAY = 1000000;
}
