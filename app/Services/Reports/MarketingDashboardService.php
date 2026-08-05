<?php

namespace App\Services\Reports;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Enums\ClosingStatus;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Support\LeadContactMetrics;
use App\Support\MarketingPacketMetrics;
use Illuminate\Support\Collection;

class MarketingDashboardService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly MarketingTeamTreeService $teamTree,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        $orderCollection = $this->orders->allFiltered($filter);
        $leadCountsBySource = MarketingPacketMetrics::effectiveCountsBySource($filter, $orderCollection);
        $primaryCountsBySource = MarketingPacketMetrics::effectivePrimaryCountsBySource($filter, $orderCollection);
        $upsaleCountsBySource = MarketingPacketMetrics::effectiveUpsaleCountsBySource($filter, $orderCollection);

        $sources = MarketingSource::query()
            ->visibleInReports()
            ->with(['product:id,name', 'children.product:id,name'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $rows = [];
        $stt = 0;

        foreach ($sources as $source) {
            $stt++;
            $family = collect([$source])->merge($source->children)->unique('id')->values();
            $rows[] = $this->mapSourceRow(
                source: $source,
                sourceFamily: $family,
                orders: $orderCollection,
                leadCountsBySource: $leadCountsBySource,
                primaryCountsBySource: $primaryCountsBySource,
                upsaleCountsBySource: $upsaleCountsBySource,
                stt: $stt,
                parentId: null,
            );

            foreach ($source->children as $child) {
                $rows[] = $this->mapSourceRow(
                    source: $child,
                    sourceFamily: collect([$child]),
                    orders: $orderCollection,
                    leadCountsBySource: $leadCountsBySource,
                    primaryCountsBySource: $primaryCountsBySource,
                    upsaleCountsBySource: $upsaleCountsBySource,
                    stt: $stt,
                    parentId: $source->id,
                );
            }
        }

        $totals = $this->aggregateTotals($rows);
        $kpis = $this->buildKpis($orderCollection, $rows);
        $tree = $this->teamTree->build($filter, $orderCollection);

        return [
            'rows' => $rows,
            'filterTotal' => $totals,
            'pageTotal' => $totals,
            'kpis' => $kpis,
            'teamTree' => $tree,
        ];
    }

    /**
     * @param Collection<int, Order> $orders
     * @param list<array<string, mixed>> $rows
     * @return array<string, int|float>
     */
    private function buildKpis(Collection $orders, array $rows): array
    {
        $parents = array_filter($rows, fn (array $row): bool => ! ($row['isChild'] ?? false));
        $contacts = max(array_sum(array_column($parents, 'contacts')), 0);
        $contactOrderIds = LeadContactMetrics::contactOrderIds($orders);
        $closed = (int) $orders->whereIn('id', $contactOrderIds)
            ->filter(fn (Order $order): bool => $this->isClosed($order))
            ->count();
        $revenue = (int) $orders->sum(fn (Order $order): int => $order->netRevenue());
        $productQty = (int) $orders->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));

        return [
            'totalRevenue' => $revenue,
            'productQuantity' => $productQty,
            'conversionRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
            'averageOrderValue' => $closed > 0 ? (int) round($revenue / $closed) : 0,
            'closedOrders' => $closed,
            'contacts' => $contacts,
        ];
    }

    /**
     * Parent source là dòng tổng của cả family (parent + children); child chỉ
     * phản ánh chính source con. Nhờ vậy KPI/tổng không bỏ sót lead của source
     * con nhưng UI vẫn có thể drill-down mà không bị double-count.
     *
     * @param Collection<int, MarketingSource> $sourceFamily
     * @param Collection<int, Order> $orders
     * @param Collection<int, int> $leadCountsBySource
     * @param Collection<int, int> $primaryCountsBySource
     * @param Collection<int, int> $upsaleCountsBySource
     * @return array<string, mixed>
     */
    private function mapSourceRow(
        MarketingSource $source,
        Collection $sourceFamily,
        Collection $orders,
        Collection $leadCountsBySource,
        Collection $primaryCountsBySource,
        Collection $upsaleCountsBySource,
        int $stt,
        ?int $parentId,
    ): array {
        $sourceIds = $sourceFamily->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $sourceOrders = $orders->whereIn('marketing_source_id', $sourceIds);
        $contacts = (int) collect($sourceIds)
            ->sum(fn (int $sourceId): int => (int) $leadCountsBySource->get($sourceId, 0));
        $primaryPackets = (int) collect($sourceIds)
            ->sum(fn (int $sourceId): int => (int) $primaryCountsBySource->get($sourceId, 0));
        $upsalePackets = (int) collect($sourceIds)
            ->sum(fn (int $sourceId): int => (int) $upsaleCountsBySource->get($sourceId, 0));
        $interactions = max($contacts, 1);
        $contactOrderIds = LeadContactMetrics::contactOrderIds($sourceOrders);
        $closed = $sourceOrders->whereIn('id', $contactOrderIds)
            ->filter(fn (Order $order): bool => $this->isClosed($order))
            ->count();
        $budget = (int) $sourceFamily->sum('budget');
        $productQty = (int) $sourceOrders->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
        $totalRevenue = (int) $sourceOrders->sum(fn (Order $order): int => $order->netRevenue());
        $revenueAfterDiscount = $totalRevenue;

        return [
            'id' => (string) $source->id,
            'stt' => $stt,
            'parentId' => $parentId !== null ? (string) $parentId : null,
            'isChild' => $parentId !== null,
            'sourceName' => $source->name,
            'productName' => $source->product?->name ?? '—',
            'adChannel' => $source->ad_channel ?? '—',
            'utmSource' => $source->utm_source,
            'utmCampaign' => $source->utm_campaign,
            'budget' => $budget,
            'interactions' => $interactions,
            'contacts' => $contacts,
            'primaryPackets' => $primaryPackets,
            'upsalePackets' => $upsalePackets,
            'baseContacts' => $primaryPackets,
            'upsaleContacts' => $upsalePackets,
            'contactRate' => $interactions > 0 ? round($contacts / $interactions * 100, 1) : 0,
            'costPerContact' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closedOrders' => $closed,
            'closingRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
            'productQuantity' => $productQty,
            'avgProductPerOrder' => $closed > 0 ? round($productQty / $closed, 1) : 0,
            'totalRevenue' => $totalRevenue,
            'revenueAfterDiscount' => $revenueAfterDiscount,
            'averageOrderValue' => $closed > 0 ? (int) round($totalRevenue / $closed) : 0,
            'budgetRevenueRatio' => $totalRevenue > 0 ? round($budget / $totalRevenue * 100, 1) : 0,
            'budgetNetRevenueRatio' => $revenueAfterDiscount > 0 ? round($budget / $revenueAfterDiscount * 100, 1) : 0,
        ];
    }

    private function isClosed(Order $order): bool
    {
        return $order->closed_at !== null
            || (string) $order->closing_status === ClosingStatus::Closed->value;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function aggregateTotals(array $rows): array
    {
        $parents = array_filter($rows, fn (array $row): bool => ! ($row['isChild'] ?? false));

        return [
            'budget' => array_sum(array_column($parents, 'budget')),
            'contacts' => array_sum(array_column($parents, 'contacts')),
            'primaryPackets' => array_sum(array_column($parents, 'primaryPackets')),
            'upsalePackets' => array_sum(array_column($parents, 'upsalePackets')),
            'baseContacts' => array_sum(array_column($parents, 'baseContacts')),
            'upsaleContacts' => array_sum(array_column($parents, 'upsaleContacts')),
            'closedOrders' => array_sum(array_column($parents, 'closedOrders')),
            'totalRevenue' => array_sum(array_column($parents, 'totalRevenue')),
        ];
    }
}
