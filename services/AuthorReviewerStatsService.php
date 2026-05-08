<?php

/**
 * @file plugins/generic/publicStats/services/AuthorReviewerStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for author and reviewer geographic and institutional statistics.
 *
 * Provides aggregated data about author and reviewer distributions by country
 * and institution (contributor diversity), plus an alphabetical per-year
 * reviewer list (`getReviewerList`) used for the FECYT-aligned public
 * acknowledgment of completed peer reviews.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use PKP\submission\PKPSubmission;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Sokil\IsoCodes\IsoCodesFactory;

/**
 * Service for author and reviewer statistics
 */
class AuthorReviewerStatsService
{
    public function __construct(
        private readonly IsoCodesFactory $isoCodes
    ) {}

    /**
     * Get authors by country
     */
    public function getAuthorsByCountry(int $contextId): ?array
    {
        $countryStats = [];

        $authors = Repo::author()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($authors as $author) {
            $country = $author->getCountry();
            if (empty($country)) {
                continue;
            }

            if (!isset($countryStats[$country])) {
                $countryStats[$country] = 0;
            }
            $countryStats[$country]++;
        }

        return empty($countryStats) 
            ? null 
            : $this->formatCountryData($countryStats);
    }

    /**
     * Get authors by institution
     */
    public function getAuthorsByInstitution(int $contextId): ?array
    {
        $institutionStats = [];

        $authors = Repo::author()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($authors as $author) {
            $affiliation = $author->getLocalizedAffiliation();
            
            // Skip authors without affiliation
            if (empty($affiliation)) {
                continue;
            }

            if (!isset($institutionStats[$affiliation])) {
                $institutionStats[$affiliation] = 0;
            }
            $institutionStats[$affiliation]++;
        }

        if (empty($institutionStats)) {
            return null;
        }

        arsort($institutionStats);

        return $this->formatInstitutionData($institutionStats);
    }

    /**
     * Distinct reviewer IDs for a list of submissions.
     *
     * @param int[] $submissionIds
     * @return int[]
     */
    private function getReviewerIdsForSubmissions(array $submissionIds): array
    {
        if (empty($submissionIds)) {
            return [];
        }

        // Match PKP's own ReviewAssignmentDAO: only count assignments that
        // weren't declined or cancelled. Otherwise an editor who invited five
        // people who all rejected would appear as five "active" reviewers.
        return DB::table('review_assignments')
            ->whereIn('submission_id', $submissionIds)
            ->where('declined', '<>', 1)
            ->where('cancelled', '<>', 1)
            ->distinct()
            ->pluck('reviewer_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Get reviewers by country
     */
    public function getReviewersByCountry(int $contextId): ?array
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([
                PKPSubmission::STATUS_PUBLISHED,
                PKPSubmission::STATUS_QUEUED
            ])
            ->getMany();

        $submissionIds = [];
        foreach ($submissions as $submission) {
            $submissionIds[] = $submission->getId();
        }

        $reviewerIds = $this->getReviewerIdsForSubmissions($submissionIds);
        if (empty($reviewerIds)) {
            return null;
        }
        
        $reviewers = Repo::user()
            ->getCollector()
            ->filterByUserIds($reviewerIds)
            ->getMany();
        
        $countryStats = [];
        foreach ($reviewers as $reviewer) {
            $country = $reviewer->getCountry();
            
            if (empty($country)) {
                continue;
            }
            
            if (!isset($countryStats[$country])) {
                $countryStats[$country] = 0;
            }
            $countryStats[$country]++;
        }

        return empty($countryStats) 
            ? null 
            : $this->formatCountryData($countryStats);
    }
    /**
     * Get reviewers by institution
     */
    public function getReviewersByInstitution(int $contextId): ?array
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([
                PKPSubmission::STATUS_PUBLISHED,
                PKPSubmission::STATUS_QUEUED
            ])
            ->getMany();

        $submissionIds = [];
        foreach ($submissions as $submission) {
            $submissionIds[] = $submission->getId();
        }

        $reviewerIds = $this->getReviewerIdsForSubmissions($submissionIds);
        if (empty($reviewerIds)) {
            return null;
        }
        
        $reviewers = Repo::user()
            ->getCollector()
            ->filterByUserIds($reviewerIds)
            ->getMany();
        
        $institutionStats = [];
        foreach ($reviewers as $reviewer) {
            $affiliation = $reviewer->getLocalizedAffiliation();
            
            // Skip reviewers without affiliation
            if (empty($affiliation)) {
                continue;
            }
            
            if (!isset($institutionStats[$affiliation])) {
                $institutionStats[$affiliation] = 0;
            }
            $institutionStats[$affiliation]++;
        }

        if (empty($institutionStats)) {
            return null;
        }

        arsort($institutionStats);

        return $this->formatInstitutionData($institutionStats);
    }

    /**
     * Get alphabetical list of reviewers who completed at least one review in the
     * given year, or all years when $year is null.
     */
    public function getReviewerList(int $contextId, ?int $year): ?array
    {
        $query = DB::table('review_assignments as ra')
            ->join('submissions as s', 'ra.submission_id', '=', 's.submission_id')
            ->where('s.context_id', $contextId)
            ->where('ra.declined', '<>', 1)
            ->where('ra.cancelled', '<>', 1)
            ->whereNotNull('ra.date_completed');

        if ($year !== null) {
            $query->whereBetween('ra.date_completed', [
                "{$year}-01-01 00:00:00",
                "{$year}-12-31 23:59:59",
            ]);
        }

        $reviewerIds = $query
            ->distinct()
            ->pluck('ra.reviewer_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($reviewerIds)) {
            return null;
        }

        $reviewers = Repo::user()
            ->getCollector()
            ->filterByUserIds($reviewerIds)
            ->getMany();

        $list = [];
        foreach ($reviewers as $reviewer) {
            $countryCode = $reviewer->getCountry();
            $countryName = null;
            if ($countryCode && strlen($countryCode) === 2) {
                try {
                    $country = $this->isoCodes->getCountries()->getByAlpha2($countryCode);
                    $countryName = $country ? $country->getLocalName() : $countryCode;
                } catch (\Exception $e) {
                    $countryName = $countryCode;
                }
            }

            $list[] = [
                'fullName'    => $reviewer->getFullName(),
                'affiliation' => $reviewer->getLocalizedAffiliation() ?: null,
                'country'     => $countryName,
            ];
        }

        usort($list, fn($a, $b) => strcmp($a['fullName'], $b['fullName']));

        return $list;
    }

    /**
     * Format country data with country names
     */
    private function formatCountryData(array $countryStats): array
    {
        $formattedData = [];

        foreach ($countryStats as $countryCode => $count) {
            // Ensure countryCode is a valid 2-letter string
            $countryCode = (string) $countryCode;
            if (strlen($countryCode) !== 2) {
                continue;
            }
            
            try {
                $country = $this->isoCodes->getCountries()->getByAlpha2($countryCode);
                $countryName = $country ? $country->getLocalName() : $countryCode;
            } catch (\Exception $e) {
                $countryName = $countryCode;
            }

            $formattedData[] = [
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'total_count' => $count
            ];
        }
        
        usort($formattedData, fn($a, $b) => $b['total_count'] - $a['total_count']);

        return $formattedData;
    }

    /**
     * Format institution data
     */
    private function formatInstitutionData(array $institutionStats): array
    {
        $formattedData = [];
        
        foreach ($institutionStats as $institution => $count) {
            $formattedData[] = [
                'institution' => $institution,
                'total_count' => $count
            ];
        }

        return $formattedData;
    }
}