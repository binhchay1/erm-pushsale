<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

readonly class ReportDateRange
{
    public const PRESET_TODAY = 'today';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_LAST_30_DAYS = 'last_30_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    /** @var list<string> */
    public const PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_LAST_7_DAYS,
        self::PRESET_LAST_30_DAYS,
        self::PRESET_THIS_MONTH,
        self::PRESET_CUSTOM,
    ];

    public function __construct(
        public string $preset,
        public Carbon $from,
        public Carbon $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $requestedPreset = (string) $request->input('preset', self::PRESET_LAST_7_DAYS);
        $preset = in_array($requestedPreset, self::PRESETS, true) ? $requestedPreset : self::PRESET_LAST_7_DAYS;

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $preset = self::PRESET_CUSTOM;
        }

        return self::forPreset(
            $preset,
            $request->input('date_from'),
            $request->input('date_to'),
        );
    }

    public static function forPreset(string $preset, mixed $dateFrom = null, mixed $dateTo = null): self
    {
        $today = now();

        return match ($preset) {
            self::PRESET_TODAY => new self(
                self::PRESET_TODAY,
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ),
            self::PRESET_LAST_7_DAYS => new self(
                self::PRESET_LAST_7_DAYS,
                $today->copy()->subDays(6)->startOfDay(),
                $today->copy()->endOfDay(),
            ),
            self::PRESET_LAST_30_DAYS => new self(
                self::PRESET_LAST_30_DAYS,
                $today->copy()->subDays(29)->startOfDay(),
                $today->copy()->endOfDay(),
            ),
            self::PRESET_THIS_MONTH => new self(
                self::PRESET_THIS_MONTH,
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfDay(),
            ),
            self::PRESET_CUSTOM => new self(
                self::PRESET_CUSTOM,
                self::parseCustomDate($dateFrom, $today->copy()->subDays(6))->startOfDay(),
                self::parseCustomDate($dateTo, $today)->endOfDay(),
            ),
            default => throw new InvalidArgumentException("Unsupported report date preset [{$preset}]."),
        };
    }

    private static function parseCustomDate(mixed $value, Carbon $fallback): Carbon
    {
        if (! $value || is_bool($value)) {
            return $fallback;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || in_array($normalized, ['0', '1', 'true', 'false'], true)) {
            return $fallback;
        }

        return Carbon::parse($normalized);
    }
}
