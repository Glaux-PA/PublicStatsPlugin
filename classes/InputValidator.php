<?php

/**
 * @file plugins/generic/publicStats/classes/InputValidator.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InputValidator
 * @ingroup plugins_generic_publicStats
 *
 * @brief Centralised input validation for public statistics endpoints.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPRequest;
use APP\facades\Repo;

class InputValidator
{
    /**
     * @return string|null Validated YYYY year, or null if out of range.
     */
    public static function validateYear(?string $year): ?string
    {
        if ($year === null || $year === '') {
            return null;
        }

        if (!preg_match('/^\d{4}$/', $year)) {
            return null;
        }

        $yearInt = (int)$year;
        $currentYear = (int)date('Y');

        if ($yearInt < PublicStatsConstants::MIN_YEAR || $yearInt > $currentYear + 1) {
            return null;
        }

        return $year;
    }

    /**
     * @return int|null Validated section ID belonging to $request's context, or null.
     */
    public static function validateSectionId(PKPRequest $request, ?string $sectionId): ?int
    {
        if ($sectionId === null || $sectionId === '') {
            return null;
        }

        if (!ctype_digit($sectionId)) {
            return null;
        }

        $sectionIdInt = (int)$sectionId;
        $contextId = $request->getContext()->getId();

        $section = Repo::section()->get($sectionIdInt);

        if (!$section || $section->getData('contextId') !== $contextId) {
            return null;
        }

        return $sectionIdInt;
    }

    /**
     * Accepts a single ID or comma-separated list (e.g. "1,45,89").
     * Each ID is verified to exist and belong to $request's context.
     *
     * @return string|null Comma-separated valid IDs, or null if none pass.
     */
    public static function validateAuthorId(PKPRequest $request, ?string $authorId): ?string
    {
        if ($authorId === null || $authorId === '') {
            return null;
        }

        $ids = explode(',', $authorId);
        $validIds = [];

        $contextId = $request->getContext()?->getId();

        foreach ($ids as $id) {
            $id = trim($id);

            if (!ctype_digit($id)) {
                continue;
            }

            $authorIdInt = (int)$id;

            if (!Repo::author()->get($authorIdInt)) {
                continue;
            }

            if ($contextId) {
                $belongs = DB::table('authors as a')
                    ->join('publications as p', 'p.publication_id', '=', 'a.publication_id')
                    ->join('submissions as s', 's.submission_id', '=', 'p.submission_id')
                    ->where('a.author_id', $authorIdInt)
                    ->where('s.context_id', $contextId)
                    ->exists();

                if (!$belongs) {
                    continue;
                }
            }

            $validIds[] = $authorIdInt;
        }

        if (empty($validIds)) {
            return null;
        }

        return implode(',', $validIds);
    }

    /**
     * @return int Value clamped to [$min, $max], or $default when input is absent/invalid.
     */
    public static function validateLimit(
        string|int|null $limit,
        int $default = 20,
        int $max = 100,
        int $min = 1
    ): int {
        if ($limit === null || $limit === '') {
            return $default;
        }

        $limitInt = (int)$limit;

        return max($min, min($max, $limitInt));
    }

    /**
     * @return int Value clamped to [$min, $max], or $default when input is absent/invalid.
     */
    public static function validatePositiveInt(
        string|int|null $value,
        int $default = 1,
        int $min = 1,
        int $max = 1000
    ): int {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_string($value) && !ctype_digit($value)) {
            return $default;
        }

        $intValue = (int)$value;

        return max($min, min($max, $intValue));
    }

    /**
     * @return int|null Validated submission ID belonging to $request's context, or null.
     */
    public static function validateSubmissionId(PKPRequest $request, ?string $submissionId): ?int
    {
        if ($submissionId === null || $submissionId === '') {
            return null;
        }

        if (!ctype_digit($submissionId)) {
            return null;
        }

        $submissionIdInt = (int)$submissionId;
        $contextId = $request->getContext()?->getId();

        if (!$contextId) {
            return null;
        }

        $submission = Repo::submission()->get($submissionIdInt);

        if (!$submission || $submission->getData('contextId') !== $contextId) {
            return null;
        }

        return $submissionIdInt;
    }

    /**
     * Validates the structured author keys produced by AuthorStatsService::createAuthorKey().
     * Accepted shapes:
     *   orcid:0000-0002-1234-5678
     *   name_email:word1_word2__emailstripped
     *   name_only:word1_word2
     *
     * @return string|null The key unchanged, or null if it doesn't match any shape.
     */
    public static function validateAuthorKey(?string $authorKey): ?string
    {
        if ($authorKey === null || $authorKey === '') {
            return null;
        }

        $authorKey = trim($authorKey);

        // 500-char cap against abuse.
        if ($authorKey === '' || strlen($authorKey) > 500) {
            return null;
        }

        // Unicode-aware so non-ASCII names pass; control chars, quotes, slashes, etc. are rejected.
        $patterns = [
            '/^orcid:\d{4}-\d{4}-\d{4}-\d{3}[\dXx]$/',
            '/^name_email:[\p{L}\p{N}_]+__[\p{L}\p{N}]+$/u',
            '/^name_only:[\p{L}\p{N}_]+$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $authorKey)) {
                return $authorKey;
            }
        }

        return null;
    }

    public static function sanitizeForCacheKey(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $value);
    }
}
