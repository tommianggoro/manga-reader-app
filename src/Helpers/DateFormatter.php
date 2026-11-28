<?php
/**
 * Date Formatting Helpers
 */

namespace MangaReader\Helpers;

class DateFormatter
{
    private static array $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        7 => 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    /**
     * Format timestamp to Indonesian date: "15 Januari 2024"
     */
    public static function indoDate(string $timestamp): string
    {
        if (!$timestamp) return '-';
        $dt = new \DateTime($timestamp);
        $day = (int) $dt->format('j');
        $month = (int) $dt->format('n');
        $year = $dt->format('Y');
        return "$day " . (self::$bulanIndo[$month] ?? $dt->format('F')) . " $year";
    }

    /**
     * Format timestamp to Indonesian datetime: "15 Januari 2024 14:30"
     */
    public static function indoDateTime(string $timestamp): string
    {
        if (!$timestamp) return '-';
        $dt = new \DateTime($timestamp);
        $day = (int) $dt->format('j');
        $month = (int) $dt->format('n');
        $year = $dt->format('Y');
        $time = $dt->format('H:i');
        return "$day " . (self::$bulanIndo[$month] ?? $dt->format('F')) . " $year $time";
    }

    /**
     * Format timestamp to time only: "14:30"
     */
    public static function timeOnly(string $timestamp): string
    {
        if (!$timestamp) return '-';
        $dt = new \DateTime($timestamp);
        return $dt->format('H:i');
    }

    /**
     * Relative time: "2 jam lalu", "3 hari lalu"
     */
    public static function relativeTime(string $timestamp): string
    {
        if (!$timestamp) return '-';
        $dt = new \DateTime($timestamp);
        $diff = time() - $dt->getTimestamp();

        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
        if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
        if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
        if ($diff < 2592000) return floor($diff / 604800) . ' minggu lalu';

        return self::indoDate($timestamp);
    }

    /**
     * Format for <time> datetime attribute (ISO 8601)
     */
    public static function iso8601(string $timestamp): string
    {
        if (!$timestamp) return '';
        return (new \DateTime($timestamp))->format('c');
    }
}