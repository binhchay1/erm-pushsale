<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Support\LeadContactMetrics;
use App\Support\MarketingRawPacketMetrics;
use App\Support\MarketingMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketingCampaignReportService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ReportScopeResolver $scope,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter, User $viewer): array
    {
        $orders = $this->queries->orders($viewer, $filter)->with('items')->get();
        $campaigns = $this->campaigns($viewer, $filter);
        $leadCountsBySource = MarketingRawPacketMetrics::effectiveCountsBySource($filter, $orders);
        $upsaleCountsBySource = MarketingRawPacketMetrics::effectiveUpsaleCountsBySource($filter, $orders);

        $rows = [];
        $totals = [
            'leadsGenerated' => 0,
            'primaryPackets' => 0,
            'upsalePackets' => 0,
            'junkLeads' => 0,
            'adCost' => 0,
            'actualRevenue' => 0,
            'roas' => 0,
            'netContribution' => 0,
        ];

        foreach ($campaigns as $index => $campaign) {
            $family = collect([$campaign])->merge($campaign->children)->unique('id')->values();
            $sourceIds = $family->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $campaignOrders = $orders->whereIn('marketing_source_id', $sourceIds);

            $leadCount = (int) collect($sourceIds)
                ->sum(fn (int $sourceId): int => (int) $leadCountsBySource->get($sourceId, 0));
            $upsaleCount = (int) collect($sourceIds)
                ->sum(fn (int $sourceId): int => (int) $upsaleCountsBySource->get($sourceId, 0));
            $primaryCount = max(0, $leadCount - $upsaleCount);

            // Junk-rate remains based on primary contacts; an upsale packet is
            // marketing traffic but is not an independent junk customer.
            $contactOrderIds = LeadContactMetrics::contactOrderIds($campaignOrders);
            $junkCount = $this->countJunkOrders($campaignOrders->whereIn('id', $contactOrderIds));

            $revenueEligible = $campaignOrders->whereIn('delivery_status', DeliveryStatus::revenueEligible());
            $metrics = MarketingMetrics::summarize($revenueEligible, $family);
            $revenue = $metrics['attributed_revenue'];

            $row = [
                'stt' => $index + 1,
                'campaignId' => (string) $campaign->id,
                'campaignName' => $campaign->name,
                'marketerName' => $campaign->marketer?->name ?? '—',
                'creatorName' => $campaign->creator?->name ?? '—',
                'leadsGenerated' => $leadCount,
                'primaryPackets' => $primaryCount,
                'upsalePackets' => $upsaleCount,
                'junkLeadRate' => $primaryCount > 0 ? round($junkCount / $primaryCount * 100, 1) : 0,
                'junkLeads' => $junkCount,
                'adCost' => $metrics['ad_spend'],
                'actualRevenue' => $revenue,
                'roas' => $metrics['roas'],
                'netContribution' => $metrics['net_contribution'],
                'isTotalRow' => false,
            ];

            $rows[] = $row;
            $totals['leadsGenerated'] += $leadCount;
            $totals['primaryPackets'] += $primaryCount;
            $totals['upsalePackets'] += $upsaleCount;
            $totals['junkLeads'] += $junkCount;
            $totals['adCost'] += $metrics['ad_spend'];
            $totals['actualRevenue'] += $revenue;
            $totals['netContribution'] += $metrics['net_contribution'];
        }

        $totalRoas = $totals['adCost'] > 0
            ? round($totals['actualRevenue'] / $totals['adCost'], 2)
            : 0.0;

        array_unshift($rows, [
            'stt' => 0,
            'campaignId' => 'total',
            'campaignName' => __('reports.total'),
            'marketerName' => '—',
            'creatorName' => '—',
            'leadsGenerated' => $totals['leadsGenerated'],
            'primaryPackets' => $totals['primaryPackets'],
            'upsalePackets' => $totals['upsalePackets'],
            'junkLeadRate' => round($totals['junkLeads'] / max(1, $totals['primaryPackets']) * 100, 1),
            'junkLeads' => $totals['junkLeads'],
            'adCost' => $totals['adCost'],
            'actualRevenue' => $totals['actualRevenue'],
            'roas' => $totalRoas,
            'netContribution' => $totals['netContribution'],
            'isTotalRow' => true,
        ]);

        return [
            'rows' => $rows,
            'columns' => $this->columns(),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'campaignName', 'label' => __('reports.campaign_report.campaign_name')],
            ['key' => 'marketerName', 'label' => __('reports.campaign_report.marketer_name')],
            ['key' => 'creatorName', 'label' => __('reports.campaign_report.creator_name')],
            ['key' => 'leadsGenerated', 'label' => __('reports.campaign_report.leads_generated')],
            ['key' => 'primaryPackets', 'label' => __('reports.campaign_report.primary_packets')],
            ['key' => 'upsalePackets', 'label' => __('reports.campaign_report.upsale_packets')],
            ['key' => 'junkLeadRate', 'label' => __('reports.campaign_report.junk_lead_rate')],
            ['key' => 'adCost', 'label' => __('reports.campaign_report.ad_cost')],
            ['key' => 'actualRevenue', 'label' => __('reports.campaign_report.actual_revenue')],
            ['key' => 'roas', 'label' => __('reports.campaign_report.roas')],
            ['key' => 'netContribution', 'label' => __('reports.campaign_report.net_contribution')],
        ];
    }

    /** @return Collection<int, MarketingSource> */
    private function campaigns(User $viewer, ReportFilterData $filter): Collection
    {
        $query = MarketingSource::query()
            ->visibleInReports()
            ->with([
                'marketer:id,name,email',
                'creator:id,name',
                'children.marketer:id,name,email',
                'children.creator:id,name',
            ])
            ->whereNull('parent_id')
            ->orderBy('name');

        if ($filter->productId) {
            $query->where(function (Builder $family) use ($filter): void {
                $family->whereHas('orders', fn (Builder $order) => $order->where('product_id', $filter->productId))
                    ->orWhereHas('children.orders', fn (Builder $order) => $order->where('product_id', $filter->productId));
            });
        }

        if ($filter->marketerId) {
            $query->where(function (Builder $family) use ($filter): void {
                $family->where('marketer_user_id', $filter->marketerId)
                    ->orWhereHas('children', fn (Builder $child) => $child->where('marketer_user_id', $filter->marketerId));
            });
        } elseif ($viewer->role === UserRole::Marketing) {
            $marketerIds = $this->scope->allowedMarketerIds($viewer);
            $query->where(function (Builder $family) use ($marketerIds): void {
                $family->whereIn('marketer_user_id', $marketerIds)
                    ->orWhereHas('children', fn (Builder $child) => $child->whereIn('marketer_user_id', $marketerIds));
            });
        }

        return $query->get();
    }

    /** @param Collection<int, Order> $orders */
    private function countJunkOrders(Collection $orders): int
    {
        return $orders->filter(function (Order $order): bool {
            $result = OperationResult::tryFromStored($order->operation_result);

            return $result?->isJunkLead() ?? false;
        })->count();
    }
}
