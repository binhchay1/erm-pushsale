<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\OperationResult;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
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

        $rows = [];
        $totals = [
            'leadsGenerated' => 0,
            'junkLeads' => 0,
            'adCost' => 0,
            'actualRevenue' => 0,
            'roas' => 0,
            'netContribution' => 0,
        ];

        foreach ($campaigns as $index => $campaign) {
            $campaignOrders = $orders->where('marketing_source_id', $campaign->id);
            $leads = $this->leadsForCampaign($campaign, $filter, $viewer);
            $junkCount = $this->countJunkOrders($campaignOrders);
            $leadCount = max($leads->count(), $campaignOrders->count());
            $revenueEligible = $campaignOrders->whereIn('delivery_status', DeliveryStatus::revenueEligible());
            $metrics = MarketingMetrics::summarize($revenueEligible, collect([$campaign]));
            $revenue = $metrics['attributed_revenue'];

            $row = [
                'stt' => $index + 1,
                'campaignId' => (string) $campaign->id,
                'campaignName' => $campaign->name,
                'marketerName' => $campaign->marketer?->name ?? '—',
                'creatorName' => $campaign->creator?->name ?? '—',
                'leadsGenerated' => $leadCount,
                'junkLeadRate' => $leadCount > 0 ? round($junkCount / $leadCount * 100, 1) : 0,
                'junkLeads' => $junkCount,
                'adCost' => $metrics['ad_spend'],
                'actualRevenue' => $revenue,
                'roas' => $metrics['roas'],
                'netContribution' => $metrics['net_contribution'],
                'isTotalRow' => false,
            ];

            $rows[] = $row;
            $totals['leadsGenerated'] += $leadCount;
            $totals['junkLeads'] += $junkCount;
            $totals['adCost'] += $metrics['ad_spend'];
            $totals['actualRevenue'] += $revenue;
            $totals['netContribution'] += $metrics['net_contribution'];
        }

        $totalLeads = max(1, $totals['leadsGenerated']);
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
            'junkLeadRate' => round($totals['junkLeads'] / $totalLeads * 100, 1),
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
            ->with(['marketer:id,name', 'creator:id,name'])
            ->whereNull('parent_id')
            ->orderBy('name');

        if ($filter->productId) {
            $query->whereHas('orders', fn (Builder $order) => $order->where('product_id', $filter->productId));
        }

        if ($filter->marketerId) {
            $query->where('marketer_user_id', $filter->marketerId);
        } elseif ($viewer->role === UserRole::Marketing) {
            $query->whereIn('marketer_user_id', $this->scope->allowedMarketerIds($viewer));
        }

        return $query->get();
    }

    /** @return Collection<int, LeadIngestion> */
    private function leadsForCampaign(MarketingSource $campaign, ReportFilterData $filter, User $viewer): Collection
    {
        if (! $filter->dateFrom || ! $filter->dateTo) {
            return collect();
        }

        $query = LeadIngestion::query()
            ->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])
            ->whereNotIn('status', [LeadIngestionStatus::Failed->value])
            ->where(function (Builder $q) use ($campaign) {
                $q->whereHas('order', fn (Builder $order) => $order->where('marketing_source_id', $campaign->id));

                if ($campaign->utm_campaign) {
                    $q->orWhere('utm_campaign', $campaign->utm_campaign);
                }
            });

        if ($filter->productId) {
            $query->whereHas('order', fn (Builder $order) => $order->where('product_id', $filter->productId));
        }

        if ($viewer->role === UserRole::Marketing) {
            $marketerIds = $this->scope->allowedMarketerIds($viewer);
            $sourceIds = MarketingSource::query()
                ->whereIn('marketer_user_id', $marketerIds)
                ->pluck('id');

            $query->where(function (Builder $q) use ($marketerIds, $sourceIds) {
                $q->whereHas('order', fn (Builder $order) => $order->whereIn('marketer_user_id', $marketerIds))
                    ->orWhereIn('utm_campaign', MarketingSource::query()->whereIn('id', $sourceIds)->pluck('utm_campaign'));
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function countJunkOrders(Collection $orders): int
    {
        return $orders->filter(function (Order $order) {
            $result = OperationResult::tryFromStored($order->operation_result);

            return $result?->isJunkLead() ?? false;
        })->count();
    }
}
