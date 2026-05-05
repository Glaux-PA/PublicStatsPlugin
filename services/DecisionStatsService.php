<?php

/**
 * @file plugins/generic/publicStats/services/DecisionStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for editorial decision timing statistics.
 *
 * Provides metrics about decision timing including time to first decision
 * and time from acceptance to publication. These metrics help evaluate
 * editorial workflow efficiency.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\services\BaseStatsService;

class DecisionStatsService extends BaseStatsService
{
    /**
     * Decision types indicating rejection.
     */
    private const DECLINE_DECISIONS = [
        Decision::DECLINE,
        Decision::INITIAL_DECLINE,
        Decision::DECLINE_INTERNAL,
    ];

    /**
     * Get first decision statistics.
     *
     * Calculates average time from submission to first editorial decision.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Decision timing statistics
     */
    public function getFirstDecisionStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $dateRange = $this->getDateRange($dateStart, $dateEnd);
        $dateStart = $dateRange['start'];
        $dateEnd = $dateRange['end'];

        $startTime = strtotime($dateStart);
        $endTime = strtotime($dateEnd);

        $submissions = $this->getSubmissionsInRange($contextId, $startTime, $endTime);

        if (empty($submissions)) {
            return $this->getEmptyDecisionStats();
        }

        $submissionIds = array_keys($submissions);
        $decisions = $this->getDecisionsForSubmissions($submissionIds);
        $reviewStatus = $this->getReviewStatusForSubmissions($submissionIds);

        $decisionsData = $this->processFirstDecisions(
            $submissions,
            $decisions,
            $reviewStatus,
            $startTime,
            $endTime
        );

        return $this->calculateAverages($decisionsData);
    }

    /**
     * Get acceptance to publication statistics.
     *
     * Calculates average time from submission to publication for published submissions.
     *
     * @param int $contextId Journal/press ID
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Publication timing statistics
     */
    public function getAcceptancePublicationStats(
        int $contextId,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): array {
        $dateRange = $this->getDateRange($dateStart, $dateEnd);
        $dateStart = $dateRange['start'];
        $dateEnd = $dateRange['end'];

        $startTime = strtotime($dateStart);
        $endTime = strtotime($dateEnd);

        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([\APP\submission\Submission::STATUS_PUBLISHED])
            ->getMany();

        $reviewStatus = $this->getReviewStatusForSubmissions(
            array_map(fn($s) => $s->getId(), iterator_to_array($submissions))
        );

        $publicationData = $this->processPublications(
            $submissions,
            $reviewStatus,
            $startTime,
            $endTime
        );

        return $this->calculatePublicationAverages($publicationData);
    }

    /**
     * Get submissions within date range.
     *
     * @param int $contextId Context ID
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @return array Submissions indexed by ID
     */
    private function getSubmissionsInRange(
        int $contextId,
        int $startTime,
        int $endTime
    ): array {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        $submissionsInRange = [];

        foreach ($submissions as $submission) {
            $dateSubmitted = $submission->getData('dateSubmitted');
            if (!$dateSubmitted) {
                continue;
            }

            $submissionTime = strtotime($dateSubmitted);
            if ($submissionTime >= $startTime && $submissionTime <= $endTime) {
                $submissionsInRange[$submission->getId()] = $submission;
            }
        }

        return $submissionsInRange;
    }

    /**
     * Get all decisions for given submissions.
     *
     * @param array $submissionIds Submission IDs
     * @return array Decisions grouped by submission ID
     */
    private function getDecisionsForSubmissions(array $submissionIds): array
    {
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
     * Whether each submission went through peer review, plus its assignments.
     * Two queries total instead of two per submission; ReviewAssignment objects
     * are rebuilt via _fromRow so callers keep using their getters as before.
     */
    private function getReviewStatusForSubmissions(array $submissionIds): array
    {
        $hasReviewBySubmission = array_fill_keys($submissionIds, false);
        $reviewsBySubmission = [];

        if (empty($submissionIds)) {
            return ['hasReview' => $hasReviewBySubmission, 'reviews' => $reviewsBySubmission];
        }

        $roundRows = DB::table('review_rounds')
            ->whereIn('submission_id', $submissionIds)
            ->get(['submission_id']);

        foreach ($roundRows as $row) {
            $hasReviewBySubmission[(int) $row->submission_id] = true;
        }

        $submissionsWithRounds = array_keys(array_filter($hasReviewBySubmission));
        if (empty($submissionsWithRounds)) {
            return ['hasReview' => $hasReviewBySubmission, 'reviews' => $reviewsBySubmission];
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $assignmentRows = DB::table('review_assignments')
            ->whereIn('submission_id', $submissionsWithRounds)
            ->get();

        foreach ($assignmentRows as $row) {
            $subId = (int) $row->submission_id;
            if (!isset($reviewsBySubmission[$subId])) {
                $reviewsBySubmission[$subId] = [];
            }
            $reviewsBySubmission[$subId][] = $reviewAssignmentDao->_fromRow((array) $row);
        }

        return [
            'hasReview' => $hasReviewBySubmission,
            'reviews' => $reviewsBySubmission,
        ];
    }

    /**
     * Process first decisions for all submissions.
     *
     * @param array $submissions Submissions to process
     * @param array $decisionsBySubmission Decisions grouped by submission
     * @param array $reviewStatus Review status data
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @return array Processed decision data
     */
    private function processFirstDecisions(
        array $submissions,
        array $decisionsBySubmission,
        array $reviewStatus,
        int $startTime,
        int $endTime
    ): array {
        $decisionsData = [];

        foreach ($submissions as $submissionId => $submission) {
            if (
                !isset($decisionsBySubmission[$submissionId]) ||
                empty($decisionsBySubmission[$submissionId])
            ) {
                continue;
            }

            $firstDecision = $this->getFirstDecision(
                $decisionsBySubmission[$submissionId]
            );

            if (!$firstDecision) {
                continue;
            }

            $decisionData = $this->buildDecisionData(
                $submission,
                $firstDecision,
                $reviewStatus,
                $submissionId
            );

            if ($decisionData !== null) {
                $decisionsData[] = $decisionData;
            }
        }

        // Sort by decision date (most recent first)
        usort(
            $decisionsData,
            fn($a, $b) =>
            strtotime($b['date_decided']) - strtotime($a['date_decided'])
        );

        return $decisionsData;
    }

    /**
     * Get the first decision from a list.
     *
     * @param array $decisions Array of decisions
     * @return object|null First decision or null
     */
    private function getFirstDecision(array $decisions): ?object
    {
        $firstDecision = null;
        $minDate = null;

        foreach ($decisions as $decision) {
            $decisionDate = $decision->getData('dateDecided');
            if (!$decisionDate) {
                continue;
            }

            $decisionTime = strtotime($decisionDate);
            if ($minDate === null || $decisionTime < $minDate) {
                $minDate = $decisionTime;
                $firstDecision = $decision;
            }
        }

        return $firstDecision;
    }

    /**
     * Build decision data array.
     *
     * @param object $submission Submission object
     * @param object $decision Decision object
     * @param array $reviewStatus Review status data
     * @param int $submissionId Submission ID
     * @return array|null Decision data or null if invalid
     */
    private function buildDecisionData(
        object $submission,
        object $decision,
        array $reviewStatus,
        int $submissionId
    ): ?array {
        $dateSubmitted = $submission->getData('dateSubmitted');
        $dateDecided = $decision->getData('dateDecided');

        if (!$dateSubmitted || !$dateDecided) {
            return null;
        }

        $submissionTime = strtotime($dateSubmitted);
        $dateDecidedTime = strtotime($dateDecided);

        // Normalize to midnight for accurate day calculation
        $submissionTimeNormalized = strtotime(date('Y-m-d 00:00:00', $submissionTime));
        $dateDecidedTimeNormalized = strtotime(date('Y-m-d 00:00:00', $dateDecidedTime));

        $daysToDecision = floor(
            ($dateDecidedTimeNormalized - $submissionTimeNormalized) / (60 * 60 * 24)
        );

        if ($daysToDecision < 0) {
            return null;
        }

        $hasReview = $reviewStatus['hasReview'][$submissionId] ?? false;
        $recommendation = $this->getRecommendation(
            $hasReview,
            $reviewStatus['reviews'][$submissionId] ?? [],
            $dateDecidedTime
        );

        $decisionType = $decision->getData('decision');

        return [
            'submission_id' => $submissionId,
            'date_submitted' => date('Y-m-d', $submissionTime),
            'date_decided' => date('Y-m-d', $dateDecidedTime),
            'days_to_decision' => $daysToDecision,
            'decision_type' => $this->getDecisionTypeName($decisionType),
            'recommendation' => $recommendation,
            'has_review' => $hasReview
        ];
    }

    /**
     * Get recommendation from review assignments.
     *
     * @param bool $hasReview Whether submission had review
     * @param array $reviewAssignments Review assignments
     * @param int $dateDecidedTime Decision timestamp
     * @return string|null Recommendation or null
     */
    private function getRecommendation(
        bool $hasReview,
        array $reviewAssignments,
        int $dateDecidedTime
    ): ?string {
        if (!$hasReview || empty($reviewAssignments)) {
            return null;
        }

        foreach ($reviewAssignments as $assignment) {
            $dateCompleted = $assignment->getDateCompleted();
            if ($dateCompleted && strtotime($dateCompleted) <= $dateDecidedTime) {
                return $assignment->getRecommendation();
            }
        }

        return null;
    }

    /**
     * Process publications for timing statistics.
     *
     * @param iterable $submissions Published submissions
     * @param array $reviewStatus Review status data
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @return array Publication timing data
     */
    private function processPublications(
        iterable $submissions,
        array $reviewStatus,
        int $startTime,
        int $endTime
    ): array {
        $publicationData = [];

        foreach ($submissions as $submission) {
            $dateSubmitted = $submission->getData('dateSubmitted');
            if (!$dateSubmitted) {
                continue;
            }

            $publication = $submission->getLatestPublication();
            if (!$publication) {
                continue;
            }

            $datePublished = $publication->getData('datePublished');
            if (!$datePublished) {
                continue;
            }

            $submissionTime = strtotime($dateSubmitted);
            $publishedTime = strtotime($datePublished);

            // Filter by publication date range
            if ($publishedTime < $startTime || $publishedTime > $endTime) {
                continue;
            }

            // Normalize for accurate day calculation
            $submissionTimeNormalized = strtotime(date('Y-m-d 00:00:00', $submissionTime));
            $publishedTimeNormalized = strtotime(date('Y-m-d 00:00:00', $publishedTime));

            $daysToPublication = floor(
                ($publishedTimeNormalized - $submissionTimeNormalized) / (60 * 60 * 24)
            );

            if ($daysToPublication < 0) {
                continue;
            }

            $submissionId = $submission->getId();
            $hasReview = $reviewStatus['hasReview'][$submissionId] ?? false;

            $publicationData[] = [
                'submission_id' => $submissionId,
                'date_submitted' => date('Y-m-d', $submissionTime),
                'date_published' => date('Y-m-d', $publishedTime),
                'days_to_publication' => $daysToPublication,
                'has_review' => $hasReview
            ];
        }

        // Sort by publication date (most recent first)
        usort(
            $publicationData,
            fn($a, $b) =>
            strtotime($b['date_published']) - strtotime($a['date_published'])
        );

        return $publicationData;
    }

    /**
     * Calculate average decision times.
     *
     * @param array $decisionsData Decision timing data
     * @return array Computed averages and individual decisions
     */
    private function calculateAverages(array $decisionsData): array
    {
        $totalDaysReviewed = 0;
        $countReviewed = 0;
        $totalDaysAll = 0;
        $countAll = count($decisionsData);

        foreach ($decisionsData as $data) {
            $totalDaysAll += $data['days_to_decision'];

            if ($data['has_review']) {
                $totalDaysReviewed += $data['days_to_decision'];
                $countReviewed++;
            }
        }

        return [
            'average_days_reviewed' => $countReviewed > 0
                ? round($totalDaysReviewed / $countReviewed, 1)
                : 0,
            'average_days_all' => $countAll > 0
                ? round($totalDaysAll / $countAll, 1)
                : 0,
            'count_reviewed' => $countReviewed,
            'count_all' => $countAll,
            'decisions' => $decisionsData
        ];
    }

    /**
     * Calculate average publication times.
     *
     * @param array $publicationData Publication timing data
     * @return array Computed averages and individual publications
     */
    private function calculatePublicationAverages(array $publicationData): array
    {
        $totalDaysReviewed = 0;
        $countReviewed = 0;
        $totalDaysAll = 0;
        $countAll = count($publicationData);

        foreach ($publicationData as $data) {
            $totalDaysAll += $data['days_to_publication'];

            if ($data['has_review']) {
                $totalDaysReviewed += $data['days_to_publication'];
                $countReviewed++;
            }
        }

        return [
            'average_days_reviewed' => $countReviewed > 0
                ? round($totalDaysReviewed / $countReviewed, 1)
                : 0,
            'average_days_all' => $countAll > 0
                ? round($totalDaysAll / $countAll, 1)
                : 0,
            'count_reviewed' => $countReviewed,
            'count_all' => $countAll,
            'publications' => $publicationData
        ];
    }

    /**
     * Get empty decision stats structure.
     *
     * @return array Empty stats array
     */
    private function getEmptyDecisionStats(): array
    {
        return [
            'average_days_reviewed' => 0,
            'average_days_all' => 0,
            'count_reviewed' => 0,
            'count_all' => 0,
            'decisions' => []
        ];
    }

    /**
     * Get human-readable decision type name.
     *
     * @param int $decisionType Decision constant
     * @return string Localized decision type name
     */
    private function getDecisionTypeName(int $decisionType): string
    {
        $acceptDecisions = [
            Decision::ACCEPT,
            Decision::ACCEPT_INTERNAL,
        ];

        $revisionDecisions = [
            Decision::PENDING_REVISIONS,
            Decision::RESUBMIT,
            Decision::PENDING_REVISIONS_INTERNAL,
            Decision::RESUBMIT_INTERNAL,
        ];

        $reviewDecisions = [
            Decision::EXTERNAL_REVIEW,
            Decision::INTERNAL_REVIEW,
            Decision::NEW_EXTERNAL_ROUND,
            Decision::NEW_INTERNAL_ROUND,
        ];

        if (in_array($decisionType, $acceptDecisions)) {
            return __('plugins.generic.publicStats.decision.accepted');
        } elseif (in_array($decisionType, self::DECLINE_DECISIONS)) {
            return __('plugins.generic.publicStats.decision.declined');
        } elseif (in_array($decisionType, $revisionDecisions)) {
            return __('plugins.generic.publicStats.decision.revisions');
        } elseif (in_array($decisionType, $reviewDecisions)) {
            return __('plugins.generic.publicStats.decision.sentToReview');
        } elseif ($decisionType === Decision::SEND_TO_PRODUCTION) {
            return __('plugins.generic.publicStats.decision.sentToProduction');
        }

        return __('plugins.generic.publicStats.decision.other');
    }
}
