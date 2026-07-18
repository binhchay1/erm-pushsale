<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class FeatureSettingsService
{
    public const SETTING_KEY = 'unit.feature_settings';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definition(): array
    {
        return config('pushsale_feature_settings', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->controls() as $control) {
            $defaults[$control['key']] = $control['default'] ?? $this->emptyValueFor($control);
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $raw = AppSetting::get(self::SETTING_KEY, '{}') ?: '{}';
        $stored = json_decode($raw, true);

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace($this->defaults(), $this->sanitize($stored));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $old = $this->values();
        $values = array_replace($old, $this->sanitize($payload));

        AppSetting::set(
            self::SETTING_KEY,
            json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return [
            'values' => $values,
            'changed' => $this->changedValues($old, $values),
        ];
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->values(), $key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->value($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->value($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function controls(): array
    {
        $controls = [];

        foreach ($this->definition() as $tab) {
            foreach (($tab['rows'] ?? []) as $row) {
                foreach (($row['controls'] ?? []) as $control) {
                    if (! empty($control['key'])) {
                        $controls[] = $control;
                    }
                }
            }
        }

        return $controls;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitize(array $payload): array
    {
        $clean = [];

        foreach ($this->controls() as $control) {
            $key = (string) $control['key'];
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $clean[$key] = $this->sanitizeValue($control, $payload[$key]);
        }

        return $clean;
    }

    private function sanitizeValue(array $control, mixed $value): mixed
    {
        $type = (string) ($control['type'] ?? 'text');

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'select' => $this->sanitizeSelect($control, $value),
            'excel_columns' => $this->sanitizeExcelColumns($control, $value),
            'long_text' => $this->limitString($value, (int) ($control['max_length'] ?? 5000)),
            default => $this->limitString($value, (int) ($control['max_length'] ?? 1000)),
        };
    }

    private function sanitizeSelect(array $control, mixed $value): string
    {
        $allowed = collect($control['options'] ?? [])->pluck('value')->map(fn ($item) => (string) $item)->all();
        $candidate = is_scalar($value) ? (string) $value : '';

        return in_array($candidate, $allowed, true)
            ? $candidate
            : (string) ($control['default'] ?? ($allowed[0] ?? ''));
    }

    private function sanitizeExcelColumns(array $control, mixed $value): array
    {
        $allowed = collect($control['options'] ?? [])->pluck('value')->map(fn ($item) => (string) $item)->all();
        $items = is_array($value) ? $value : explode(';', is_scalar($value) ? (string) $value : '');

        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '' && in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function limitString(mixed $value, int $maxLength): string
    {
        $text = trim(is_scalar($value) ? (string) $value : '');

        return Str::limit($text, max(1, $maxLength), '');
    }

    private function emptyValueFor(array $control): mixed
    {
        return match ((string) ($control['type'] ?? 'text')) {
            'boolean' => false,
            'excel_columns' => [],
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function changedValues(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $changed[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changed;
    }
}
