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

    /**
     * Sidebar section groups (used for form headers and group-level collapsing).
     */
    public const SECTION_GROUPS = [
        'general'   => 'plugins.generic.publicStats.settings.section.general',
        'editorial' => 'plugins.generic.publicStats.settings.section.editorial',
        'reach'     => 'plugins.generic.publicStats.settings.section.reach',
        'impact'    => 'plugins.generic.publicStats.settings.section.impact',
    ];

    /**
     * All subsections per group: content-section div id → sidebar label key.
     * Order here matches the sidebar order.
     */
    public const SUBSECTIONS = [
        'general' => [
            'monthly-trends'          => 'plugins.generic.publicStats.monthlyTrends',
            'annual-trends'           => 'plugins.generic.publicStats.annualTrends',
            'general-downloads'       => 'plugins.generic.publicStats.contributionsDownloads',
            'general-views'           => 'plugins.generic.publicStats.contributionsViews',
            'general-sections'        => 'plugins.generic.publicStats.sections',
            'general-issues'          => 'plugins.generic.publicStats.issues',
            'general-languages'       => 'plugins.generic.publicStats.languageDistribution',
            'geographic-distribution' => 'plugins.generic.publicStats.geographicDistribution',
        ],
        'editorial' => [
            'author-individual-stats'      => 'plugins.generic.publicStats.authorIndividualStats',
            'editorial-submissions'        => 'plugins.generic.publicStats.monthlyContributions',
            'editorial-annual'             => 'plugins.generic.publicStats.annualContributions',
            'authors-by-country'           => 'plugins.generic.publicStats.authorsByCountry',
            'authors-by-institution'       => 'plugins.generic.publicStats.authorsByInstitution',
            'reviewers-by-country'         => 'plugins.generic.publicStats.reviewersByCountry',
            'reviewers-by-institution'     => 'plugins.generic.publicStats.reviewersByInstitution',
            'first-decision-stats'         => 'plugins.generic.publicStats.firstDecisionDays',
            'acceptance-publication-stats' => 'plugins.generic.publicStats.acceptancePublicationDays',
        ],
        'reach' => [
            'recent-downloads' => 'plugins.generic.publicStats.mostDownloaded60Days',
            'recent-views'     => 'plugins.generic.publicStats.mostViewed60Days',
        ],
        'impact' => [
            'top-cited'          => 'plugins.generic.publicStats.topCitedArticles',
            'citation-evolution' => 'plugins.generic.publicStats.citationEvolution',
            'open-access-stats'  => 'plugins.generic.publicStats.openAccessStats',
            'thematic-profile'   => 'plugins.generic.publicStats.thematicProfile',
            'citations-map'      => 'plugins.generic.publicStats.citationsByCountry',
            'citing-journals'    => 'plugins.generic.publicStats.citingJournals',
        ],
    ];
}
