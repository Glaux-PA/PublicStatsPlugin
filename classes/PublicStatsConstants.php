<?php

/**
 * @file plugins/generic/publicStats/classes/PublicStatsConstants.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsConstants
 * @ingroup plugins_generic_publicStats
 *
 * @brief Centralized constants for the Public Statistics plugin.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

class PublicStatsConstants
{
    /** Lower bound for year-based filters and selectors. */
    public const MIN_YEAR = 2010;

    /** Cache TTL for local stats (1 hour). */
    public const CACHE_TTL_INTERNAL = 3600;

    /** Cache TTL for OpenAlex aggregates (7 days). */
    public const CACHE_TTL_EXTERNAL = 604800;

    /**
     * Submissions processed per chunked OpenAlex job invocation.
     * Sized for async workers (~30-60s per chunk). Drop to 50 if jobs run inline.
     */
    public const MAX_INLINE_JOB_SUBMISSIONS = 100;

    /** Microsecond delay between OpenAlex API calls (~10 req/s, polite-pool safe). */
    public const OPENALEX_RATE_LIMIT_DELAY = 100000;

    public const SECTION_GROUPS = [
        'general'   => 'plugins.generic.publicStats.settings.section.general',
        'editorial' => 'plugins.generic.publicStats.settings.section.editorial',
        'reach'     => 'plugins.generic.publicStats.settings.section.reach',
        'impact'    => 'plugins.generic.publicStats.settings.section.impact',
    ];

    /** Subsections by group: content-section id => sidebar label key. Order = sidebar order. */
    public const SUBSECTIONS = [
        'general' => [
            'monthly-trends'          => 'plugins.generic.publicStats.monthlyTrends',
            'annual-trends'           => 'plugins.generic.publicStats.annualTrends',
            'general-downloads'       => 'plugins.generic.publicStats.contributionsDownloads',
            'general-views'           => 'plugins.generic.publicStats.contributionsViews',
            'general-sections'        => 'plugins.generic.publicStats.sections',
            'general-issues'          => 'plugins.generic.publicStats.issues',
            'general-languages'       => 'plugins.generic.publicStats.languageDistribution',
            'language-trends'         => 'plugins.generic.publicStats.languageTrends',
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
            'reviewer-list'                => 'plugins.generic.publicStats.reviewerList',
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
