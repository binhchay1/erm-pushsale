<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use App\Services\Marketing\MarketingBudgetService;
use App\Support\LeadContactMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * CEO V2 dùng cùng nguồn sự thật với dashboard tài chính và các báo cáo 4.5.x.
 * Contact chỉ đếm packet lead gốc; đơn/dòng hàng upsell vẫn góp sản lượng và
 * doanh thu nhưng không làm tăng mẫu số tỷ lệ chốt.
 */
class CeoReportService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly MarketingBudgetService $budgets,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter, ?User $viewer = null): array
    {
        $viewer ??= User::query()->where('role', UserRole::Admin)->firstOrFail();
        $allOrders = $this->queries->orders($viewer, $filter)
            ->with([
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
                'saleUser:id,name,email,role',
                'marketerUser:id,name,email,role',
            ])
            ->get();

        $statusSummary = [];
        foreach (DeliveryStatus::ceoSummaryMap() as $key => $status) {
            $statusSummary[$key] = $allOrders->where('delivery_status', $status->value)->count();
        }

        $from = ($filter->dateFrom ?? Carbon::now()->startOfMonth())->copy()->startOfDay();
        $to = ($filter->dateTo ?? Carbon::now()->endOfMonth())->copy()->endOfDay();
        $plans = $this->plansForRange($from, $to);

        $saleUsers = User::query()
            ->where('role', UserRole::Sales)
            ->whereIn('id', $allOrders->pluck('sale_user_id')->filter()->unique())
            ->orderBy('name')
            ->get();

        $saleRows = $saleUsers->map(function (User $user, int $index) use ($allOrders, $plans): array {
            $mine = $allOrders->where('sale_user_id', $user->id)->values();
            $contactOrders = $mine->whereIn('id', LeadContactMetrics::contactOrderIds($mine));
            $newContactOrders = $contactOrders->where('is_returning_customer', false);
            $oldContactOrders = $contactOrders->where('is_returning_customer', true);
            $newOrders = $mine->where('is_returning_customer', false);
            $oldOrders = $mine->where('is_returning_customer', true);
            $closed = $mine->filter(fn (Order $order): bool => $this->isClosed($order));
            $closedItems = $closed->flatMap(fn (Order $order) => $order->items);
            $upsellItems = $closedItems->where('item_type', 'upsell');

            $newContact = $newContactOrders->count();
            $oldContact = $oldContactOrders->count();
            $newClosed = $newContactOrders->filter(fn (Order $order): bool => $this->isClosed($order))->count();
            $oldClosed = $oldContactOrders->filter(fn (Order $order): bool => $this->isClosed($order))->count();
            $totalRevenue = (int) $closed->sum(fn (Order $order): int => $order->netRevenue());
            $salesKpi = (int) collect($plans->get($user->id, collect()))->sum('revenue_target');
            $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());

            return [
                'stt' => $index + 1,
                'saleStaffId' => (string) $user->id,
                'saleStaffName' => $user->name,
                'saleUsername' => strstr($user->email, '@', true) ?: $user->email,
                'newContact' => $newContact,
                'newClosed' => $newClosed,
                'newCloseRate' => $this->pct($newClosed, $newContact),
                'newProductQty' => (int) $newOrders->filter(fn (Order $o): bool => $this->isClosed($o))->sum(fn (Order $order): int => (int) $order->items->sum('quantity')),
                'newEstRevenue' => (int) $newOrders->filter(fn (Order $o): bool => $this->isClosed($o))->sum(fn (Order $order): int => $order->netRevenue()),
                'oldContact' => $oldContact,
                'oldClosed' => $oldClosed,
                'oldCloseRate' => $this->pct($oldClosed, $oldContact),
                'oldProductQty' => (int) $oldOrders->filter(fn (Order $o): bool => $this->isClosed($o))->sum(fn (Order $order): int => (int) $order->items->sum('quantity')),
                'oldEstRevenue' => (int) $oldOrders->filter(fn (Order $o): bool => $this->isClosed($o))->sum(fn (Order $order): int => $order->netRevenue()),
                'totalEstRevenue' => $totalRevenue,
                'upsellQty' => (int) $upsellItems->sum('quantity'),
                'upsellRevenue' => $upsellRevenue,
                'upsellRevenueShare' => $this->pct($upsellRevenue, $totalRevenue),
                'codFee' => (int) $closed->sum('cod_fee'),
                'codSupport' => (int) $closed->sum('cod_support'),
                'bankTransfer' => (int) $closed->sum('discount'),
                'deposit' => (int) $closed->sum('deposit'),
                'salesKpi' => $salesKpi,
                'achievementRate' => $this->pct($totalRevenue, $salesKpi),
            ];
        })->sortByDesc('totalEstRevenue')->values()->map(
            fn (array $row, int $index): array => array_merge($row, ['stt' => $index + 1])
        )->all();

        $leadCountsBySource = LeadContactMetrics::effectiveCountsBySource($filter, $allOrders);
        $sources = MarketingSource::query()
            ->with('marketer:id,name,email,role')
            ->whereIn('id', $allOrders->pluck('marketing_source_id')->filter()->unique())
            ->get();

        $sourceGroups = $sources->groupBy(fn (MarketingSource $source): string => $source->marketer_user_id
            ? 'marketer:'.$source->marketer_user_id
            : 'source:'.$source->id);

        $marketingRows = $sourceGroups->map(function (Collection $sourceGroup) use ($allOrders, $leadCountsBySource, $plans, $from, $to): array {
            /** @var MarketingSource $first */
            $first = $sourceGroup->first();
            $marketer = $first->marketer;
            $sourceIds = $sourceGroup->pluck('id')->map('intval')->unique()->values();
            $orders = $allOrders->whereIn('marketing_source_id', $sourceIds)->values();
            $contactOrderIds = LeadContactMetrics::contactOrderIds($orders);
            $closedContacts = $orders->whereIn('id', $contactOrderIds)
                ->filter(fn (Order $order): bool => $this->isClosed($order));
            $closed = $orders->filter(fn (Order $order): bool => $this->isClosed($order));
            $newOrders = $closed->where('is_returning_customer', false);
            $oldOrders = $closed->where('is_returning_customer', true);
            $items = $closed->flatMap(fn (Order $order) => $order->items);
            $upsellItems = $items->where('item_type', 'upsell');
            $budgetResult = $this->budgets->effectiveForSourceIds($sourceIds, $from, $to);
            $budget = (int) $budgetResult['amount'];
            $contacts = (int) $sourceIds->sum(fn (int $sourceId): int => (int) $leadCountsBySource->get($sourceId, 0));
            $closedCount = $closedContacts->count();
            $newRevenue = (int) $newOrders->sum(fn (Order $order): int => $order->netRevenue());
            $oldRevenue = (int) $oldOrders->sum(fn (Order $order): int => $order->netRevenue());
            $totalRevenue = (int) $closed->sum(fn (Order $order): int => $order->netRevenue());
            $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());
            $marketingKpi = $marketer
                ? (int) collect($plans->get($marketer->id, collect()))->sum('revenue_target')
                : 0;

            return [
                'marketerId' => (string) ($marketer?->id ?? $first->id),
                'marketerName' => $marketer?->name ?? $first->name,
                'marketerUsername' => $marketer ? (strstr($marketer->email, '@', true) ?: $marketer->email) : null,
                'sourceCount' => $sourceIds->count(),
                'budget' => $budget,
                'budgetActual' => (int) $budgetResult['actual'],
                'budgetPlanned' => (int) $budgetResult['planned'],
                'budgetBasis' => $budgetResult['basis'],
                'contacts' => $contacts,
                'contactPrice' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
                'closed' => $closedCount,
                'closeRate' => $this->pct($closedCount, $contacts),
                'newEstRevenue' => $newRevenue,
                'budgetRevenueRatioNew' => $this->pct($budget, $newRevenue),
                'budgetRevenueRatioNewAfterDiscount' => $this->pct($budget, $newRevenue),
                'oldEstRevenue' => $oldRevenue,
                'totalEstRevenue' => $totalRevenue,
                'upsellQty' => (int) $upsellItems->sum('quantity'),
                'upsellRevenue' => $upsellRevenue,
                'upsellRevenueShare' => $this->pct($upsellRevenue, $totalRevenue),
                'budgetRevenueRatioTotal' => $this->pct($budget, $totalRevenue),
                'codFee' => (int) $closed->sum('cod_fee'),
                'codSupport' => (int) $closed->sum('cod_support'),
                'bankTransfer' => (int) $closed->sum('discount'),
                'deposit' => (int) $closed->sum('deposit'),
                'marketingKpi' => $marketingKpi,
                'achievementRate' => $this->pct($totalRevenue, $marketingKpi),
            ];
        })->sortByDesc('totalEstRevenue')->values()->map(
            fn (array $row, int $index): array => array_merge($row, ['stt' => $index + 1])
        )->all();

        return [
            'statusSummary' => $statusSummary,
            'saleRows' => $saleRows,
            'marketingRows' => $marketingRows,
            'businessRules' => [
                'currency' => 'VND',
                'contactRule' => 'counts_as_lead=true',
                'upsellRule' => 'Upsell adds product quantity and revenue, never a second contact.',
                'budgetRule' => 'Actual daily spend is preferred; planned landing budget fills missing source-days.',
            ],
        ];
    }

    /** @return Collection<int, Collection<int, MonthlyKpiPlan>> */
    private function plansForRange(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months->push([$cursor->year, $cursor->month]);
            $cursor->addMonth();
        }

        return MonthlyKpiPlan::query()
            ->where(function ($query) use ($months): void {
                foreach ($months as [$year, $month]) {
                    $query->orWhere(fn ($monthQuery) => $monthQuery->where('year', $year)->where('month', $month));
                }
            })
            ->get()
            ->groupBy('user_id');
    }

    private function isClosed(Order $order): bool
    {
        return $order->closed_at !== null
            || (string) $order->closing_status === ClosingStatus::Closed->value;
    }

    private function pct(int|float $numerator, int|float $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : 0.0;
    }
}
