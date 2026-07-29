<?php

namespace App\Services\Customers;

use App\Models\Order;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Models\User;
use App\Support\VietnamesePhone;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class CareCampaignService
{
    /** @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>, filters: array<string, mixed>} */
    public function paginate(Request $request): array
    {
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', $request->input('keyword', '')));
        $dateFrom = $this->parseDate($request->input('date_from'), false);
        $dateTo = $this->parseDate($request->input('date_to'), true);
        $perPage = min(100, max(10, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $query = CustomerCareCampaign::query()->latest('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($dateFrom && $dateTo) {
            $query->where(function ($scope) use ($dateFrom, $dateTo): void {
                $scope->whereBetween('starts_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->orWhereBetween('ends_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->orWhereBetween('created_at', [$dateFrom, $dateTo]);
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        $rows = collect($paginator->items())->values()->map(function (CustomerCareCampaign $row, int $index) use ($paginator): array {
            return $this->toRow($row, ($paginator->firstItem() ?? 1) + $index - 1);
        })->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'per_page' => $perPage,
                'page' => $page,
            ],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function create(array $payload, ?User $user = null): CustomerCareCampaign
    {
        $condition = $this->normalizeCondition($payload['customer_condition'] ?? $payload['filters'] ?? []);

        if (! empty($payload['customer_ids']) || ! empty($payload['order_ids'])) {
            $ids = collect($payload['customer_ids'] ?? $payload['order_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $condition['order_ids'] = array_values(array_unique(array_merge($condition['order_ids'] ?? [], $ids)));
            $condition['phone_keys'] = array_values(array_unique(array_merge(
                $condition['phone_keys'] ?? [],
                $this->phoneKeysForOrderIds($ids)->all(),
            )));
        }

        return CustomerCareCampaign::query()->create([
            'name' => trim((string) $payload['name']),
            'customer_condition' => $condition,
            'repeat_days' => max(0, (int) ($payload['repeat_days'] ?? 0)),
            'starts_at' => $payload['starts_at'] ?? null,
            'ends_at' => $payload['ends_at'] ?? null,
            'status' => (string) ($payload['status'] ?? 'draft'),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function update(CustomerCareCampaign $campaign, array $payload, ?User $user = null): CustomerCareCampaign
    {
        $condition = array_key_exists('customer_condition', $payload) || array_key_exists('filters', $payload)
            ? $this->normalizeCondition($payload['customer_condition'] ?? $payload['filters'] ?? [])
            : (array) ($campaign->customer_condition ?? []);

        $campaign->forceFill([
            'name' => array_key_exists('name', $payload) ? trim((string) $payload['name']) : $campaign->name,
            'customer_condition' => $condition,
            'repeat_days' => array_key_exists('repeat_days', $payload) ? max(0, (int) $payload['repeat_days']) : $campaign->repeat_days,
            'starts_at' => array_key_exists('starts_at', $payload) ? $payload['starts_at'] : $campaign->starts_at,
            'ends_at' => array_key_exists('ends_at', $payload) ? $payload['ends_at'] : $campaign->ends_at,
            'status' => array_key_exists('status', $payload) ? (string) $payload['status'] : $campaign->status,
            'updated_by_user_id' => $user?->id,
        ])->save();

        return $campaign->refresh();
    }

    /** @param  array<int, int>  $orderIds */
    public function attachOrders(CustomerCareCampaign $campaign, array $orderIds, ?User $user = null): CustomerCareCampaign
    {
        $existing = (array) ($campaign->customer_condition ?? []);
        $ids = collect($existing['order_ids'] ?? [])
            ->merge($orderIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $phones = collect($existing['phone_keys'] ?? [])
            ->merge($this->phoneKeysForOrderIds($ids))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $campaign->forceFill([
            'customer_condition' => array_merge($existing, [
                'source' => $existing['source'] ?? 'manual_customer360_selection',
                'order_ids' => $ids,
                'phone_keys' => $phones,
            ]),
            'updated_by_user_id' => $user?->id,
        ])->save();

        return $campaign->refresh();
    }

    public function destroy(CustomerCareCampaign $campaign): void
    {
        $campaign->delete();
    }

    /** @return Collection<int, string> */
    public function phoneKeysForOrderIds(array $orderIds): Collection
    {
        if ($orderIds === []) {
            return collect();
        }

        return Order::query()
            ->whereIn('id', $orderIds)
            ->get(['id', 'customer_phone'])
            ->map(function (Order $order): ?string {
                $normalized = VietnamesePhone::normalize($order->customer_phone);
                if ($normalized) {
                    return $normalized;
                }
                $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';

                return $digits !== '' ? $digits : null;
            })
            ->filter()
            ->unique()
            ->values();
    }

    /** @return array<int, array{value: string, label: string}> */
    public function options(): array
    {
        return CustomerCareCampaign::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name'])
            ->map(fn (CustomerCareCampaign $campaign): array => [
                'value' => (string) $campaign->id,
                'label' => $campaign->name,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function toRow(CustomerCareCampaign $row, int $index = 0): array
    {
        $condition = (array) ($row->customer_condition ?? []);
        $filters = (array) ($condition['filters'] ?? Arr::except($condition, ['source', 'order_ids', 'phone_keys']));
        $memberCount = count($condition['phone_keys'] ?? []) ?: count($condition['order_ids'] ?? []);

        return [
            'id' => $row->id,
            'index' => $index + 1,
            'name' => $row->name,
            'customer_condition' => $this->conditionLabel($filters, $memberCount),
            'customer_condition_raw' => $condition,
            'repeat_days' => (int) $row->repeat_days,
            'starts_at' => $row->starts_at?->format('d/m/Y'),
            'ends_at' => $row->ends_at?->format('d/m/Y'),
            'starts_at_iso' => $row->starts_at?->toDateString(),
            'ends_at_iso' => $row->ends_at?->toDateString(),
            'status' => $row->status,
            'status_label' => match ($row->status) {
                'active' => 'Đang chạy',
                'paused' => 'Tạm dừng',
                'completed' => 'Hoàn thành',
                default => 'Nháp',
            },
            'updated_at' => $row->updated_at?->format('d/m/Y H:i'),
            'member_count' => $memberCount,
        ];
    }

    /** @param  array<string, mixed>  $raw */
    public function normalizeCondition(mixed $raw): array
    {
        $data = is_array($raw) ? $raw : [];
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : Arr::except($data, ['source', 'order_ids', 'phone_keys', 'filters']);

        return [
            'source' => (string) ($data['source'] ?? 'care_campaign_form'),
            'filters' => Arr::only($filters, [
                'status', 'customer_type', 'segment_id', 'marital_status', 'language',
                'province', 'district', 'ward', 'gender', 'birth_month', 'job',
                'age_from', 'age_to', 'religion', 'income_from', 'income_to',
                'spending_from', 'spending_to', 'usage_effectiveness', 'customer_status',
                'sale_id', 'marketer_id', 'date_from', 'date_to', 'date_type', 'search',
            ]),
            'order_ids' => collect($data['order_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
            'phone_keys' => collect($data['phone_keys'] ?? [])->map(fn ($phone) => (string) $phone)->filter()->unique()->values()->all(),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function conditionLabel(array $filters, int $memberCount): string
    {
        $parts = collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => is_scalar($value) ? "{$key}: {$value}" : null)
            ->filter()
            ->take(4)
            ->values()
            ->all();

        if ($parts === [] && $memberCount > 0) {
            return "{$memberCount} khách đã gắn";
        }

        if ($parts === []) {
            return '—';
        }

        $label = implode(', ', $parts);

        return $memberCount > 0 ? "{$label} · {$memberCount} KH" : $label;
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
