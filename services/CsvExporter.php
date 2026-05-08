<?php

/**
 * @file plugins/generic/publicStats/services/CsvExporter.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvExporter
 * @ingroup plugins_generic_publicStats
 *
 * @brief Builds CSV payloads for every statistics export.
 *
 * Pure builder: each method fetches data via the relevant statistics service
 * and returns an associative array with keys 'filename', 'headers' and 'rows'.
 * The HTTP layer (CsvExportTrait) only has to stream that payload out.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use PKP\core\PKPRequest;

class CsvExporter
{
    public function __construct(
        private StatisticsService $statsService,
        private ArticleStatsService $articleService,
        private EditorialStatsService $editorialService,
        private DecisionStatsService $decisionService,
        private AuthorReviewerStatsService $authorReviewerService,
        private IssueStatsService $issueService,
        private SectionStatsService $sectionService,
        private LanguageStatsService $languageService,
        private EnrichedStatsService $enrichedService,
    ) {
    }

    // ========================================
    // Access metrics
    // ========================================

    public function monthly(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->statsService->getMonthlyStats($contextId, $range['start'], $range['end']);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['month'] ?? '',
                $item['downloads']['label'] ?? '',
                $item['downloads']['value'] ?? 0,
                $item['views']['value'] ?? 0,
                $item['total'] ?? 0,
            ];
        }

        return [
            'filename' => 'monthly_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Month', 'Month Label', 'Downloads', 'Views', 'Total'],
            'rows' => $rows,
        ];
    }

    public function annual(int $contextId): array
    {
        $data = $this->statsService->getAnnualStats($contextId);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['year'] ?? '',
                $item['downloads'] ?? 0,
                $item['views'] ?? 0,
                $item['total'] ?? 0,
            ];
        }

        return [
            'filename' => 'annual_stats_' . date('Y-m-d') . '.csv',
            'headers' => ['Year', 'Downloads', 'Views', 'Total'],
            'rows' => $rows,
        ];
    }

    public function countries(int $contextId): array
    {
        $data = $this->statsService->getCountryStatistics($contextId);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['country_code'] ?? '',
                $item['country_name'] ?? '',
                $item['total_access'] ?? 0,
            ];
        }

        return [
            'filename' => 'country_stats_' . date('Y-m-d') . '.csv',
            'headers' => ['Country Code', 'Country Name', 'Total Access'],
            'rows' => $rows,
        ];
    }

    // ========================================
    // Top articles
    // ========================================

    public function topDownloaded(PKPRequest $request, int $contextId, ?string $year, int $limit): array
    {
        $range = $this->dateRanges($year);
        $data = $this->articleService->getTopDownloadedArticles($request, $contextId, $limit, $range['start'], $range['end']);

        return [
            'filename' => 'top_downloaded_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Rank', 'Title', 'Authors', 'Downloads', 'Date Published'],
            'rows' => $this->rankedArticleRows($data, 'downloads'),
        ];
    }

    public function topViewed(PKPRequest $request, int $contextId, ?string $year, int $limit): array
    {
        $range = $this->dateRanges($year);
        $data = $this->articleService->getTopViewedArticles($request, $contextId, $limit, $range['start'], $range['end']);

        return [
            'filename' => 'top_viewed_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Rank', 'Title', 'Authors', 'Views', 'Date Published'],
            'rows' => $this->rankedArticleRows($data, 'views'),
        ];
    }

    public function recentDownloaded(PKPRequest $request, int $contextId, int $limit): array
    {
        $data = $this->articleService->getRecentTopDownloadedArticles($request, $contextId, $limit);

        return [
            'filename' => 'recent_top_downloaded_' . date('Y-m-d') . '.csv',
            'headers' => ['Rank', 'Title', 'Authors', 'Downloads (60 days)', 'Date Published'],
            'rows' => $this->rankedArticleRows($data, 'downloads'),
        ];
    }

    public function recentViewed(PKPRequest $request, int $contextId, int $limit): array
    {
        $data = $this->articleService->getRecentTopViewedArticles($request, $contextId, $limit);

        return [
            'filename' => 'recent_top_viewed_' . date('Y-m-d') . '.csv',
            'headers' => ['Rank', 'Title', 'Authors', 'Views (60 days)', 'Date Published'],
            'rows' => $this->rankedArticleRows($data, 'views'),
        ];
    }

    // ========================================
    // Editorial
    // ========================================

    public function editorial(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->editorialService->getStats($contextId, $range['start'], $range['end']) ?? [];

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['month'] ?? '',
                $item['label'] ?? '',
                $item['received'] ?? 0,
                $item['declined'] ?? 0,
                $item['published'] ?? 0,
                $item['inProcess'] ?? 0,
            ];
        }

        return [
            'filename' => 'editorial_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Month', 'Label', 'Received', 'Declined', 'Published', 'In Process'],
            'rows' => $rows,
        ];
    }

    public function editorialAnnual(int $contextId): array
    {
        $data = $this->editorialService->getAnnualStats($contextId);

        $rows = [];
        foreach ($data as $item) {
            $received = (int)($item['received'] ?? 0);
            $published = (int)($item['published'] ?? 0);
            $acceptanceRate = $received > 0 ? round(($published / $received) * 100, 1) : 0;

            $rows[] = [
                $item['year'] ?? '',
                $received,
                $item['declined'] ?? 0,
                $published,
                $item['inProcess'] ?? 0,
                $acceptanceRate,
            ];
        }

        return [
            'filename' => 'editorial_annual_stats_' . date('Y-m-d') . '.csv',
            'headers' => ['Year', 'Received', 'Declined', 'Published', 'In Process', 'Acceptance Rate (%)'],
            'rows' => $rows,
        ];
    }

    public function firstDecision(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->decisionService->getFirstDecisionStats($contextId, $range['start'], $range['end']);

        $rows = [
            [
                'SUMMARY',
                'Avg Days (with review): ' . ($data['average_days_reviewed'] ?? 0),
                'Avg Days (all): ' . ($data['average_days_all'] ?? 0),
                'Count with review: ' . ($data['count_reviewed'] ?? 0),
                'Total count: ' . ($data['count_all'] ?? 0),
                '',
            ],
            ['', '', '', '', '', ''],
        ];

        foreach (($data['decisions'] ?? []) as $item) {
            $rows[] = [
                $item['submission_id'] ?? '',
                $item['date_submitted'] ?? '',
                $item['date_decided'] ?? '',
                $item['days_to_decision'] ?? 0,
                $item['decision_type'] ?? '',
                ($item['has_review'] ?? false) ? 'Yes' : 'No',
            ];
        }

        return [
            'filename' => 'first_decision_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Submission ID', 'Date Submitted', 'Date Decided', 'Days to Decision', 'Decision Type', 'Had Review'],
            'rows' => $rows,
        ];
    }

    public function acceptancePublication(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->decisionService->getAcceptancePublicationStats($contextId, $range['start'], $range['end']);

        $rows = [
            [
                'SUMMARY',
                'Avg Days (with review): ' . ($data['average_days_reviewed'] ?? 0),
                'Avg Days (all): ' . ($data['average_days_all'] ?? 0),
                'Count with review: ' . ($data['count_reviewed'] ?? 0),
                'Total count: ' . ($data['count_all'] ?? 0),
            ],
            ['', '', '', '', ''],
        ];

        foreach (($data['publications'] ?? []) as $item) {
            $rows[] = [
                $item['submission_id'] ?? '',
                $item['date_submitted'] ?? '',
                $item['date_published'] ?? '',
                $item['days_to_publication'] ?? 0,
                ($item['has_review'] ?? false) ? 'Yes' : 'No',
            ];
        }

        return [
            'filename' => 'acceptance_publication_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Submission ID', 'Date Submitted', 'Date Published', 'Days to Publication', 'Had Review'],
            'rows' => $rows,
        ];
    }

    // ========================================
    // Authors / reviewers
    // ========================================

    public function authorsByCountry(int $contextId): array
    {
        return $this->countryCountCsv(
            $this->authorReviewerService->getAuthorsByCountry($contextId),
            'authors_by_country',
            'Authors Count'
        );
    }

    public function authorsByInstitution(int $contextId): array
    {
        return $this->institutionCountCsv(
            $this->authorReviewerService->getAuthorsByInstitution($contextId),
            'authors_by_institution',
            'Authors Count'
        );
    }

    public function reviewersByCountry(int $contextId): array
    {
        return $this->countryCountCsv(
            $this->authorReviewerService->getReviewersByCountry($contextId),
            'reviewers_by_country',
            'Reviewers Count'
        );
    }

    public function reviewersByInstitution(int $contextId): array
    {
        return $this->institutionCountCsv(
            $this->authorReviewerService->getReviewersByInstitution($contextId),
            'reviewers_by_institution',
            'Reviewers Count'
        );
    }

    public function reviewerList(int $contextId, ?string $year): array
    {
        $yearInt = $year !== null ? (int) $year : null;
        $data    = $this->authorReviewerService->getReviewerList($contextId, $yearInt) ?? [];

        $yearLabel = $year ?? 'all';
        $filename  = "reviewer-list-{$yearLabel}.csv";
        $headers   = ['Name', 'Institution', 'Country'];

        $rows = [];
        foreach ($data as $reviewer) {
            $rows[] = [
                $reviewer['fullName'],
                $reviewer['affiliation'] ?? '',
                $reviewer['country']     ?? '',
            ];
        }

        return compact('filename', 'headers', 'rows');
    }

    // ========================================
    // Issues / sections
    // ========================================

    public function issues(PKPRequest $request, int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->issueService->getIssueStats($request, $contextId, $range['start'], $range['end']);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['issueId'] ?? '',
                $item['issueTitle'] ?? '',
                $item['articleCount'] ?? 0,
                $item['downloads'] ?? 0,
                $item['views'] ?? 0,
                $item['total'] ?? 0,
            ];
        }

        return [
            'filename' => 'issue_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Issue ID', 'Issue Title', 'Articles', 'Downloads', 'Views', 'Total'],
            'rows' => $rows,
        ];
    }

    public function sections(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $data = $this->sectionService->getSectionStats($contextId, $range['start'], $range['end']);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['sectionId'] ?? '',
                $item['sectionTitle'] ?? '',
                $item['articleCount'] ?? 0,
                $item['downloads'] ?? 0,
                $item['views'] ?? 0,
                $item['total'] ?? 0,
            ];
        }

        return [
            'filename' => 'section_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Section ID', 'Section Title', 'Articles', 'Downloads', 'Views', 'Total'],
            'rows' => $rows,
        ];
    }

    public function languageTrends(int $contextId): array
    {
        $data = $this->languageService->getLanguageTrends($contextId);

        $rows = [];
        foreach ($data['labels'] ?? [] as $yearIdx => $year) {
            foreach ($data['series'] ?? [] as $series) {
                $rows[] = [
                    $year,
                    $series['code'] ?? '',
                    $series['name'] ?? '',
                    $series['data'][$yearIdx] ?? 0,
                ];
            }
        }

        return [
            'filename' => 'language_trends_' . date('Y-m-d') . '.csv',
            'headers'  => ['Year', 'Language Code', 'Language', 'Articles'],
            'rows'     => $rows,
        ];
    }

    public function languages(int $contextId, ?int $issueId): array
    {
        $data = $this->languageService->getLanguageStats($contextId, $issueId);

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['code'] ?? '',
                $item['name'] ?? '',
                $item['count'] ?? 0,
            ];
        }

        $suffix = $issueId ? "issue{$issueId}" : 'all';
        return [
            'filename' => "language_stats_{$suffix}_" . date('Y-m-d') . '.csv',
            'headers'  => ['Language Code', 'Language', 'Articles'],
            'rows'     => $rows,
        ];
    }

    // ========================================
    // Citations / enriched
    // ========================================

    /**
     * Refuse to export a chunked aggregate that hasn't finished computing.
     * CsvExportTrait converts the STATS_NOT_READY prefix into a 503.
     */
    private function assertReady(array $response, string $what): void
    {
        if (!empty($response['is_computing'])) {
            throw new \RuntimeException("STATS_NOT_READY: {$what}");
        }
    }

    public function topCited(PKPRequest $request, int $contextId, int $limit): array
    {
        $response = $this->enrichedService->getTopCitedArticles($request, $contextId, $limit);
        $this->assertReady($response, 'top cited');
        $articles = $response['articles'] ?? [];

        return [
            'filename' => 'top_cited_articles_' . date('Y-m-d') . '.csv',
            'headers' => ['Rank', 'Title', 'Authors', 'Year', 'Citations'],
            'rows' => $this->rankedArticleRows($articles, 'citations', true),
        ];
    }

    public function citationEvolution(int $contextId): array
    {
        $response = $this->enrichedService->getCitationEvolution($contextId);
        $this->assertReady($response, 'citation evolution');
        $data = $response['data'] ?? [];

        $rows = [];
        foreach ($data as $item) {
            $rows[] = [$item['year'] ?? '', $item['citations'] ?? 0];
        }

        return [
            'filename' => 'citation_evolution_' . date('Y-m-d') . '.csv',
            'headers' => ['Year', 'Citations'],
            'rows' => $rows,
        ];
    }

    public function openAccessStats(int $contextId): array
    {
        $data = $this->enrichedService->getOpenAccessStats($contextId);
        $this->assertReady($data, 'open access');
        $total = (int)($data['total'] ?? 0);
        $openAccess = (int)($data['open_access'] ?? 0);
        $rate = $total > 0 ? round(($openAccess / $total) * 100, 1) : 0;

        return [
            'filename' => 'open_access_stats_' . date('Y-m-d') . '.csv',
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Total Articles Analyzed', $total],
                ['Open Access Articles', $openAccess],
                ['Open Access Rate (%)', $rate],
                ['', ''],
                ['By Type:', ''],
                ['Diamond OA', $data['by_type']['diamond'] ?? 0],
                ['Gold OA', $data['by_type']['gold'] ?? 0],
                ['Hybrid OA', $data['by_type']['hybrid'] ?? 0],
                ['Green OA', $data['by_type']['green'] ?? 0],
                ['Bronze OA', $data['by_type']['bronze'] ?? 0],
                ['Closed Access', $data['by_type']['closed'] ?? 0],
            ],
        ];
    }

    public function citationsByCountry(int $contextId): array
    {
        $data = $this->enrichedService->getCitationsByCountry($contextId);
        // The chunked service returns either an `is_computing` placeholder or
        // the formatted list directly (no 'data' wrapper).
        if (is_array($data) && !empty($data['is_computing'])) {
            $this->assertReady($data, 'citations by country');
        }
        $rows = [];
        foreach (($data ?? []) as $item) {
            $rows[] = [
                $item['country_code']    ?? '',
                $item['country_name']    ?? '',
                $item['citations_count'] ?? 0,
            ];
        }

        return [
            'filename' => 'citations_by_country_' . date('Y-m-d') . '.csv',
            'headers'  => ['Country Code', 'Country Name', 'Citations'],
            'rows'     => $rows,
        ];
    }

    public function thematicProfile(int $contextId): array
    {
        $data = $this->enrichedService->getThematicProfile($contextId);
        $this->assertReady($data, 'thematic profile');
        $topics = $data['topics'] ?? [];

        $rows = [];
        foreach ($topics as $item) {
            $rows[] = [$item['name'] ?? '', $item['count'] ?? 0];
        }

        return [
            'filename' => 'thematic_profile_' . date('Y-m-d') . '.csv',
            'headers' => ['Topic', 'Publications Count'],
            'rows' => $rows,
        ];
    }

    public function citingJournals(PKPRequest $request, int $contextId, ?string $yearFilter): array
    {
        $response = $this->enrichedService->getCitingJournals($request, $contextId);
        $this->assertReady($response, 'citing journals');
        $filter = $this->normalizeYearFilter($yearFilter);
        $journals = is_array($response) && is_array($response['journals'] ?? null) ? $response['journals'] : [];

        $rows = [];
        foreach ($journals as $journal) {
            if (!is_array($journal)) {
                continue;
            }
            $name = trim((string)($journal['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $citations = $this->resolveCitationCount($journal, $filter);
            if ($citations <= 0) {
                continue;
            }

            $rows[] = [$name, $journal['issn'] ?? '', $journal['type'] ?? '', $citations];
        }

        usort($rows, fn($a, $b) => $b[3] <=> $a[3]);

        $suffix = $filter !== 'all' ? "_{$filter}" : '';

        return [
            'filename' => "citing_journals{$suffix}_" . date('Y-m-d') . '.csv',
            'headers' => ['Journal', 'ISSN', 'Type', 'Citations'],
            'rows' => $rows,
        ];
    }

    public function citingInstitutions(PKPRequest $request, int $contextId, ?string $yearFilter): array
    {
        $response = $this->enrichedService->getCitingInstitutions($request, $contextId);
        $filter = $this->normalizeYearFilter($yearFilter);
        $institutions = is_array($response) && is_array($response['institutions'] ?? null) ? $response['institutions'] : [];

        $rows = [];
        foreach ($institutions as $institution) {
            $citations = $this->resolveCitationCount($institution, $filter);
            if ($filter !== 'all' && $citations <= 0) {
                continue;
            }

            $rows[] = [
                $institution['name'] ?? '',
                $institution['country_name'] ?? $institution['country_code'] ?? '',
                $institution['type'] ?? '',
                $citations,
            ];
        }

        usort($rows, fn($a, $b) => $b[3] <=> $a[3]);

        $suffix = $filter !== 'all' ? "_{$filter}" : '';

        return [
            'filename' => "citing_institutions{$suffix}_" . date('Y-m-d') . '.csv',
            'headers' => ['Institution', 'Country', 'Type', 'Citations'],
            'rows' => $rows,
        ];
    }

    // ========================================
    // Full report
    // ========================================

    public function fullReport(int $contextId, ?string $year): array
    {
        $range = $this->dateRanges($year);
        $monthly = $this->statsService->getMonthlyStats($contextId, $range['start'], $range['end']);
        $annual = $this->statsService->getAnnualStats($contextId);
        $countries = $this->statsService->getCountryStatistics($contextId);

        $rows = [];
        foreach ($monthly as $item) {
            $month = $item['month'] ?? '';
            $rows[] = ['Monthly', $month, 'Downloads', $item['downloads']['value'] ?? 0];
            $rows[] = ['Monthly', $month, 'Views', $item['views']['value'] ?? 0];
        }
        foreach ($annual as $item) {
            $y = $item['year'] ?? '';
            $rows[] = ['Annual', $y, 'Downloads', $item['downloads'] ?? 0];
            $rows[] = ['Annual', $y, 'Views', $item['views'] ?? 0];
        }
        foreach (array_slice($countries, 0, 50) as $item) {
            $rows[] = ['Country', $item['country_name'] ?? '', 'Total Access', $item['total_access'] ?? 0];
        }

        return [
            'filename' => 'full_statistics_report_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Section', 'Category', 'Metric', 'Value'],
            'rows' => $rows,
        ];
    }

    // ========================================
    // Shared helpers
    // ========================================

    /**
     * Build the date range used by statistics queries, matching the handler's
     * convention: explicit year → Jan 1 to Dec 31 of that year; no year →
     * MIN_YEAR to yesterday.
     */
    private function dateRanges(?string $year): array
    {
        return [
            'start' => $year ? $year . '0101' : PublicStatsConstants::MIN_YEAR . '0101',
            'end' => $year ? $year . '1231' : date('Ymd', strtotime('yesterday')),
        ];
    }

    /**
     * Common rank/title/authors/metric/date row shape used by the four "top"
     * article exports. $metricKey is the array key for the metric column
     * ('downloads', 'views' or 'citations'). When $includeYear is true the
     * 'year' field is emitted instead of 'datePublished'.
     */
    private function rankedArticleRows(iterable $data, string $metricKey, bool $includeYear = false): array
    {
        $rows = [];
        $rank = 0;
        foreach ($data as $item) {
            $rows[] = [
                ++$rank,
                $item['title'] ?? '',
                $item['authors'] ?? '',
                $includeYear ? ($item['year'] ?? '') : ($item[$metricKey] ?? 0),
                $includeYear ? ($item[$metricKey] ?? 0) : ($item['datePublished'] ?? ''),
            ];
        }

        return $rows;
    }

    private function countryCountCsv(array $data, string $filenameStem, string $countColumn): array
    {
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['country_code'] ?? '',
                $item['country_name'] ?? '',
                $item['total_count'] ?? 0,
            ];
        }

        return [
            'filename' => $filenameStem . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Country Code', 'Country Name', $countColumn],
            'rows' => $rows,
        ];
    }

    private function institutionCountCsv(array $data, string $filenameStem, string $countColumn): array
    {
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['institution'] ?? '',
                $item['total_count'] ?? 0,
            ];
        }

        return [
            'filename' => $filenameStem . '_' . date('Y-m-d') . '.csv',
            'headers' => ['Institution', $countColumn],
            'rows' => $rows,
        ];
    }

    private function normalizeYearFilter(?string $yearFilter): string
    {
        if ($yearFilter === null || $yearFilter === '' || $yearFilter === 'all') {
            return 'all';
        }
        return $yearFilter;
    }

    /**
     * Shared citation-count resolver for citing journals/institutions:
     * year-specific filter reads citations_by_year, 'all' falls back to the
     * total field or a sum of per-year buckets.
     */
    private function resolveCitationCount(array $entity, string $filter): int
    {
        if ($filter !== 'all') {
            $byYear = $entity['citations_by_year'] ?? [];
            if (!is_array($byYear)) {
                return 0;
            }
            $yearInt = (int)$filter;
            if (isset($byYear[$yearInt])) {
                return (int)$byYear[$yearInt];
            }
            return isset($byYear[$filter]) ? (int)$byYear[$filter] : 0;
        }

        if (isset($entity['citations']) && is_numeric($entity['citations'])) {
            return (int)$entity['citations'];
        }
        if (isset($entity['citations_by_year']) && is_array($entity['citations_by_year'])) {
            return array_sum($entity['citations_by_year']);
        }
        return 0;
    }
}
