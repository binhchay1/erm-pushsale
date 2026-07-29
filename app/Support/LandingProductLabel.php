<?php

namespace App\Support;

/**
 * Sanitize landing/webhook product labels so page URLs and tracking junk
 * never become order_items.product_name or fake unit prices.
 */
final class LandingProductLabel
{
    public static function looksLikeUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return true;
        }

        if (preg_match('#^www\.#i', $value) === 1) {
            return true;
        }

        // Bare domain + path often pasted into LadiPage "products" field.
        return preg_match(
            '#^[a-z0-9.-]+\.(click|shop|vn|com|net|app|online|store|site)(/|\?|$)#i',
            $value,
        ) === 1;
    }

    /**
     * Return a safe product display name, or null when the label is noise (URL / empty).
     */
    public static function sanitizeName(?string $label): ?string
    {
        $label = trim(preg_replace('/\s+/u', ' ', (string) $label) ?? (string) $label);
        if ($label === '') {
            return null;
        }

        if (self::looksLikeUrl($label)) {
            return null;
        }

        // Monitor hints / discarded URL placeholders must never become order lines.
        if (preg_match('/^URL landing\b/iu', $label) === 1) {
            return null;
        }

        // Extremely long labels are almost always tracking dumps, not product names.
        if (mb_strlen($label) > 180) {
            return null;
        }

        return $label;
    }

    /**
     * Short host/path hint for monitoring when a URL was rejected as product name.
     */
    public static function urlMonitorHint(string $value): string
    {
        $value = trim($value);
        if (preg_match('~https?://([^/?#]+)~i', $value, $matches) === 1) {
            $path = parse_url($value, PHP_URL_PATH);

            return $matches[1].(is_string($path) && $path !== '' && $path !== '/' ? $path : '');
        }

        return mb_substr($value, 0, 120);
    }
}
