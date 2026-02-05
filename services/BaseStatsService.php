<?php

/**
 * @file plugins/generic/publicStats/services/BaseStatsService.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Abstract base class for statistics services.
 *
 * Provides common functionality shared across all statistics services,
 * including date range handling, submission retrieval, and utility methods.
 * Concrete service classes should extend this class.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use APP\facades\Repo;
use PKP\submission\PKPSubmission;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;

abstract class BaseStatsService
{
    /**
     * Get published submissions for a context.
     *
     * Retrieves all submissions with STATUS_PUBLISHED for the given journal/press.
     *
     * @param int $contextId Journal/press ID
     * @return iterable Collection of published submissions
     */
    protected function getPublishedSubmissions(int $contextId): iterable
    {
        return Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getMany();
    }

    /**
     * Get date range with defaults.
     *
     * Returns start and end dates for statistics queries, using defaults
     * from PublicStatsConstants if not provided.
     *
     * @param string|null $dateStart Start date in Ymd format
     * @param string|null $dateEnd End date in Ymd format
     * @return array Associative array with 'start' and 'end' keys
     */
    protected function getDateRange(?string $dateStart = null, ?string $dateEnd = null): array
    {
        return [
            'start' => $dateStart ?? (PublicStatsConstants::MIN_YEAR . '0101'),
            'end' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];
    }

    /**
     * Format date for database query.
     *
     * Converts any parseable date string to Ymd format required by statistics queries.
     *
     * @param string $date Date in any format parseable by strtotime
     * @return string Date in Ymd format
     */
    protected function formatDateForDB(string $date): string
    {
        return date('Ymd', strtotime($date));
    }

    /**
     * Check if data is empty or null.
     *
     * Utility method to determine if a result set should be considered empty.
     *
     * @param mixed $data Data to check
     * @return bool True if data is null or empty array
     */
    protected function isEmpty($data): bool
    {
        return $data === null || (is_array($data) && empty($data));
    }
}
