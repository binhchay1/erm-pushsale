<?php

namespace App\Services\Reports\SalesLeader;

use App\Models\Order;
use App\Models\Pushsale\UserOperationalProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SalesDataReportService
{
    public function __construct(
        private readonly SalesLeaderReportQuery $query,
        private readonly SalesLeaderSaleAggregator $aggregator,
    ) {}

    public function build(Request $request): array
    {
        $orders = $this->query->loadOrders($request);
        $grouped = $this->aggregator->filterGrouped($this->aggregator->groupBySale($orders, $request), $request);

        $yesterday = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
        $thisMonth = [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()];
        $lastMonth = [now()->copy()->subMonthNoOverflow()->startOfMonth(), now()->copy()->subMonthNoOverflow()->endOfMonth()];

        $rows = $grouped->map(function (array $row, int $index) use ($yesterday, $thisMonth, $lastMonth): array {
            /** @var Collection<int, Order> $saleOrders */
            $saleOrders = $row['orders'];
            $unique = max(0, $row['contacts'] - $row['duplicate_contacts']);
            $y = $this->periodMetrics($saleOrders, $yesterday[0], $yesterday[1]);
            $lm = $this->periodMetrics($saleOrders, $lastMonth[0], $lastMonth[1]);
            $tm = $this->periodMetrics($saleOrders, $thisMonth[0], $thisMonth[1]);

            return [
                'index' => $index + 1,
                'sale_id' => $row['id'],
                'sale' => $row['name'],
                'sale_account' => $row['account'],
                'received' => $row['contacts'],
                'duplicate' => $row['duplicate_contacts'],
                'unique' => $unique,
                'yesterday_rate' => $y['rate'],
                'yesterday_revenue' => $y['revenue'],
                'last_month_rate' => $lm['rate'],
                'last_month_revenue' => $lm['revenue'],
                'this_month_rate' => $tm['rate'],
                'this_month_revenue' => $tm['revenue'],
                'receive_data' => $row['receive_data'],
            ];
        })->values();

        $unassigned = Order::query()
            ->when($request->user()?->isPlatformAdmin(), fn ($q) => $q->withoutTenant())
            ->whereNull('sale_user_id')
            ->count();

        $totals = $this->totals($rows);
        $page = $this->query->paginateRows($rows, $request);

        return [
            'data' => $page['data'],
            'meta' => $page['meta'],
            'summary' => [
                'totals' => $totals,
                'unassigned_contacts' => $unassigned,
            ],
        ];
    }

    /** @param list<int> $saleIds */
    public function updateReceiveData(User $actor, array $saleIds, bool $receiveData): int
    {
        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds))));
        if ($saleIds === []) {
            throw ValidationException::withMessages(['sale_ids' => 'Chọn ít nhất một sale.']);
        }

        $updated = 0;
        DB::transaction(function () use ($saleIds, $receiveData, &$updated): void {
            foreach ($saleIds as $saleId) {
                $profile = UserOperationalProfile::query()->firstOrNew(['user_id' => $saleId]);
                if (! $profile->exists) {
                    $profile->company_id = User::query()->whereKey($saleId)->value('company_id');
                }
                $profile->receive_data = $receiveData;
                $profile->save();
                $updated++;
            }
        });

        return $updated;
    }

    /** @param Collection<int, Order> $orders */
    private function periodMetrics(Collection $orders, Carbon $from, Carbon $to): array
    {
        $period = $orders->filter(function (Order $order) use ($from, $to): bool {
            $basis = $order->assigned_at ?? $order->data_arrived_at;
            return $basis && $basis->between($from, $to);
        });
        $closed = $period->whereNotNull('closed_at');

        return [
            'contacts' => $period->count(),
            'closed' => $closed->count(),
            'rate' => round(($closed->count() / max(1, $period->count())) * 100, 2),
            'revenue' => (int) $closed->sum(fn (Order $order) => $order->effectiveRevenue()),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function totals(Collection $rows): array
    {
        $totals = [
            'sale' => 'Tổng',
            'received' => 0,
            'duplicate' => 0,
            'unique' => 0,
            'yesterday_revenue' => 0,
            'last_month_revenue' => 0,
            'this_month_revenue' => 0,
            'yesterday_closed' => 0,
            'yesterday_contacts' => 0,
            'last_month_closed' => 0,
            'last_month_contacts' => 0,
            'this_month_closed' => 0,
            'this_month_contacts' => 0,
        ];
        foreach ($rows as $row) {
            $totals['received'] += (int) $row['received'];
            $totals['duplicate'] += (int) $row['duplicate'];
            $totals['unique'] += (int) $row['unique'];
            $totals['yesterday_revenue'] += (int) $row['yesterday_revenue'];
            $totals['last_month_revenue'] += (int) $row['last_month_revenue'];
            $totals['this_month_revenue'] += (int) $row['this_month_revenue'];
        }
        $totals['yesterday_rate'] = null;
        $totals['last_month_rate'] = null;
        $totals['this_month_rate'] = null;

        return $totals;
    }
}
