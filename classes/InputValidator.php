<?php

/**
 * @file plugins/generic/publicStats/classes/InputValidator.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InputValidator
 * @ingroup plugins_generic_publicStats
 *
 * @brief Input validation and sanitization for public statistics endpoints.
 *
 * Provides centralized validation methods to ensure data integrity and security.
 * All user-supplied parameters are validated before use to prevent SQL injection,
 * XSS attacks, and unauthorized access to resources. Methods return null for
 * invalid input rather than throwing exceptions, allowing graceful handling.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

use PKP\core\PKPRequest;
use APP\facades\Repo;

class InputValidator
{
    /**
     * Validate and sanitize year parameter.
     *
     * Ensures year is a 4-digit number within a reasonable range
     * (1900 to current year + 1). Returns null for invalid input
     * to allow callers to use default values.
     *
     * @param string|null $year User-provided year (expected format: YYYY)
     * @return string|null Validated year or null if invalid
     */
    public static function validateYear(?string $year): ?string
    {
        if ($year === null || $year === '') {
            return null;
        }

        // Require exactly 4 digits
        if (!preg_match('/^\d{4}$/', $year)) {
            return null;
        }

        $yearInt = (int)$year;
        $currentYear = (int)date('Y');

        // Allow from 1900 to next year (for planning purposes)
        if ($yearInt < 1900 || $yearInt > $currentYear + 1) {
            return null;
        }

        return $year;
    }

    /**
     * Validate section ID and verify context ownership.
     *
     * Ensures the section ID is numeric and belongs to the current context,
     * preventing unauthorized access to other journals' sections.
     *
     * @param PKPRequest $request Current request for context verification
     * @param string|null $sectionId User-provided section ID
     * @return int|null Validated section ID or null if invalid/unauthorized
     */
    public static function validateSectionId(PKPRequest $request, ?string $sectionId): ?int
    {
        if ($sectionId === null || $sectionId === '') {
            return null;
        }

        // Must be numeric to prevent SQL injection
        if (!ctype_digit($sectionId)) {
            return null;
        }

        $sectionIdInt = (int)$sectionId;
        $contextId = $request->getContext()->getId();

        // Verify section exists and belongs to current context
        $section = Repo::section()->get($sectionIdInt);

        if (!$section || $section->getData('contextId') !== $contextId) {
            return null;
        }

        return $sectionIdInt;
    }

    /**
     * Validate author ID(s).
     *
     * Accepts single ID or comma-separated IDs (e.g., "1,45,89").
     * Each ID is verified to exist in the database.
     *
     * @param PKPRequest $request Current request object
     * @param string|null $authorId Single ID or comma-separated IDs
     * @return string|null Validated author ID(s) or null if all invalid
     */
    public static function validateAuthorId(PKPRequest $request, ?string $authorId): ?string
    {
        if ($authorId === null || $authorId === '') {
            return null;
        }

        $ids = explode(',', $authorId);
        $validIds = [];

        foreach ($ids as $id) {
            $id = trim($id);

            // Skip non-numeric IDs
            if (!ctype_digit($id)) {
                continue;
            }

            $authorIdInt = (int)$id;
            $author = Repo::author()->get($authorIdInt);

            if ($author) {
                $validIds[] = $authorIdInt;
            }
        }

        if (empty($validIds)) {
            return null;
        }

        return implode(',', $validIds);
    }

    /**
     * Validate limit parameter for pagination.
     *
     * Ensures the limit is within specified bounds, defaulting to
     * a safe value if invalid or not provided.
     *
     * @param string|int|null $limit User-provided limit
     * @param int $default Default value if null/empty
     * @param int $max Maximum allowed value
     * @param int $min Minimum allowed value
     * @return int Validated limit within bounds
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
     * Validate positive integer with optional range constraints.
     *
     * General-purpose validator for numeric parameters that must be positive.
     *
     * @param string|int|null $value User-provided value
     * @param int $default Default if null/invalid
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int Validated integer within bounds
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
     * Validate submission ID and verify context ownership.
     *
     * Ensures the submission belongs to the current context,
     * preventing unauthorized access to other journals' submissions.
     *
     * @param PKPRequest $request Current request object
     * @param string|null $submissionId User-provided submission ID
     * @return int|null Validated submission ID or null if invalid/unauthorized
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
     * Validate author key format.
     *
     * Author keys use prefixes to indicate the identification method.
     * Each prefix has its own whitelist of allowed characters (built from the
     * output of AuthorStatsService::createAuthorKey), so anything that doesn't
     * match an expected shape is rejected outright.
     *
     *   - orcid:0000-0002-1234-5678 (digits + optional trailing X)
     *   - name_email:word1_word2__emailstripped (unicode letters/digits + underscore)
     *   - name_only:word1_word2 (unicode letters/digits + underscore)
     *
     * @param string|null $authorKey User-provided author key
     * @return string|null Validated author key or null if invalid
     */
    public static function validateAuthorKey(?string $authorKey): ?string
    {
        if ($authorKey === null || $authorKey === '') {
            return null;
        }

        $authorKey = trim($authorKey);

        // Prevent abuse with overly long keys
        if ($authorKey === '' || strlen($authorKey) > 500) {
            return null;
        }

        // Strict per-prefix whitelist patterns. Unicode-aware to preserve
        // non-ASCII names, but never allows control chars, quotes, slashes, etc.
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

    /**
     * Sanitize string for use in cache keys.
     *
     * Removes all characters except alphanumeric and underscores
     * to ensure cache key compatibility across different storage backends.
     *
     * @param string $value String to sanitize
     * @return string Sanitized string safe for cache keys
     */
    public static function sanitizeForCacheKey(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $value);
    }
}
