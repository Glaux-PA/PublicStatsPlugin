<?php

/**
 * @file plugins/generic/publicStats/services/LanguageStatsService.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LanguageStatsService
 * @ingroup plugins_generic_publicStats
 *
 * @brief Service for language distribution statistics of published articles.
 *
 * Counts published articles by language. An article available in multiple
 * locales is counted once per language (normalised: es_ES and es_MX both
 * collapse to es). Language names are returned in the current UI locale.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\services;

use Illuminate\Support\Facades\DB;
use PKP\facades\Locale;
use PKP\submission\PKPSubmission;

class LanguageStatsService
{
    /**
     * Get article counts grouped by language.
     *
     * Determines languages from publication_settings where setting_name='title'
     * and the value is non-empty. An article with titles in two locales is
     * counted in both language categories.
     *
     * @param int $contextId
     * @param int|null $issueId Null = all published issues
     * @return array [['code' => 'es', 'name' => 'Español', 'count' => 42], ...]
     */
    public function getLanguageStats(int $contextId, ?int $issueId = null): array
    {
        $query = DB::table('publications as p')
            ->join('publication_settings as ps', function ($join) {
                $join->on('ps.publication_id', '=', 'p.publication_id')
                    ->where('ps.setting_name', '=', 'title')
                    ->whereNotNull('ps.locale')
                    ->where('ps.locale', '!=', '')
                    ->whereNotNull('ps.setting_value')
                    ->where('ps.setting_value', '!=', '');
            })
            ->join('submissions as s', function ($join) use ($contextId) {
                $join->on('s.submission_id', '=', 'p.submission_id')
                    ->where('s.context_id', '=', $contextId)
                    ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED);
            })
            ->where('p.status', '=', PKPSubmission::STATUS_PUBLISHED)
            ->select('ps.locale', DB::raw('COUNT(DISTINCT p.submission_id) as article_count'))
            ->groupBy('ps.locale');

        if ($issueId !== null) {
            $query->join('publication_settings as ps_issue', function ($join) use ($issueId) {
                $join->on('ps_issue.publication_id', '=', 'p.publication_id')
                    ->where('ps_issue.setting_name', '=', 'issueId')
                    ->where('ps_issue.locale', '=', '')
                    ->where('ps_issue.setting_value', '=', (string) $issueId);
            });
        }

        $rows = $query->orderByDesc('article_count')->get();

        $uiLocale = Locale::getLocale();

        // Normalize locale variants to primary language (es_ES + es_MX → es)
        $merged = [];
        foreach ($rows as $row) {
            $langCode = \Locale::getPrimaryLanguage($row->locale) ?: $row->locale;
            if (!isset($merged[$langCode])) {
                $displayName = \Locale::getDisplayLanguage($row->locale, $uiLocale);
                $name = $displayName
                    ? mb_strtoupper(mb_substr($displayName, 0, 1)) . mb_substr($displayName, 1)
                    : $langCode;
                $merged[$langCode] = ['code' => $langCode, 'name' => $name, 'count' => 0];
            }
            $merged[$langCode]['count'] += (int) $row->article_count;
        }

        usort($merged, fn($a, $b) => $b['count'] - $a['count']);

        return array_values($merged);
    }

    /**
     * Get published issues ordered by date desc for the filter dropdown.
     *
     * @param int $contextId
     * @return array [['id' => 5, 'label' => 'Vol. 12 No. 2 (2024)'], ...]
     */
    public function getPublishedIssues(int $contextId): array
    {
        $rows = DB::table('issues')
            ->where('journal_id', '=', $contextId)
            ->where('published', '=', 1)
            ->orderByDesc('date_published')
            ->select('issue_id', 'volume', 'number', 'year', 'show_volume', 'show_number', 'show_year', 'show_title')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = ['id' => $row->issue_id, 'label' => $this->buildIssueLabel($row)];
        }

        return $result;
    }

    private function buildIssueLabel(object $issue): string
    {
        $parts = [];
        if ($issue->show_volume && $issue->volume) {
            $parts[] = __('issue.vol') . ' ' . $issue->volume;
        }
        if ($issue->show_number && $issue->number) {
            $parts[] = __('issue.no') . ' ' . $issue->number;
        }
        if ($issue->show_year && $issue->year) {
            $parts[] = '(' . $issue->year . ')';
        }
        if (!empty($parts)) {
            return implode(' ', $parts);
        }

        // Fallback: use the issue title from settings
        $title = DB::table('issue_settings')
            ->where('issue_id', '=', $issue->issue_id)
            ->where('setting_name', '=', 'title')
            ->value('setting_value');

        return $title ?: '#' . $issue->issue_id;
    }
}
