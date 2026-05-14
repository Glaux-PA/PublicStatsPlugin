<?php

/**
 * @file plugins/generic/publicStats/services/BaseStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
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
    protected function getPublishedSubmissions(int $contextId): iterable
    {
        return Repo::submission()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->getMany();
    }

    protected function getDateRange(?string $dateStart = null, ?string $dateEnd = null): array
    {
        return [
            'start' => $dateStart ?? (PublicStatsConstants::MIN_YEAR . '0101'),
            'end' => $dateEnd ?? date('Ymd', strtotime('yesterday'))
        ];
    }

    protected function formatDateForDB(string $date): string
    {
        return date('Ymd', strtotime($date));
    }

    protected function isEmpty($data): bool
    {
        return $data === null || (is_array($data) && empty($data));
    }
}
