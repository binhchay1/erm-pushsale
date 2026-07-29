<?php

namespace App\Services\Customers;

use App\Models\AppSetting;
use App\Models\CustomerSegmentAssignment;
use App\Models\Order;
use App\Support\VietnamesePhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CustomerSegmentService
{
    public const SETTING_KEY = 'customer360.segments';

    /** @return array<int, array{id: int, name: string, color: string, min_successful_order_value: int}> */
    public function definitions(): array
    {
        $json = AppSetting::get(self::SETTING_KEY);
        $segments = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($segments) || $segments === []) {
            return $this->defaults();
        }

        return collect($segments)
            ->values()
            ->map(function (mixed $segment, int $index): array {
                $row = is_array($segment) ? $segment : [];

                return [
                    'id' => (int) ($row['id'] ?? ($index + 1)),
                    'name' => trim((string) ($row['name'] ?? ('Phân loại '.($index + 1)))),
                    'color' => (string) ($row['color'] ?? '#337ab7'),
                    'min_successful_order_value' => max(0, (int) ($row['min_successful_order_value'] ?? $row['min_value'] ?? 0)),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, color?: string|null, min_successful_order_value?: int|null}>  $segments
     * @return array<int, array{id: int, name: string, color: string, min_successful_order_value: int}>
     */
    public function saveDefinitions(array $segments): array
    {
        $normalized = collect($segments)
            ->values()
            ->map(function (array $segment, int $index): array {
                return [
                    'id' => $index + 1,
                    'name' => trim((string) ($segment['name'] ?? '')),
                    'color' => (string) (($segment['color'] ?? '') ?: '#337ab7'),
                    'min_successful_order_value' => max(0, (int) ($segment['min_successful_order_value'] ?? 0)),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();

        AppSetting::set(self::SETTING_KEY, json_encode($normalized, JSON_UNESCAPED_UNICODE));

        return $normalized;
    }

    /** @return array{assigned: int, phones: int, segments: int} */
    public function recalculate(?int $companyId = null): array
    {
        $definitions = collect($this->definitions())
            ->sortByDesc('min_successful_order_value')
            ->values();

        if ($definitions->isEmpty()) {
            return ['assigned' => 0, 'phones' => 0, 'segments' => 0];
        }

        $profile = app(CustomerProfileService::class);
        $phoneExpression = $profile->normalizedPhoneExpression('orders.customer_phone');

        $totals = Order::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->where(function ($q): void {
                $q->whereIn('delivery_status', ['delivered', 'paid', 'partial_delivery', 'delivery_complete'])
                    ->orWhereIn('closing_status', ['closed', 'success']);
            })
            ->selectRaw("{$phoneExpression} AS phone_key")
            ->selectRaw('COALESCE(SUM(orders.total), 0) AS successful_order_value')
            ->groupByRaw($phoneExpression)
            ->get();

        $now = now();
        $rows = [];

        foreach ($totals as $row) {
            $phoneKey = VietnamesePhone::normalize((string) $row->phone_key)
                ?: preg_replace('/\D+/', '', (string) $row->phone_key)
                ?: (string) $row->phone_key;
            $value = (int) $row->successful_order_value;
            $match = $definitions->first(fn (array $segment): bool => $value >= (int) $segment['min_successful_order_value']);

            if (! $match || $phoneKey === '') {
                continue;
            }

            $rows[] = [
                'company_id' => $companyId ?? (int) (auth()->user()?->company_id ?? 0),
                'phone_key' => mb_substr($phoneKey, 0, 32),
                'segment_id' => (int) $match['id'],
                'segment_name' => (string) $match['name'],
                'successful_order_value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Tenant scope: wipe + insert for current company when known.
        $targetCompanyId = $companyId ?? (int) (auth()->user()?->company_id ?? 0);

        DB::transaction(function () use ($rows, $targetCompanyId): void {
            $query = CustomerSegmentAssignment::query();
            if ($targetCompanyId > 0) {
                $query->where('company_id', $targetCompanyId);
            }
            $query->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                if ($targetCompanyId > 0) {
                    $chunk = array_map(function (array $row) use ($targetCompanyId): array {
                        $row['company_id'] = $targetCompanyId;

                        return $row;
                    }, $chunk);
                }
                CustomerSegmentAssignment::query()->insert($chunk);
            }
        });

        return [
            'assigned' => count($rows),
            'phones' => $totals->count(),
            'segments' => $definitions->count(),
        ];
    }

    /** @return Collection<int, string> */
    public function phoneKeysForSegment(int $segmentId): Collection
    {
        return CustomerSegmentAssignment::query()
            ->where('segment_id', $segmentId)
            ->pluck('phone_key');
    }

    /** @return array<int, array{id: int, name: string, color: string, min_successful_order_value: int}> */
    private function defaults(): array
    {
        return [
            ['id' => 1, 'name' => 'Khách mới', 'color' => '#337ab7', 'min_successful_order_value' => 0],
            ['id' => 2, 'name' => 'Khách cũ', 'color' => '#00a65a', 'min_successful_order_value' => 500000],
            ['id' => 3, 'name' => 'Khách VIP', 'color' => '#f39c12', 'min_successful_order_value' => 5000000],
        ];
    }
}
