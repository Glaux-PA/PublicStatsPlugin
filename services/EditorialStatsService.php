<?php

/**
 * @file plugins/generic/publicStats/services/EditorialStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for editorial workflow statistics.
 *
 * Provides metrics about the submission lifecycle including received,
 * declined, published, and in-process counts. Unlike article statistics
 * which focus on access metrics, this service tracks editorial workflow
 * throughput.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\facades\Repo;
use PKP\submission\PKPSubmission;
use APP\submission\Submission;
use PKP\decision\Decision;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\services\BaseStatsService;

class EditorialStatsService extends BaseStatsService
{
    /**
     * Decision types that indicate rejection.
     * Includes initial decline, standard decline, and internal decline.
     */
    private const DECLINE_DECISIONS = [
        Decision::DECLINE,
        Decision::INITIAL_DECLINE,
        Decision::DECLINE_INTERNAL,
    ];

    /**
     * Get monthly editorial statistics.
     *
     * Returns counts of submissions received, declined, published, and
     * in-process for each month in the specified range.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array|null Monthly editorial statistics
     */
    public function getStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): ?array {
        $dateRange = $this->getDateRange($dateStart, $dateEnd);
        $dateStart = $dateRange['start'];
        $dateEnd = $dateRange['end'];

        $startTime = strtotime($dateStart);
        $endTime = strtotime($dateEnd);

        $monthlyStats = $this->initializeMonthlyStats($startTime, $endTime);
        $submissions = $this->getAllSubmissions($contextId, $dateStart, $dateEnd);

        $decisionsBySubmission = $this->getDecisionsForSubmissions(
            array_map(fn($s) => $s->getId(), $submissions)
        );

        foreach ($submissions as $submission) {
            $this->processSubmission($submission, $monthlyStats, $startTime, $endTime, $decisionsBySubmission);
        }

        return array_values($monthlyStats);
    }

    /**
     * Get annual editorial statistics.
     *
     * Returns yearly aggregated counts from MIN_YEAR to current year.
     *
     * @param int $contextId Journal/press ID
     * @return array Annual editorial statistics
     */
    public function getAnnualStats(int $contextId): array
    {
        $startYear = PublicStatsConstants::MIN_YEAR;
        $endYear = (int)date('Y');

        $annualStats = $this->initializeAnnualStats($startYear, $endYear);
        $submissions = $this->getAllSubmissions($contextId, "{$startYear}-01-01", "{$endYear}-12-31");

        $decisionsBySubmission = $this->getDecisionsForSubmissions(
            array_map(fn($s) => $s->getId(), $submissions)
        );

        foreach ($submissions as $submission) {
            $this->processSubmissionAnnual($submission, $annualStats, $startYear, $endYear, $decisionsBySubmission);
        }

        return array_values($annualStats);
    }

    /**
     * Initialize monthly statistics structure.
     *
     * Creates empty counters for each month in the range.
     *
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @return array Initialized monthly stats array
     */
    private function initializeMonthlyStats(int $startTime, int $endTime): array
    {
        $monthlyStats = [];
        $currentTime = $startTime;

        while ($currentTime <= $endTime) {
            $monthKey = date('Y-m', $currentTime);
            $monthlyStats[$monthKey] = [
                'month' => $monthKey,
                'label' => date('M Y', $currentTime),
                'received' => 0,
                'declined' => 0,
                'published' => 0,
                'inProcess' => 0
            ];
            $currentTime = strtotime('+1 month', $currentTime);
        }

        return $monthlyStats;
    }

    /**
     * Initialize annual statistics structure.
     *
     * Creates empty counters for each year in the range.
     *
     * @param int $startYear Start year
     * @param int $endYear End year
     * @return array Initialized annual stats array
     */
    private function initializeAnnualStats(int $startYear, int $endYear): array
    {
        $annualStats = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $annualStats[$year] = [
                'year' => $year,
                'label' => (string)$year,
                'received' => 0,
                'declined' => 0,
                'published' => 0,
                'inProcess' => 0
            ];
        }

        return $annualStats;
    }

    /**
     * Submissions for a context in all statuses (editorial stats need the
     * full workflow). When a date range is given, filter at SQL level instead
     * of dragging every draft and old reject into PHP.
     *
     * @param string|null $dateStart  Inclusive lower bound for date_submitted
     * @param string|null $dateEnd    Inclusive upper bound for date_submitted
     */
    private function getAllSubmissions(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId]);

        if ($dateStart === null && $dateEnd === null) {
            // Materialise the LazyCollection so callers can use array_map / count.
            return iterator_to_array($collector->getMany(), false);
        }

        $query = $collector->getQueryBuilder();
        if ($dateStart !== null) {
            $query->where('s.date_submitted', '>=', date('Y-m-d 00:00:00', strtotime($dateStart)));
        }
        if ($dateEnd !== null) {
            $query->where('s.date_submitted', '<=', date('Y-m-d 23:59:59', strtotime($dateEnd)));
        }

        $submissions = [];
        foreach ($query->get() as $row) {
            $submissions[] = Repo::submission()->dao->fromRow($row);
        }
        return $submissions;
    }

    /**
     * One query per call instead of one per submission. Returns
     * `submissionId => decisions[]`.
     *
     * @param int[] $submissionIds
     */
    private function getDecisionsForSubmissions(array $submissionIds): array
    {
        if (empty($submissionIds)) {
            return [];
        }

        $allDecisions = Repo::decision()
            ->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->getMany();

        $decisionsBySubmission = [];
        foreach ($allDecisions as $decision) {
            $subId = $decision->getData('submissionId');
            if (!isset($decisionsBySubmission[$subId])) {
                $decisionsBySubmission[$subId] = [];
            }
            $decisionsBySubmission[$subId][] = $decision;
        }

        return $decisionsBySubmission;
    }

    /**
     * Process a submission for monthly statistics.
     *
     * Categorizes the submission based on its status and updates
     * the appropriate month's counters.
     *
     * @param Submission $submission Submission to process
     * @param array &$monthlyStats Stats array to update
     * @param int $startTime Range start timestamp
     * @param int $endTime Range end timestamp
     */
    private function processSubmission(
        Submission $submission,
        array &$monthlyStats,
        int $startTime,
        int $endTime,
        array $decisionsBySubmission = []
    ): void {
        $dateSubmitted = $submission->getData('dateSubmitted');
        if (!$dateSubmitted) {
            return;
        }

        $submissionTime = strtotime($dateSubmitted);

        if ($submissionTime < $startTime || $submissionTime > $endTime) {
            return;
        }

        $monthKey = date('Y-m', $submissionTime);
        if (!isset($monthlyStats[$monthKey])) {
            return;
        }

        $monthlyStats[$monthKey]['received']++;

        $status = $submission->getData('status');

        if ($status === PKPSubmission::STATUS_PUBLISHED) {
            $this->processPublished($submission, $monthlyStats, $startTime, $endTime);
            return;
        }

        if ($status === PKPSubmission::STATUS_DECLINED) {
            $decisions = $decisionsBySubmission[$submission->getId()] ?? [];
            $this->processDeclined($submission, $monthlyStats, $startTime, $endTime, $decisions);
            return;
        }

        if (in_array($status, [PKPSubmission::STATUS_QUEUED, PKPSubmission::STATUS_SCHEDULED])) {
            $monthlyStats[$monthKey]['inProcess']++;
        }
    }

    /**
     * Process a published submission.
     *
     * Records the publication in the month it was published.
     *
     * @param Submission $submission Published submission
     * @param array &$monthlyStats Stats array to update
     * @param int $startTime Range start timestamp
     * @param int $endTime Range end timestamp
     */
    private function processPublished(
        Submission $submission,
        array &$monthlyStats,
        int $startTime,
        int $endTime
    ): void {
        $publication = $submission->getLatestPublication();
        if (!$publication) {
            return;
        }

        $datePublished = $publication->getData('datePublished');
        if (!$datePublished) {
            return;
        }

        $publishedTime = strtotime($datePublished);
        if ($publishedTime < $startTime || $publishedTime > $endTime) {
            return;
        }

        $publishedMonthKey = date('Y-m', $publishedTime);
        if (isset($monthlyStats[$publishedMonthKey])) {
            $monthlyStats[$publishedMonthKey]['published']++;
        }
    }

    /**
     * Process a declined submission.
     *
     * Finds the most recent decline decision and records it
     * in the appropriate month.
     *
     * @param Submission $submission Declined submission
     * @param array &$monthlyStats Stats array to update
     * @param int $startTime Range start timestamp
     * @param int $endTime Range end timestamp
     */
    private function processDeclined(
        Submission $submission,
        array &$monthlyStats,
        int $startTime,
        int $endTime,
        array $decisions = []
    ): void {
        $latestDeclineDate = null;
        $latestDeclineTime = 0;

        foreach ($decisions as $decision) {
            if (!in_array($decision->getData('decision'), self::DECLINE_DECISIONS)) {
                continue;
            }

            $dateDecided = $decision->getData('dateDecided');
            if (!$dateDecided) {
                continue;
            }

            $decidedTime = strtotime($dateDecided);

            if ($decidedTime > $latestDeclineTime) {
                $latestDeclineTime = $decidedTime;
                $latestDeclineDate = $dateDecided;
            }
        }

        if ($latestDeclineDate && $latestDeclineTime >= $startTime && $latestDeclineTime <= $endTime) {
            $declinedMonthKey = date('Y-m', $latestDeclineTime);
            if (isset($monthlyStats[$declinedMonthKey])) {
                $monthlyStats[$declinedMonthKey]['declined']++;
            }
        }
    }

    /**
     * Process a submission for annual statistics.
     *
     * Similar to processSubmission but aggregates by year.
     *
     * @param Submission $submission Submission to process
     * @param array &$annualStats Stats array to update
     * @param int $startYear Range start year
     * @param int $endYear Range end year
     */
    private function processSubmissionAnnual(
        Submission $submission,
        array &$annualStats,
        int $startYear,
        int $endYear,
        array $decisionsBySubmission = []
    ): void {
        $dateSubmitted = $submission->getData('dateSubmitted');
        if (!$dateSubmitted) {
            return;
        }

        $submissionYear = (int)date('Y', strtotime($dateSubmitted));

        if ($submissionYear < $startYear || $submissionYear > $endYear) {
            return;
        }

        if (!isset($annualStats[$submissionYear])) {
            return;
        }

        $annualStats[$submissionYear]['received']++;

        $status = $submission->getData('status');

        if ($status === PKPSubmission::STATUS_PUBLISHED) {
            $this->processPublishedAnnual($submission, $annualStats, $startYear, $endYear);
            return;
        }

        if ($status === PKPSubmission::STATUS_DECLINED) {
            $decisions = $decisionsBySubmission[$submission->getId()] ?? [];
            $this->processDeclinedAnnual($submission, $annualStats, $startYear, $endYear, $decisions);
            return;
        }

        if (in_array($status, [PKPSubmission::STATUS_QUEUED, PKPSubmission::STATUS_SCHEDULED])) {
            $annualStats[$submissionYear]['inProcess']++;
        }
    }

    /**
     * Process published submission for annual statistics.
     *
     * @param Submission $submission Published submission
     * @param array &$annualStats Stats array to update
     * @param int $startYear Range start year
     * @param int $endYear Range end year
     */
    private function processPublishedAnnual(
        Submission $submission,
        array &$annualStats,
        int $startYear,
        int $endYear
    ): void {
        $publication = $submission->getLatestPublication();
        if (!$publication) {
            return;
        }

        $datePublished = $publication->getData('datePublished');
        if (!$datePublished) {
            return;
        }

        $publishedYear = (int)date('Y', strtotime($datePublished));
        if ($publishedYear >= $startYear &&
            $publishedYear <= $endYear &&
            isset($annualStats[$publishedYear])
        ) {
            $annualStats[$publishedYear]['published']++;
        }
    }

    /**
     * Process declined submission for annual statistics.
     *
     * @param Submission $submission Declined submission
     * @param array &$annualStats Stats array to update
     * @param int $startYear Range start year
     * @param int $endYear Range end year
     */
    private function processDeclinedAnnual(
        Submission $submission,
        array &$annualStats,
        int $startYear,
        int $endYear,
        array $decisions = []
    ): void {
        $latestDeclineYear = null;

        foreach ($decisions as $decision) {
            if (!in_array($decision->getData('decision'), self::DECLINE_DECISIONS)) {
                continue;
            }

            $dateDecided = $decision->getData('dateDecided');
            if (!$dateDecided) {
                continue;
            }

            $declinedYear = (int)date('Y', strtotime($dateDecided));

            if ($latestDeclineYear === null || $declinedYear > $latestDeclineYear) {
                $latestDeclineYear = $declinedYear;
            }
        }

        if ($latestDeclineYear !== null &&
            $latestDeclineYear >= $startYear &&
            $latestDeclineYear <= $endYear &&
            isset($annualStats[$latestDeclineYear])
        ) {
            $annualStats[$latestDeclineYear]['declined']++;
        }
    }
}
