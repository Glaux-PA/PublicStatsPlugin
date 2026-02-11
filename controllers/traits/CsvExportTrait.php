<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/CsvExportTrait.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing CSV export HTTP endpoints.
 *
 * Contains handlers for exporting all statistics types to CSV format.
 * Implements proper UTF-8 encoding with BOM for Excel compatibility.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use PKP\core\PKPRequest;
use APP\plugins\generic\publicStats\classes\InputValidator;
use Illuminate\Support\Facades\Cache;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;

trait CsvExportTrait
{
    // ========================================
    // CSV Output Helpers
    // ========================================

    /**
     * Output CSV response with proper headers
     */
    private function outputCsv(string $filename, array $headers, array $rows): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, $headers);
        
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Validate the journal context and apply rate limiting for CSV exports.
     *
     * In addition to verifying a valid context (journal) exists, enforces
     * a per-IP rate limit of 10 export requests per minute. This prevents
     * automated scraping of export endpoints, which generate CSV files
     * on-the-fly without caching and execute database queries on each request.
     *
     * @param PKPRequest $request Current HTTP request
     * @return int|null Context ID, or null if invalid or rate limit exceeded
     */
    private function validateContextForExport(PKPRequest $request): ?int
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return null;
        }

        // Rate limiting: max 10 CSV exports per minute per IP
        $ip = $request->getRemoteAddr();
        $cacheKey = 'csv_rate_limit_' . md5($ip);
        $count = Cache::get($cacheKey, 0);

        if ($count >= 10) {
            $this->outputError('Too many export requests. Please try again later.', 429);
            return null;
        }

        Cache::put($cacheKey, $count + 1, 60);

        return $context->getId();
    }

    // ========================================
    // Export Endpoints
    // ========================================

    /**
     * Export monthly statistics to CSV
     * Fields: month, downloads.value, views.value
     */
    public function exportMonthly(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->statsService->getMonthlyStats(
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Month', 'Month Label', 'Downloads', 'Views', 'Total'];
            $rows = [];
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['month'] ?? '',
                    $item['downloads']['label'] ?? '',
                    $item['downloads']['value'] ?? 0,
                    $item['views']['value'] ?? 0,
                    $item['total'] ?? 0
                ];
            }

            $filename = 'monthly_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting monthly stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export annual statistics to CSV
     * Fields: year, downloads, views, total
     */
    public function exportAnnual(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->statsService->getAnnualStats($contextId);

            $headers = ['Year', 'Downloads', 'Views', 'Total'];
            $rows = [];
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['year'] ?? '',
                    $item['downloads'] ?? 0,
                    $item['views'] ?? 0,
                    $item['total'] ?? 0
                ];
            }

            $filename = 'annual_stats_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting annual stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export country statistics to CSV
     * Fields: country_code, country_name, total_access
     */
    public function exportCountries(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->statsService->getCountryStatistics($contextId);

            $headers = ['Country Code', 'Country Name', 'Total Access'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['country_code'] ?? '',
                        $item['country_name'] ?? '',
                        $item['total_access'] ?? 0
                    ];
                }
            }

            $filename = 'country_stats_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting country stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export top downloaded articles to CSV
     * Fields: submissionId, title, authors, downloads, datePublished
     */
    public function exportTopDownloaded(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->articleService->getTopDownloadedArticles(
                $request,
                $contextId,
                $limit,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Rank', 'Title', 'Authors', 'Downloads', 'Date Published'];
            $rows = [];
            
            foreach ($data as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['title'] ?? '',
                    $item['authors'] ?? '',
                    $item['downloads'] ?? 0,
                    $item['datePublished'] ?? ''
                ];
            }

            $filename = 'top_downloaded_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting top downloaded: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export top viewed articles to CSV
     * Fields: submissionId, title, authors, views, datePublished
     */
    public function exportTopViewed(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->articleService->getTopViewedArticles(
                $request,
                $contextId,
                $limit,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Rank', 'Title', 'Authors', 'Views', 'Date Published'];
            $rows = [];
            
            foreach ($data as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['title'] ?? '',
                    $item['authors'] ?? '',
                    $item['views'] ?? 0,
                    $item['datePublished'] ?? ''
                ];
            }

            $filename = 'top_viewed_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting top viewed: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export editorial statistics to CSV
     * Fields: month, label, received, declined, published, inProcess
     */
    public function exportEditorial(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->editorialService->getStats(
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Month', 'Label', 'Received', 'Declined', 'Published', 'In Process'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['month'] ?? '',
                        $item['label'] ?? '',
                        $item['received'] ?? 0,
                        $item['declined'] ?? 0,
                        $item['published'] ?? 0,
                        $item['inProcess'] ?? 0
                    ];
                }
            }

            $filename = 'editorial_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting editorial stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export editorial annual statistics to CSV
     * Fields: year, label, received, declined, published, inProcess
     */
    public function exportEditorialAnnual(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->editorialService->getAnnualStats($contextId);

            $headers = ['Year', 'Received', 'Declined', 'Published', 'In Process', 'Acceptance Rate (%)'];
            $rows = [];
            
            foreach ($data as $item) {
                $received = $item['received'] ?? 0;
                $declined = $item['declined'] ?? 0;
                $published = $item['published'] ?? 0;
                
                $total = $received;
                $acceptanceRate = $total > 0 ? round(($published / $total) * 100, 1) : 0;
                
                $rows[] = [
                    $item['year'] ?? '',
                    $received,
                    $declined,
                    $published,
                    $item['inProcess'] ?? 0,
                    $acceptanceRate
                ];
            }

            $filename = 'editorial_annual_stats_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting editorial annual stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export authors by country to CSV
     * Fields: country_code, country_name, total_count
     */
    public function exportAuthorsByCountry(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->authorReviewerService->getAuthorsByCountry($contextId);

            $headers = ['Country Code', 'Country Name', 'Authors Count'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['country_code'] ?? '',
                        $item['country_name'] ?? '',
                        $item['total_count'] ?? 0
                    ];
                }
            }

            $filename = 'authors_by_country_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting authors by country: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export authors by institution to CSV
     * Fields: institution, total_count
     */
    public function exportAuthorsByInstitution(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->authorReviewerService->getAuthorsByInstitution($contextId);

            $headers = ['Institution', 'Authors Count'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['institution'] ?? '',
                        $item['total_count'] ?? 0
                    ];
                }
            }

            $filename = 'authors_by_institution_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting authors by institution: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export reviewers by country to CSV
     * Fields: country_code, country_name, total_count
     */
    public function exportReviewersByCountry(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->authorReviewerService->getReviewersByCountry($contextId);

            $headers = ['Country Code', 'Country Name', 'Reviewers Count'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['country_code'] ?? '',
                        $item['country_name'] ?? '',
                        $item['total_count'] ?? 0
                    ];
                }
            }

            $filename = 'reviewers_by_country_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting reviewers by country: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export reviewers by institution to CSV
     * Fields: institution, total_count
     */
    public function exportReviewersByInstitution(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->authorReviewerService->getReviewersByInstitution($contextId);

            $headers = ['Institution', 'Reviewers Count'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['institution'] ?? '',
                        $item['total_count'] ?? 0
                    ];
                }
            }

            $filename = 'reviewers_by_institution_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting reviewers by institution: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export issue statistics to CSV
     * Fields: issueId, issueTitle, downloads, views, total, articleCount
     */
    public function exportIssues(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->issueService->getIssueStats(
                $request,
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Issue ID', 'Issue Title', 'Articles', 'Downloads', 'Views', 'Total'];
            $rows = [];
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['issueId'] ?? '',
                    $item['issueTitle'] ?? '',
                    $item['articleCount'] ?? 0,
                    $item['downloads'] ?? 0,
                    $item['views'] ?? 0,
                    $item['total'] ?? 0
                ];
            }

            $filename = 'issue_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting issue stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export section statistics to CSV
     * Fields: sectionId, sectionTitle, downloads, views, total, articleCount
     */
    public function exportSections(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->sectionService->getSectionStats(
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Section ID', 'Section Title', 'Articles', 'Downloads', 'Views', 'Total'];
            $rows = [];
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['sectionId'] ?? '',
                    $item['sectionTitle'] ?? '',
                    $item['articleCount'] ?? 0,
                    $item['downloads'] ?? 0,
                    $item['views'] ?? 0,
                    $item['total'] ?? 0
                ];
            }

            $filename = 'section_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting section stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export top cited articles to CSV
     * Fields: submissionId, title, authors, year, citations
     */
    public function exportTopCited(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            
            $data = $this->enrichedService->getTopCitedArticles($request, $contextId, $limit);

            $headers = ['Rank', 'Title', 'Authors', 'Year', 'Citations'];
            $rows = [];
            
            foreach ($data as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['title'] ?? '',
                    $item['authors'] ?? '',
                    $item['year'] ?? '',
                    $item['citations'] ?? 0
                ];
            }

            $filename = 'top_cited_articles_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting top cited: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export funding sources to CSV
     * Fields: funder, count
     */
    public function exportFundingSources(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            
            $data = $this->enrichedService->getFundingSources($contextId, $limit);

            $headers = ['Funder', 'Publications Count'];
            $rows = [];
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['funder'] ?? '',
                    $item['count'] ?? 0
                ];
            }

            $filename = 'funding_sources_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting funding sources: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export recent top downloaded articles (last 60 days) to CSV
     */
    public function exportRecentDownloaded(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            
            $data = $this->articleService->getRecentTopDownloadedArticles(
                $request,
                $contextId,
                $limit
            );

            $headers = ['Rank', 'Title', 'Authors', 'Downloads (60 days)', 'Date Published'];
            $rows = [];
            
            foreach ($data as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['title'] ?? '',
                    $item['authors'] ?? '',
                    $item['downloads'] ?? 0,
                    $item['datePublished'] ?? ''
                ];
            }

            $filename = 'recent_top_downloaded_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting recent downloaded: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export recent top viewed articles (last 60 days) to CSV
     */
    public function exportRecentViewed(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
            
            $data = $this->articleService->getRecentTopViewedArticles(
                $request,
                $contextId,
                $limit
            );

            $headers = ['Rank', 'Title', 'Authors', 'Views (60 days)', 'Date Published'];
            $rows = [];
            
            foreach ($data as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['title'] ?? '',
                    $item['authors'] ?? '',
                    $item['views'] ?? 0,
                    $item['datePublished'] ?? ''
                ];
            }

            $filename = 'recent_top_viewed_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting recent viewed: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export citation evolution by year to CSV
     */
    public function exportCitationEvolution(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->enrichedService->getCitationEvolution($contextId);

            $headers = ['Year', 'Citations'];
            $rows = [];
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    $rows[] = [
                        $item['year'] ?? '',
                        $item['citations'] ?? 0
                    ];
                }
            }

            $filename = 'citation_evolution_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting citation evolution: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export open access statistics to CSV
     */
    public function exportOpenAccessStats(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->enrichedService->getOpenAccessStats($contextId);

            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Articles Analyzed', $data['total'] ?? 0],
                ['Open Access Articles', $data['open_access'] ?? 0],
                ['Open Access Rate (%)', $data['total'] > 0 ? round(($data['open_access'] / $data['total']) * 100, 1) : 0],
                ['', ''], // separator
                ['By Type:', ''],
                ['Diamond OA', $data['by_type']['diamond'] ?? 0],
                ['Gold OA', $data['by_type']['gold'] ?? 0],
                ['Hybrid OA', $data['by_type']['hybrid'] ?? 0],
                ['Green OA', $data['by_type']['green'] ?? 0],
                ['Bronze OA', $data['by_type']['bronze'] ?? 0],
                ['Closed Access', $data['by_type']['closed'] ?? 0]
            ];

            $filename = 'open_access_stats_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting open access stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export citing journals to CSV
     */

    public function exportCitingJournals(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $response = $this->enrichedService->getCitingJournals($request, $contextId);
            $yearFilter = $request->getUserVar('year');
            
            // Normalize year filter
            if ($yearFilter === null || $yearFilter === '' || $yearFilter === 'all') {
                $yearFilter = 'all';
            }

            $headers = ['Journal', 'ISSN', 'Type', 'Citations'];
            $rows = [];
            
            if ($response === null) {
                error_log("exportCitingJournals: Response is null");
                $filename = 'citing_journals_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            if (!is_array($response)) {
                error_log("exportCitingJournals: Response is not an array, type: " . gettype($response));
                $filename = 'citing_journals_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            if (!isset($response['journals']) || !is_array($response['journals'])) {
                error_log("exportCitingJournals: 'journals' key missing or not an array");
                $filename = 'citing_journals_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            if (empty($response['journals'])) {
                error_log("exportCitingJournals: 'journals' array is empty");
                $filename = 'citing_journals_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            $journals = $response['journals'];
            
            // Process each journal
            foreach ($journals as $index => $journal) {
                if (!is_array($journal)) {
                    error_log("exportCitingJournals: Journal at index {$index} is not an array");
                    continue;
                }
                
                if (!isset($journal['name'])) {
                    error_log("exportCitingJournals: Journal at index {$index} has no 'name' key");
                    continue;
                }
                
                $journalName = trim((string)$journal['name']);
                if ($journalName === '') {
                    error_log("exportCitingJournals: Journal at index {$index} has empty name after trim");
                    continue;
                }
                
                // Calculate citations based on year filter
                $citations = 0;
                
                if ($yearFilter !== 'all') {
                    // Specific year filter - try both int and string keys
                    $yearInt = (int)$yearFilter;
                    $citationsByYear = $journal['citations_by_year'] ?? [];
                    
                    if (is_array($citationsByYear)) {
                        if (isset($citationsByYear[$yearInt])) {
                            $citations = (int)$citationsByYear[$yearInt];
                        } elseif (isset($citationsByYear[$yearFilter])) {
                            $citations = (int)$citationsByYear[$yearFilter];
                        }
                    }
                } else {
                    // All time - use total citations field or sum from years
                    if (isset($journal['citations']) && is_numeric($journal['citations'])) {
                        $citations = (int)$journal['citations'];
                    } elseif (isset($journal['citations_by_year']) && is_array($journal['citations_by_year'])) {
                        $citations = array_sum($journal['citations_by_year']);
                    }
                }
                
                if ($citations <= 0) {
                    continue;
                }
                
                $rows[] = [
                    $journalName,
                    $journal['issn'] ?? '',
                    $journal['type'] ?? '',
                    $citations
                ];
            }
            
            // Sort by citations descending
            usort($rows, fn($a, $b) => $b[3] <=> $a[3]);

            $yearSuffix = ($yearFilter !== 'all') ? "_{$yearFilter}" : '';
            $filename = 'citing_journals' . $yearSuffix . '_' . date('Y-m-d') . '.csv';
            
            if (empty($rows)) {
                error_log("exportCitingJournals: No valid journals found after processing");
            }
            
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting citing journals: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export citations by country to CSV
     * Fields: country_code, country_name, citations_count
     */
    public function exportCitationsByCountry(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->enrichedService->getCitationsByCountry($contextId);

            $headers = ['Country Code', 'Country Name', 'Citations'];
            $rows = [];
            
            // Handle null or empty data
            if ($data === null || !is_array($data) || empty($data)) {
                // Output CSV with just headers (no data)
                $filename = 'citations_by_country_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            foreach ($data as $item) {
                $rows[] = [
                    $item['country_code'] ?? '',
                    $item['country_name'] ?? '',
                    $item['citations_count'] ?? 0
                ];
            }

            $filename = 'citations_by_country_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting citations by country: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export thematic profile to CSV
     * Fields: name, count
     */
    public function exportThematicProfile(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->enrichedService->getThematicProfile($contextId);

            $headers = ['Topic', 'Publications Count'];
            $rows = [];
            
            $topics = $data['topics'] ?? [];
            foreach ($topics as $item) {
                $rows[] = [
                    $item['name'] ?? '',
                    $item['count'] ?? 0
                ];
            }

            $filename = 'thematic_profile_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting thematic profile: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export decision times (first decision) to CSV
     */
    public function exportFirstDecision(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->decisionService->getFirstDecisionStats(
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Submission ID', 'Date Submitted', 'Date Decided', 'Days to Decision', 'Decision Type', 'Had Review'];
            $rows = [];
            
            // Add summary row first
            $rows[] = [
                'SUMMARY',
                'Avg Days (with review): ' . ($data['average_days_reviewed'] ?? 0),
                'Avg Days (all): ' . ($data['average_days_all'] ?? 0),
                'Count with review: ' . ($data['count_reviewed'] ?? 0),
                'Total count: ' . ($data['count_all'] ?? 0),
                ''
            ];
            $rows[] = ['', '', '', '', '', '']; // Empty row separator
            
            // Add individual decisions
            $decisions = $data['decisions'] ?? [];
            if (is_array($decisions)) {
                foreach ($decisions as $item) {
                    $rows[] = [
                        $item['submission_id'] ?? '',
                        $item['date_submitted'] ?? '',
                        $item['date_decided'] ?? '',
                        $item['days_to_decision'] ?? 0,
                        $item['decision_type'] ?? '',
                        ($item['has_review'] ?? false) ? 'Yes' : 'No'
                    ];
                }
            }

            $filename = 'first_decision_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting first decision stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export acceptance to publication times to CSV
     */
    public function exportAcceptancePublication(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            $data = $this->decisionService->getAcceptancePublicationStats(
                $contextId,
                $dateRanges['start'],
                $dateRanges['end']
            );

            $headers = ['Submission ID', 'Date Submitted', 'Date Published', 'Days to Publication', 'Had Review'];
            $rows = [];
            
            // Add summary row first
            $rows[] = [
                'SUMMARY',
                'Avg Days (with review): ' . ($data['average_days_reviewed'] ?? 0),
                'Avg Days (all): ' . ($data['average_days_all'] ?? 0),
                'Count with review: ' . ($data['count_reviewed'] ?? 0),
                'Total count: ' . ($data['count_all'] ?? 0)
            ];
            $rows[] = ['', '', '', '', '']; // Empty row separator
            
            // Add individual publications
            $publications = $data['publications'] ?? [];
            if (is_array($publications)) {
                foreach ($publications as $item) {
                    $rows[] = [
                        $item['submission_id'] ?? '',
                        $item['date_submitted'] ?? '',
                        $item['date_published'] ?? '',
                        $item['days_to_publication'] ?? 0,
                        ($item['has_review'] ?? false) ? 'Yes' : 'No'
                    ];
                }
            }

            $filename = 'acceptance_publication_stats_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting acceptance to publication stats: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export collaboration metrics to CSV
     */
    public function exportCollaboration(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $data = $this->enrichedService->getCollaborationMetrics($contextId);

            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Works Analyzed', $data['total_works'] ?? 0],
                ['International Collaborations', $data['international_collaborations'] ?? 0],
                ['Multi-Institution Works', $data['multi_institution'] ?? 0],
                ['Avg Countries per Work', $data['avg_countries_per_work'] ?? 0],
                ['Avg Institutions per Work', $data['avg_institutions_per_work'] ?? 0],
                ['International Collaboration Rate (%)', $data['international_collaboration_rate'] ?? 0],
                ['Multi-Institution Rate (%)', $data['multi_institution_rate'] ?? 0],
            ];

            $filename = 'collaboration_metrics_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting collaboration metrics: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export complete statistics report to CSV
     */
    public function exportFullReport(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $year = InputValidator::validateYear($request->getUserVar('year'));
            $dateRanges = $this->getDateRanges($year, null);
            
            // Get all data
            $monthlyData = $this->statsService->getMonthlyStats($contextId, $dateRanges['start'], $dateRanges['end']);
            $annualData = $this->statsService->getAnnualStats($contextId);
            $countryData = $this->statsService->getCountryStatistics($contextId);
            
            $headers = ['Section', 'Category', 'Metric', 'Value'];
            $rows = [];
            
            // Monthly stats
            foreach ($monthlyData as $item) {
                $month = $item['month'] ?? '';
                $rows[] = ['Monthly', $month, 'Downloads', $item['downloads']['value'] ?? 0];
                $rows[] = ['Monthly', $month, 'Views', $item['views']['value'] ?? 0];
            }
            
            // Annual stats
            foreach ($annualData as $item) {
                $yearVal = $item['year'] ?? '';
                $rows[] = ['Annual', $yearVal, 'Downloads', $item['downloads'] ?? 0];
                $rows[] = ['Annual', $yearVal, 'Views', $item['views'] ?? 0];
            }
            
            // Country stats (top 50)
            if (is_array($countryData)) {
                $countrySlice = array_slice($countryData, 0, 50);
                foreach ($countrySlice as $item) {
                    $country = $item['country_name'] ?? '';
                    $rows[] = ['Country', $country, 'Total Access', $item['total_access'] ?? 0];
                }
            }

            $filename = 'full_statistics_report_' . ($year ?? 'all') . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting full report: " . $e->getMessage());
            $this->outputError('Error exporting data', 500);
        }
    }

    /**
     * Export citing institutions to CSV
     * Fields: name, country, type, citations
     */
    public function exportCitingInstitutions(array $args, PKPRequest $request): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) return;

        try {
            $response = $this->enrichedService->getCitingInstitutions($request, $contextId);
            $yearFilter = $request->getUserVar('year');
            
            // Normalize year filter
            if ($yearFilter === null || $yearFilter === '' || $yearFilter === 'all') {
                $yearFilter = 'all';
            }

            $headers = ['Institution', 'Country', 'Type', 'Citations'];
            $rows = [];
            
            // Check if we have valid data
            if (!is_array($response) || !isset($response['institutions']) || empty($response['institutions'])) {
                error_log("exportCitingInstitutions: No data available - response is " . 
                    (is_array($response) ? 'array without institutions' : gettype($response)));
                $filename = 'citing_institutions_' . date('Y-m-d') . '.csv';
                $this->outputCsv($filename, $headers, $rows);
                return;
            }
            
            $institutions = $response['institutions'];
            
            // Process each institution
            foreach ($institutions as $institution) {
                // Calculate citations based on year filter
                if ($yearFilter !== 'all') {
                    // Specific year filter
                    $citationsByYear = $institution['citations_by_year'] ?? [];
                    $citations = is_array($citationsByYear) ? ($citationsByYear[$yearFilter] ?? 0) : 0;
                    
                    // Skip institutions with 0 citations in filtered year
                    if ($citations <= 0) {
                        continue;
                    }
                } else {
                    // All time - use total citations field or sum from years
                    if (isset($institution['citations']) && is_numeric($institution['citations'])) {
                        $citations = (int)$institution['citations'];
                    } elseif (isset($institution['citations_by_year']) && is_array($institution['citations_by_year'])) {
                        $citations = array_sum($institution['citations_by_year']);
                    } else {
                        $citations = 0;
                    }
                }
                
                $rows[] = [
                    $institution['name'] ?? '',
                    $institution['country_name'] ?? $institution['country_code'] ?? '',
                    $institution['type'] ?? '',
                    $citations
                ];
            }
            
            // Sort by citations descending
            usort($rows, fn($a, $b) => $b[3] <=> $a[3]);

            $yearSuffix = ($yearFilter !== 'all') ? "_{$yearFilter}" : '';
            $filename = 'citing_institutions' . $yearSuffix . '_' . date('Y-m-d') . '.csv';
            $this->outputCsv($filename, $headers, $rows);
            
        } catch (\Exception $e) {
            error_log("Error exporting citing institutions: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->outputError('Error exporting data', 500);
        }
    }
}