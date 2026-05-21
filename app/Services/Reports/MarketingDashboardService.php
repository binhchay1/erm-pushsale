<?php

namespace App\Services\Reports;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Models\MarketingSource;

class MarketingDashboardService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        $orderCollection = $this->orders->allFiltered($filter);

        $sources = MarketingSource::query()
            ->with(['children.product', 'product'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $rows = [];
        $stt = 0;

        foreach ($sources as $source) {
            $stt++;
            $rows[] = $this->mapSourceRow($source, $orderCollection, $stt, null);

            foreach ($source->children as $child) {
                $rows[] = $this->mapSourceRow($child, $orderCollection, $stt, $source->id);
            }
        }

        $totals = $this->aggregateTotals($rows);

        return [
            'rows' => $rows,
            'filterTotal' => $totals,
            'pageTotal' => $totals,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Order>  $orders
     * @return array<string, mixed>
     */
    private function mapSourceRow(MarketingSource $source, $orders, int $stt, ?int $parentId): array
    {
        $sourceOrders = $orders->where('marketing_source_id', $source->id);
        $interactions = max($source->interactions, 1);
        $contacts = max($source->contacts, (int) $sourceOrders->sum('contact_count'), 1);
        $closed = $sourceOrders->count();
        $budget = $source->budget;
        $productQty = (int) $sourceOrders->sum(fn ($o) => $o->items->sum('quantity'));
        $totalRevenue = (int) $sourceOrders->sum(fn ($o) => $o->effectiveRevenue());
        $revenueAfterDiscount = $totalRevenue;

        return [
            'id' => (string) $source->id,
            'stt' => $parentId ? $stt : $stt,
            'parentId' => $parentId ? (string) $parentId : null,
            'isChild' => $parentId !== null,
            'sourceName' => $source->name,
            'productName' => $source->product?->name ?? '—',
            'adChannel' => $source->ad_channel ?? '—',
            'utmSource' => $source->utm_source,
            'utmCampaign' => $source->utm_campaign,
            'budget' => $budget,
            'interactions' => $interactions,
            'contacts' => $contacts,
            'contactRate' => round($contacts / $interactions * 100, 1),
            'costPerContact' => (int) round($budget / $contacts),
            'closedOrders' => $closed,
            'closingRate' => round($closed / $contacts * 100, 1),
            'productQuantity' => $productQty,
            'avgProductPerOrder' => $closed > 0 ? round($productQty / $closed, 1) : 0,
            'totalRevenue' => $totalRevenue,
            'revenueAfterDiscount' => $revenueAfterDiscount,
            'budgetRevenueRatio' => $totalRevenue > 0 ? round($budget / $totalRevenue * 100, 1) : 0,
            'budgetNetRevenueRatio' => $revenueAfterDiscount > 0 ? round($budget / $revenueAfterDiscount * 100, 1) : 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function aggregateTotals(array $rows): array
    {
        $parents = array_filter($rows, fn ($r) => ! ($r['isChild'] ?? false));

        return [
            'budget' => array_sum(array_column($parents, 'budget')),
            'contacts' => array_sum(array_column($parents, 'contacts')),
            'closedOrders' => array_sum(array_column($parents, 'closedOrders')),
            'totalRevenue' => array_sum(array_column($parents, 'totalRevenue')),
        ];
    }
}
