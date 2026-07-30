<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Single source for Excel/CSV download brand + filename slug.
 * Call sites must not hardcode "pushsale-" prefixes.
 */
final class ReportExportIdentity
{
    public static function brand(): string
    {
        $name = trim((string) config('app.name', ''));
        if ($name !== '' && ! self::containsPushsale($name)) {
            return $name;
        }

        $saleops = trim((string) config('saleops.brand.name', ''));
        if ($saleops !== '' && ! self::containsPushsale($saleops)) {
            return $saleops;
        }

        return 'ERM SaleOps';
    }

    public static function slug(): string
    {
        $slug = Str::slug(self::brand(), '-');
        if ($slug === '' || self::containsPushsale($slug)) {
            $slug = Str::slug((string) config('saleops.brand.short', 'saleops'), '-') ?: 'saleops';
        }

        return $slug;
    }

    /**
     * Build a download basename (no extension). Strips legacy "pushsale" tokens.
     *
     * @param  string  ...$parts  e.g. page code, report key, timestamp fragment
     */
    public static function basename(string ...$parts): string
    {
        $cleaned = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $part = preg_replace('/^pushsale[-_.]*/i', '', $part) ?? $part;
            $part = preg_replace('/[-_.]?pushsale[-_.]?/i', '-', $part) ?? $part;
            $part = trim($part, '-_.');
            if ($part !== '') {
                $cleaned[] = $part;
            }
        }

        $tail = implode('-', $cleaned);
        $slug = self::slug();

        if ($tail === '') {
            return $slug.'-report-'.now()->format('Ymd-His');
        }

        if (str_starts_with(Str::lower($tail), Str::lower($slug).'-')) {
            return $tail;
        }

        return $slug.'-'.$tail;
    }

    /** Normalize a caller-supplied filename and strip .xls/.csv extensions. */
    public static function sanitizeFilename(string $filename): string
    {
        $name = preg_replace('/\.(xls|xlsx|csv)$/i', '', $filename) ?: $filename;
        $name = preg_replace('/^pushsale[-_.]*/i', '', $name) ?? $name;
        $name = preg_replace('/[-_.]?pushsale[-_.]?/i', '-', $name) ?? $name;
        $name = trim($name, '-_.');
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name) ?: 'report';

        $slug = self::slug();
        if (! str_starts_with(Str::lower($safe), Str::lower($slug))) {
            $safe = $slug.'-'.ltrim($safe, '-_');
        }

        return $safe;
    }

    private static function containsPushsale(string $value): bool
    {
        return str_contains(Str::lower($value), 'pushsale');
    }
}
