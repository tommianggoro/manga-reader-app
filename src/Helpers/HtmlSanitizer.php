<?php
/**
 * HTML Sanitization Helpers
 */

namespace MangaReader\Helpers;

class HtmlSanitizer
{
    /**
     * Escape HTML special characters
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape for HTML attribute (double quotes)
     */
    public static function escapeAttr(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape for JavaScript string context
     */
    public static function escapeJs(string $string): string
    {
        return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Sanitize URL - only allow http/https
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return '';
    }

    /**
     * Sanitize for CSS class/id
     */
    public static function sanitizeClass(string $class): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $class);
    }
}