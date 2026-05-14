<?php

/**
 * @file plugins/generic/publicStats/classes/ColorHelper.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ColorHelper
 * @ingroup plugins_generic_publicStats
 *
 * @brief Helper class for color manipulation and variant generation.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats\classes;

class ColorHelper
{
    /** @var string Default primary color (burgundy) */
    public const DEFAULT_COLOR = '#8b2635';

    /**
     * @param string $hex Hex color (e.g., '#8b2635' or '8b2635')
     * @return array Associative array with keys: primary, light, dark, darker, rgb
     */
    public static function calculateVariants(string $hex): array
    {
        $hex = strtolower(ltrim($hex, '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[a-f0-9]{6}$/', $hex)) {
            return self::getDefaultVariants();
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return [
            'primary' => "#{$hex}",
            'light' => self::adjustBrightness($r, $g, $b, 1.12),   // ~12% lighter
            'dark' => self::adjustBrightness($r, $g, $b, 0.77),    // ~23% darker
            'darker' => self::adjustBrightness($r, $g, $b, 0.65),  // ~35% darker
            'rgb' => "{$r}, {$g}, {$b}"
        ];
    }

    /**
     * @param float $factor >1 = lighter, <1 = darker
     */
    private static function adjustBrightness(int $r, int $g, int $b, float $factor): string
    {
        $newR = (int) round(min(255, max(0, $r * $factor)));
        $newG = (int) round(min(255, max(0, $g * $factor)));
        $newB = (int) round(min(255, max(0, $b * $factor)));

        return sprintf('#%02x%02x%02x', $newR, $newG, $newB);
    }

    public static function getDefaultVariants(): array
    {
        return [
            'primary' => '#8b2635',
            'light' => '#9b3645',
            'dark' => '#6b1e2a',
            'darker' => '#5b1620',
            'rgb' => '139, 38, 53'
        ];
    }

    public static function isValidHex(string $color): bool
    {
        return (bool) preg_match('/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color);
    }

    public static function normalizeHex(string $color): string
    {
        $color = ltrim($color, '#');
        return '#' . $color;
    }

    /** @return array|null RGB array [r, g, b] or null if invalid */
    public static function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[a-fA-F0-9]{6}$/', $hex)) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    public static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        );
    }

    /** Based on WCAG 2.0 formula. */
    public static function getLuminance(string $hex): float
    {
        $rgb = self::hexToRgb($hex);
        if (!$rgb) {
            return 0;
        }

        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** @return string '#ffffff' or '#000000' */
    public static function getContrastTextColor(string $backgroundColor): string
    {
        $luminance = self::getLuminance($backgroundColor);
        return $luminance > 0.179 ? '#000000' : '#ffffff';
    }
}