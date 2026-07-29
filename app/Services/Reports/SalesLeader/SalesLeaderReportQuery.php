<?php

namespace App\Services\Reports\SalesLeader;

use App\Data\ReportFilterData;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SalesLeaderReportQuery
{
    public const STAGES = [
        'call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6',
        'care_1', 'care_2', 'care_3', 'skipped',
    ];

    public const STAGE_LABELS = [
        'call_1' => 'Gọi lần 1',
        'call_2' => 'Gọi lần 2',
        'call_3' => 'Gọi lần 3',
        'call_4' => 'Gọi lần 4',
        'call_5' => 'Gọi lần 5',
        'call_6' => 'Gọi lần 6',
        'care_1' => 'Chăm sóc lần 1',
        'care_2' => 'Chăm sóc lần 2',
        'care_3' => 'Chăm sóc lần 3',
        'skipped' => 'Bỏ qua',
    ];

    public function ordersQuery(Request $request, ?User $actor = null): Builder
    {
        $actor ??= $request->user();
        $query = Order::query();
        if ($actor?->isPlatformAdmin()) {
            $query->withoutTenant();
        }

        $filter = ReportFilterData::fromRequest($request, $actor);
        $query->applyReportFilter($filter);

        $saleTeamId = (int) ($request->input('sale_team_id') ?: $request->input('team_id') ?: 0);
        $saleLeaderId = (int) ($request->input('sale_leader_id') ?: $request->input('team_leader_id') ?: 0);
        $saleId = (int) ($request->input('sale_id') ?: 0);
        $productId = (int) ($request->input('product_id') ?: 0);
        $parentProductId = (int) ($request->input('parent_product_id') ?: 0);

        $query
            ->when($saleTeamId > 0, fn (Builder $q) => $q->where('team_id', $saleTeamId))
            ->when($saleLeaderId > 0, fn (Builder $q) => $q->whereHas('team', fn (Builder $team) => $team->where('leader_user_id', $saleLeaderId)))
            ->when($saleId > 0, fn (Builder $q) => $q->where('sale_user_id', $saleId))
            ->when($productId > 0, fn (Builder $q) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)))
            ->when($parentProductId > 0, fn (Builder $q) => $q->whereHas('items.product', fn (Builder $product) => $product->where('parent_id', $parentProductId)->orWhere('product_group_id', $parentProductId)));

        // Stage filter chỉ dùng để ẩn/hiện cột trên UI (visibleStages), không cắt dataset SQL.

        return $query;
    }

    /** @return Collection<int, Order> */
    public function loadOrders(Request $request, ?User $actor = null): Collection
    {
        return $this->ordersQuery($request, $actor)
            ->with([
                'team:id,name,leader_user_id',
                'saleUser:id,name,email,team_id,permissions',
                'saleUser.operationalProfile:id,user_id,receive_data',
                'items:id,order_id,product_id,quantity,item_type,unit_price,discount_amount',
            ])
            ->latest('data_arrived_at')
            ->limit(20000)
            ->get();
    }

    /** @return list<string> */
    public function requestedStages(Request $request): array
    {
        $raw = $request->input('operation_stage', $request->input('operation_stages', ''));
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $values = array_filter(array_map('trim', explode(',', (string) $raw)));
        }

        $normalized = [];
        foreach ($values as $value) {
            $stage = $this->normalizeStage((string) $value);
            if ($stage) {
                $normalized[] = $stage;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @return list<string> */
    public function visibleStages(Request $request): array
    {
        $requested = $this->requestedStages($request);

        return $requested !== [] ? $requested : self::STAGES;
    }

    public function normalizeStage(string $stage): ?string
    {
        return match (Str::lower(trim($stage))) {
            '102133', 'call_1', 'call1', 'gọi lần 1' => 'call_1',
            '102134', 'call_2', 'call2', 'gọi lần 2' => 'call_2',
            '102135', 'call_3', 'call3', 'gọi lần 3' => 'call_3',
            '102136', 'call_4', 'call4', 'gọi lần 4' => 'call_4',
            '102137', 'call_5', 'call5', 'gọi lần 5' => 'call_5',
            '102138', 'call_6', 'call6', 'gọi lần 6' => 'call_6',
            '102139', 'care_1', 'care1', 'chăm sóc lần 1' => 'care_1',
            '102140', 'care_2', 'care2', 'chăm sóc lần 2' => 'care_2',
            '102141', 'care_3', 'care3', 'chăm sóc lần 3' => 'care_3',
            '102142', 'skipped', 'ignore', 'bỏ qua' => 'skipped',
            default => null,
        };
    }

    /** @return list<string> */
    public function stageAliases(string $stage): array
    {
        return match ($stage) {
            'call_1' => ['call_1', 'call1', 'Gọi lần 1', 'gọi lần 1'],
            'call_2' => ['call_2', 'call2', 'Gọi lần 2', 'gọi lần 2'],
            'call_3' => ['call_3', 'call3', 'Gọi lần 3', 'gọi lần 3'],
            'call_4' => ['call_4', 'call4', 'Gọi lần 4', 'gọi lần 4'],
            'call_5' => ['call_5', 'call5', 'Gọi lần 5', 'gọi lần 5'],
            'call_6' => ['call_6', 'call6', 'Gọi lần 6', 'gọi lần 6'],
            'care_1' => ['care_1', 'care1', 'Chăm sóc lần 1', 'chăm sóc lần 1'],
            'care_2' => ['care_2', 'care2', 'Chăm sóc lần 2', 'chăm sóc lần 2'],
            'care_3' => ['care_3', 'care3', 'Chăm sóc lần 3', 'chăm sóc lần 3'],
            'skipped' => ['skipped', 'ignore', 'Bỏ qua', 'bỏ qua'],
            default => [$stage],
        };
    }

    public function isUntouched(Order $order): bool
    {
        if (blank($order->operation_result)) {
            return true;
        }

        $stage = Str::lower(trim((string) $order->operation_stage));

        return $stage === '' || $stage === 'no_operation';
    }

    public function isStageUntouched(Order $order): bool
    {
        return blank($order->operation_result);
    }

    public function saleAccount(?User $user): string
    {
        if (! $user?->email) {
            return '';
        }

        return (string) Str::before($user->email, '@');
    }

    public function receivesData(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $profile = $user->relationLoaded('operationalProfile')
            ? $user->operationalProfile
            : $user->operationalProfile()->first();

        if ($profile && array_key_exists('receive_data', $profile->getAttributes())) {
            return (bool) $profile->receive_data;
        }

        return (bool) data_get($user->permissions, 'receive_data', true);
    }

    public function orderRevenue(Order $order, Request $request): int
    {
        $mode = strtolower(trim((string) $request->input('discount_mode', 'after_discount')));
        if (in_array($mode, ['before_discount', '0', 'before'], true)) {
            return (int) max(0, (int) $order->subtotal);
        }

        return (int) $order->effectiveRevenue();
    }

    /**
     * Số ngày làm việc trong khoảng (mặc định bỏ T7/CN trừ khi request bật).
     * Dùng cho 4.6.5 khi quy đổi chỉ tiêu theo ngày.
     */
    public function workingDaysInRange(Request $request): int
    {
        $from = $request->input('date_from');
        $to = $request->input('date_to') ?: $from;
        if (! $from) {
            return 1;
        }

        try {
            $start = \Illuminate\Support\Carbon::parse($from)->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($to)->startOfDay();
        } catch (\Throwable) {
            return 1;
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $includeSaturday = $request->boolean('include_saturday');
        $includeSunday = $request->boolean('include_sunday');
        $days = 0;
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dow = (int) $cursor->dayOfWeekIso; // 1=Mon ... 7=Sun
            if ($dow === 6 && ! $includeSaturday) {
                continue;
            }
            if ($dow === 7 && ! $includeSunday) {
                continue;
            }
            $days++;
        }

        return max(1, $days);
    }

    public function paginateRows(Collection $rows, Request $request): array
    {
        $total = $rows->count();
        $export = strtolower((string) $request->query('export', ''));
        if ($request->boolean('export') || in_array($export, ['1', 'xls', 'excel', 'csv'], true)) {
            return [
                'data' => $rows->values()->all(),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, $total),
                    'total' => $total,
                    'from' => $total === 0 ? 0 : 1,
                    'to' => $total,
                ],
            ];
        }

        $perPage = max(1, min(1000, (int) ($request->input('per_page') ?: 50)));
        $page = max(1, (int) ($request->input('page') ?: 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? 0 : min($total, $page * $perPage);

        return [
            'data' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }
}
