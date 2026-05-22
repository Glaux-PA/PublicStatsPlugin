<?php

/**
 * @file plugins/generic/publicStats/services/DecisionStatsService.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for editorial decision timing statistics.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\decision\Decision;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\services\BaseStatsService;

class DecisionStatsService extends BaseStatsService
{
    private const DECLINE_DECISIONS = [
        Decision::DECLINE,
        Decision::INITIAL_DECLINE,
        Decision::DECLINE_INTERNAL,
    ];

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

        $submissionIds = [];
        foreach ($submissions as $s) {
            $submissionIds[] = $s->getId();
        }
        $reviewStatus = $this->getReviewStatusForSubmissions($submissionIds);

        $publicationData = $this->processPublications(
            $submissions,
            $reviewStatus,
            $startTime,
            $endTime
        );

        return $this->calculatePublicationAverages($publicationData);
    }

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
     * Two queries total instead of two per submission. Returns assignments as
     * raw rows because OJS 3.4 has no public Repo for ReviewAssignment and we
     * only need date_completed + recommendation downstream.
     *
     * @return array{hasReview: array<int, bool>, reviews: array<int, array<object>>}
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

        $assignmentRows = DB::table('review_assignments')
            ->whereIn('submission_id', $submissionsWithRounds)
            ->where('declined', '<>', 1)
            ->where('cancelled', '<>', 1)
            ->get(['submission_id', 'date_completed', 'recommendation']);

        foreach ($assignmentRows as $row) {
            $subId = (int) $row->submission_id;
            if (!isset($reviewsBySubmission[$subId])) {
                $reviewsBySubmission[$subId] = [];
            }
            $reviewsBySubmission[$subId][] = $row;
        }

        return [
            'hasReview' => $hasReviewBySubmission,
            'reviews' => $reviewsBySubmission,
        ];
    }

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

        usort(
            $decisionsData,
            fn($a, $b) =>
            strtotime($b['date_decided']) - strtotime($a['date_decided'])
        );

        return $decisionsData;
    }

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

        $daysToDecision = (int) floor(
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
     * @param array<object> $reviewAssignments Raw rows from getReviewStatusForSubmissions
     */
    private function getRecommendation(
        bool $hasReview,
        array $reviewAssignments,
        int $dateDecidedTime
    ): int|null {
        if (!$hasReview || empty($reviewAssignments)) {
            return null;
        }

        foreach ($reviewAssignments as $row) {
            $dateCompleted = $row->date_completed ?? null;
            if ($dateCompleted && strtotime($dateCompleted) <= $dateDecidedTime) {
                return $row->recommendation ?? null;
            }
        }

        return null;
    }

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

            if ($publishedTime < $startTime || $publishedTime > $endTime) {
                continue;
            }

            // Normalize for accurate day calculation
            $submissionTimeNormalized = strtotime(date('Y-m-d 00:00:00', $submissionTime));
            $publishedTimeNormalized = strtotime(date('Y-m-d 00:00:00', $publishedTime));

            $daysToPublication = (int) floor(
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

        usort(
            $publicationData,
            fn($a, $b) =>
            strtotime($b['date_published']) - strtotime($a['date_published'])
        );

        return $publicationData;
    }

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
