<?php

/**
 * @file plugins/generic/publicStats/controllers/traits/CsvExportTrait.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Trait providing CSV export HTTP endpoints.
 *
 * Keeps only the HTTP-layer concerns (rate limiting, streaming the CSV body,
 * error handling). Row building is delegated to the CsvExporter service so
 * each endpoint is a thin adapter between the request and the builder.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\controllers\traits;

use APP\plugins\generic\publicStats\classes\InputValidator;
use APP\plugins\generic\publicStats\classes\Logger;
use Illuminate\Support\Facades\Cache;
use PKP\core\PKPRequest;

trait CsvExportTrait
{
    // ========================================
    // HTTP plumbing
    // ========================================

    /**
     * Stream a CSV payload ({filename, headers, rows}) with UTF-8 BOM.
     */
    private function outputCsv(string $filename, array $headers, array $rows): void
    {
        // Clean any buffered output so it doesn't corrupt the CSV body.
        while (ob_get_level()) {
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
        flush();
    }

    /**
     * Validate the journal context and apply rate limiting for CSV exports.
     *
     * Enforces a per-IP rate limit of 10 export requests per minute to
     * prevent scraping of uncached export endpoints.
     */
    private function validateContextForExport(PKPRequest $request): ?int
    {
        $context = $request->getContext();
        if (!$context) {
            $this->outputError('Context not found', 404);
            return null;
        }

        // Cache::add preserves the window TTL; Cache::increment bumps the counter
        // without touching the expiry, so a burst of requests can't extend the window.
        $ip = $request->getRemoteAddr();
        $cacheKey = 'csv_rate_limit_' . md5($ip);

        Cache::add($cacheKey, 0, 60);
        $count = Cache::increment($cacheKey);

        if ($count > 10) {
            $this->outputError('Too many export requests. Please try again later.', 429);
            return null;
        }

        return $context->getId();
    }

    /**
     * Run a CSV export: validate the context, invoke $build(int $contextId)
     * to obtain ['filename' => ..., 'headers' => ..., 'rows' => ...], stream
     * it out. Any exception is logged and surfaced as a 500.
     */
    private function runExport(PKPRequest $request, callable $build, string $errorLabel): void
    {
        $contextId = $this->validateContextForExport($request);
        if (!$contextId) {
            return;
        }

        try {
            $payload = $build($contextId);
            $this->outputCsv($payload['filename'], $payload['headers'], $payload['rows']);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'STATS_NOT_READY:')) {
                $this->outputError(
                    'Statistics are still being computed in the background. Please try again in a few minutes.',
                    503
                );
                return;
            }
            Logger::error("Error exporting {$errorLabel}", $e);
            $this->outputError('Error exporting data', 500);
        } catch (\Exception $e) {
            Logger::error("Error exporting {$errorLabel}", $e);
            $this->outputError('Error exporting data', 500);
        }
    }

    // ========================================
    // Endpoints
    // ========================================

    public function exportMonthly(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->monthly($ctx, $year),
            'monthly stats'
        );
    }

    public function exportAnnual(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->annual($ctx),
            'annual stats'
        );
    }

    public function exportCountries(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->countries($ctx),
            'country stats'
        );
    }

    public function exportTopDownloaded(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->topDownloaded($request, $ctx, $year, $limit),
            'top downloaded'
        );
    }

    public function exportTopViewed(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->topViewed($request, $ctx, $year, $limit),
            'top viewed'
        );
    }

    public function exportEditorial(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->editorial($ctx, $year),
            'editorial stats'
        );
    }

    public function exportEditorialAnnual(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->editorialAnnual($ctx),
            'editorial annual stats'
        );
    }

    public function exportAuthorsByCountry(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->authorsByCountry($ctx),
            'authors by country'
        );
    }

    public function exportAuthorsByInstitution(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->authorsByInstitution($ctx),
            'authors by institution'
        );
    }

    public function exportReviewersByCountry(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->reviewersByCountry($ctx),
            'reviewers by country'
        );
    }

    public function exportReviewersByInstitution(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->reviewersByInstitution($ctx),
            'reviewers by institution'
        );
    }

    public function exportIssues(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->issues($request, $ctx, $year),
            'issue stats'
        );
    }

    public function exportSections(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->sections($ctx, $year),
            'section stats'
        );
    }

    public function exportLanguageTrends(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->languageTrends($ctx),
            'language trends'
        );
    }

    public function exportLanguages(array $args, PKPRequest $request): void
    {
        $issueIdRaw = $request->getUserVar('issueId');
        $issueId = ($issueIdRaw !== null && ctype_digit((string) $issueIdRaw)) ? (int) $issueIdRaw : null;
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->languages($ctx, $issueId),
            'language stats'
        );
    }

    public function exportTopCited(array $args, PKPRequest $request): void
    {
        $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->topCited($request, $ctx, $limit),
            'top cited'
        );
    }

    public function exportRecentDownloaded(array $args, PKPRequest $request): void
    {
        $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->recentDownloaded($request, $ctx, $limit),
            'recent downloaded'
        );
    }

    public function exportRecentViewed(array $args, PKPRequest $request): void
    {
        $limit = InputValidator::validateLimit($request->getUserVar('limit'), 100, 500);
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->recentViewed($request, $ctx, $limit),
            'recent viewed'
        );
    }

    public function exportCitationEvolution(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->citationEvolution($ctx),
            'citation evolution'
        );
    }

    public function exportOpenAccessStats(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->openAccessStats($ctx),
            'open access stats'
        );
    }

    public function exportCitingJournals(array $args, PKPRequest $request): void
    {
        $yearFilter = $request->getUserVar('year');
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->citingJournals($request, $ctx, $yearFilter),
            'citing journals'
        );
    }

    public function exportCitationsByCountry(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->citationsByCountry($ctx),
            'citations by country'
        );
    }

    public function exportThematicProfile(array $args, PKPRequest $request): void
    {
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->thematicProfile($ctx),
            'thematic profile'
        );
    }

    public function exportFirstDecision(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->firstDecision($ctx, $year),
            'first decision stats'
        );
    }

    public function exportAcceptancePublication(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->acceptancePublication($ctx, $year),
            'acceptance to publication stats'
        );
    }

    public function exportReviewerList(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->reviewerList($ctx, $year),
            'reviewer list'
        );
    }

    public function exportFullReport(array $args, PKPRequest $request): void
    {
        $year = InputValidator::validateYear($request->getUserVar('year'));
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->fullReport($ctx, $year),
            'full report'
        );
    }

    public function exportCitingInstitutions(array $args, PKPRequest $request): void
    {
        $yearFilter = $request->getUserVar('year');
        $this->runExport(
            $request,
            fn(int $ctx) => $this->csvExporter->citingInstitutions($request, $ctx, $yearFilter),
            'citing institutions'
        );
    }
}
