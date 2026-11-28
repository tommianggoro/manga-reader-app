<?php
/**
 * Chapter Number Formatting Helpers
 */

namespace MangaReader\Helpers;

class ChapterFormatter
{
    /**
     * Format chapter number for display.
     * 1.0 -> "1", 1.5 -> "1.5", 1.50 -> "1.5", 1.25 -> "1.25"
     */
    public static function format($num): string
    {
        if ($num === null || $num === "") return "";
        $num = (float) $num;
        if ($num == (int) $num) {
            return (string) (int) $num;
        }
        return rtrim(rtrim(number_format($num, 2, ".", ""), "0"), ".");
    }

    /**
     * Format with "Ch." prefix: 1.5 -> "Ch. 1.5"
     */
    public static function formatWithPrefix($num, string $prefix = 'Ch.'): string
    {
        $formatted = self::format($num);
        return $formatted ? "$prefix $formatted" : '';
    }

    /**
     * Compare two chapter numbers numerically
     */
    public static function compare($a, $b): int
    {
        return (float) $a <=> (float) $b;
    }

    /**
     * Check if chapter number is a decimal (e.g., 1.5, 154.5)
     */
    public static function isDecimal($num): bool
    {
        if ($num === null || $num === "") return false;
        $num = (float) $num;
        return $num != (int) $num;
    }

    /**
     * Get next chapter number (increment by 1, or by 0.5 if decimal exists)
     */
    public static function next($num): float
    {
        $num = (float) ($num ?? 0);
        if (self::isDecimal($num)) {
            // If we have decimals, increment by 0.5
            return $num + 0.5;
        }
        return $num + 1;
    }
}