<?php

namespace App\Models\Pushsale;

use App\Enums\OperationResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationResultSetting extends BusinessRecord
{
    protected $table = 'operation_result_settings';

    protected $fillable = [
        'value',
        'label',
        'legacy_id',
        'sort_order',
        'closes_order',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'sort_order' => 'integer',
            'closes_order' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return list<array{value:string,label:string,legacy_id:int,sort_order:int,closes_order:bool,is_active:bool}> */
    public static function defaultRows(): array
    {
        return collect(OperationResult::selectableOptions())
            ->values()
            ->map(fn (array $item, int $index): array => [
                'value' => (string) $item['value'],
                'label' => (string) $item['label'],
                'legacy_id' => 109117 + $index,
                'sort_order' => $index + 1,
                'closes_order' => (string) $item['value'] === OperationResult::ClosedSuccess->value,
                'is_active' => true,
            ])
            ->all();
    }

    public static function ensureDefaults(): void
    {
        if (! Schema::hasTable('operation_result_settings')) {
            return;
        }

        foreach (self::defaultRows() as $row) {
            /** @var self $setting */
            $setting = self::query()->firstOrNew(['value' => $row['value']]);
            if (! $setting->exists) {
                $setting->fill($row)->save();
            }
        }
    }

    /** @return list<array{value:string,label:string,legacy_id:int,sort_order:int,closes_order:bool,is_active:bool}> */
    public static function optionRows(): array
    {
        try {
            if (! Schema::hasTable('operation_result_settings')) {
                return self::defaultRows();
            }

            self::ensureDefaults();

            $rows = self::query()
                ->orderBy('sort_order')
                ->orderBy('legacy_id')
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return self::defaultRows();
            }

            return $rows->map(fn (self $row): array => [
                'value' => $row->value,
                'label' => $row->label,
                'legacy_id' => (int) ($row->legacy_id ?: 0),
                'sort_order' => (int) $row->sort_order,
                'closes_order' => (bool) $row->closes_order,
                'is_active' => (bool) $row->is_active,
            ])->values()->all();
        } catch (Throwable) {
            return self::defaultRows();
        }
    }

    public static function closesOrder(string $value): bool
    {
        try {
            if (! Schema::hasTable('operation_result_settings')) {
                return $value === OperationResult::ClosedSuccess->value;
            }

            $setting = self::query()->where('value', $value)->first();
            if ($setting) {
                return (bool) $setting->is_active && (bool) $setting->closes_order;
            }
        } catch (Throwable) {
            // Fallback to the hard-coded safe default below.
        }

        return $value === OperationResult::ClosedSuccess->value;
    }
}
