<?php

/**
 * @file plugins/generic/publicStats/classes/Logger.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Logger
 * @ingroup plugins_generic_publicStats
 *
 * @brief Thin wrapper over error_log that prepends a consistent plugin prefix.
 *
 * Centralising this lets you grep server logs for "[publicStats]" to find
 * every message produced by this plugin, regardless of which service or
 * handler emitted it.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

class Logger
{
    private const PREFIX = '[publicStats]';

    public static function error(string $message, ?\Throwable $e = null): void
    {
        error_log(self::format($message, $e));
    }

    /**
     * Log a warning (non-fatal degraded behaviour, e.g. retry exhausted).
     */
    public static function warning(string $message, ?\Throwable $e = null): void
    {
        error_log(self::format('WARN: ' . $message, $e));
    }

    private static function format(string $message, ?\Throwable $e): string
    {
        $line = self::PREFIX . ' ' . $message;
        if ($e !== null) {
            $line .= ' | ' . $e->getMessage();
        }
        return $line;
    }
}
