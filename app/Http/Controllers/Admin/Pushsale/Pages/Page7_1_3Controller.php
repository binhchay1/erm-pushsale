<?php

namespace App\Http\Controllers\Admin\Pushsale\Pages;

use App\Models\Pushsale\KpiCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class Page7_1_3Controller extends BasePushsalePageController
{
    protected string $pageCode = '7.1.3';

    public function initializeDefaults(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $position = $this->normalizePosition((string) $request->input('position_key', $request->query('position_key', 'marketing')));
        $items = $this->defaultItems($position);
        $created = 0;

        DB::transaction(function () use ($items, $position, $request, &$created): void {
            foreach ($items as $sort => $item) {
                $model = KpiCatalogItem::query()->firstOrCreate(
                    ['position_key' => $position, 'kpi_name' => $item['kpi_name']],
                    array_merge($item, [
                        'sort_order' => $sort + 1,
                        'is_active' => true,
                        'created_by_user_id' => $request->user()?->id,
                        'updated_by_user_id' => $request->user()?->id,
                    ])
                );

                if (! $model->wasRecentlyCreated) {
                    $model->fill(array_merge($item, [
                        'sort_order' => $sort + 1,
                        'is_active' => true,
                        'updated_by_user_id' => $request->user()?->id,
                    ]))->save();
                } else {
                    $created++;
                }
            }
        });

        return $this->ok($request, "Đã khởi tạo/cập nhật danh mục KPI {$this->positionLabel($position)}. Thêm mới {$created} dòng.");
    }

    public function bulkSave(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'records' => ['required', 'array'],
            'records.*.id' => ['nullable', 'integer', 'exists:kpi_catalog_items,id'],
            'records.*.position_key' => ['required', 'in:marketing,sales,sale'],
            'records.*.kpi_name' => ['required', 'string', 'max:255'],
            'records.*.daily_budget' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_clicks' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_contacts' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_revenue' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_new_contacts' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_new_closed' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_old_contacts' => ['nullable', 'integer', 'min:0'],
            'records.*.daily_old_closed' => ['nullable', 'integer', 'min:0'],
            'records.*.is_active' => ['boolean'],
            'records.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $saved = 0;
        DB::transaction(function () use ($validated, $request, &$saved): void {
            foreach ($validated['records'] as $record) {
                $position = $this->normalizePosition((string) $record['position_key']);
                $payload = [
                    'position_key' => $position,
                    'kpi_name' => trim((string) $record['kpi_name']),
                    'daily_budget' => (int) ($record['daily_budget'] ?? 0),
                    'daily_clicks' => (int) ($record['daily_clicks'] ?? 0),
                    'daily_contacts' => (int) ($record['daily_contacts'] ?? 0),
                    'daily_revenue' => (int) ($record['daily_revenue'] ?? 0),
                    'daily_new_contacts' => (int) ($record['daily_new_contacts'] ?? 0),
                    'daily_new_closed' => (int) ($record['daily_new_closed'] ?? 0),
                    'daily_old_contacts' => (int) ($record['daily_old_contacts'] ?? 0),
                    'daily_old_closed' => (int) ($record['daily_old_closed'] ?? 0),
                    'is_active' => (bool) ($record['is_active'] ?? true),
                    'sort_order' => (int) ($record['sort_order'] ?? 0),
                    'updated_by_user_id' => $request->user()?->id,
                ];

                if (! empty($record['id'])) {
                    KpiCatalogItem::query()->findOrFail((int) $record['id'])->fill($payload)->save();
                } else {
                    KpiCatalogItem::query()->create(array_merge($payload, ['created_by_user_id' => $request->user()?->id]));
                }
                $saved++;
            }
        });

        return $this->ok($request, "Đã lưu {$saved} dòng danh mục KPI.");
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultItems(string $position): array
    {
        if ($position === 'sales') {
            return [
                ['kpi_name' => 'KPI Sale chuẩn', 'daily_new_contacts' => 12, 'daily_new_closed' => 5, 'daily_old_contacts' => 6, 'daily_old_closed' => 3, 'daily_revenue' => 12000000],
                ['kpi_name' => 'KPI Sale tăng tốc', 'daily_new_contacts' => 16, 'daily_new_closed' => 7, 'daily_old_contacts' => 8, 'daily_old_closed' => 4, 'daily_revenue' => 18000000],
                ['kpi_name' => 'KPI Sale chăm sóc lại', 'daily_new_contacts' => 8, 'daily_new_closed' => 3, 'daily_old_contacts' => 14, 'daily_old_closed' => 6, 'daily_revenue' => 15000000],
            ];
        }

        return [
            ['kpi_name' => 'KPI Marketing chuẩn', 'daily_budget' => 2200000, 'daily_clicks' => 700, 'daily_contacts' => 34, 'daily_revenue' => 16000000],
            ['kpi_name' => 'KPI Marketing tăng trưởng', 'daily_budget' => 3200000, 'daily_clicks' => 950, 'daily_contacts' => 48, 'daily_revenue' => 24000000],
            ['kpi_name' => 'KPI Marketing tối ưu ngân sách', 'daily_budget' => 1600000, 'daily_clicks' => 520, 'daily_contacts' => 26, 'daily_revenue' => 13000000],
        ];
    }

    private function normalizePosition(string $value): string
    {
        return $value === 'sale' ? 'sales' : (in_array($value, ['marketing', 'sales'], true) ? $value : 'marketing');
    }

    private function positionLabel(string $position): string
    {
        return $position === 'sales' ? 'Sale' : 'Marketing';
    }

    private function ok(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
