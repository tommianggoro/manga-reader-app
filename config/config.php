<?php
/**
 * Main Config Entry Point
 * Includes all config modules for backward compatibility.
 * Existing code using `require_once "config.php"` will continue to work.
 */

require_once __DIR__ . "/constants.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/rate_limit.php";

/**
 * Format chapter number for display.
 * 1.0 -> "1", 1.5 -> "1.5", 1.50 -> "1.5"
 */
function formatChapterNumber($num)
{
    if ($num === null || $num === "") return "";
    $num = (float) $num;
    if ($num == (int) $num) {
        return (string) (int) $num;
    }
    return rtrim(rtrim(number_format($num, 2, ".", ""), "0"), ".");
}