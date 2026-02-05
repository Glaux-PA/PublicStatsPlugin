<?php

/**
 * @file plugins/generic/publicStats/services/AuthorReviewerStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for author and reviewer geographic and institutional statistics.
 *
 * Provides aggregated data about author and reviewer distributions by country
 * and institution, useful for understanding the journal's contributor diversity.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use PKP\submission\PKPSubmission;
use APP\facades\Repo;
use PKP\db\DAORegistry;
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
     * Get reviewers by country
     */
  public function getReviewersByCountry(int $contextId): ?array
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        
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
        
        if (empty($submissionIds)) {
            return null;
        }
        
        $reviewerIds = [];
        $result = $reviewAssignmentDao->retrieve(
            'SELECT DISTINCT reviewer_id FROM review_assignments WHERE submission_id IN (' . 
            implode(',', array_map('intval', $submissionIds)) . ')'
        );
        
        while ($row = $result->current()) {
            $reviewerIds[] = (int)$row->reviewer_id;
            $result->next();
        }
        
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
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        
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
        
        if (empty($submissionIds)) {
            return null;
        }
        
  
        $reviewerIds = [];
        $result = $reviewAssignmentDao->retrieve(
            'SELECT DISTINCT reviewer_id FROM review_assignments WHERE submission_id IN (' . 
            implode(',', array_map('intval', $submissionIds)) . ')'
        );
        
        while ($row = $result->current()) {
            $reviewerIds[] = (int)$row->reviewer_id;
            $result->next();
        }
        
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