<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Support\LeadContactMetrics;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function __construct(
        private readonly ReportScopeResolver $scopeResolver,
    ) {}

    /** @return Builder<Order> */
    public function orders(User $user, ReportFilterData $filter): Builder
    {
        $query = Order::query()->applyReportFilter($filter);

        return $this->scopeResolver->applyOrderScope($query, $user, $filter);
    }

    /** @return Builder<LeadIngestion> */
    public function leads(User $user, ReportFilterData $filter): Builder
    {
        $query = LeadContactMetrics::countableQuery($filter)
            ->when($filter->sourceType, fn (Builder $q) => $q->where('platform', $filter->sourceType))
            ->when($filter->search, function (Builder $q) use ($filter) {
                $term = '%'.$filter->search.'%';
                $q->where(function (Builder $search) use ($term) {
                    $search->where('customer_name', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhere('external_id', 'like', $term)
                        ->orWhere('utm_source', 'like', $term)
                        ->orWhere('utm_campaign', 'like', $term);
                });
            });

        return $this->scopeResolver->applyLeadScope($query, $user, $filter);
    }

    /** @return Builder<LeadIngestion> — mọi lead chính (kể cả duplicate/failed), không gồm packet bổ sung. */
    public function rawLeads(User $user, ReportFilterData $filter): Builder
    {
        $query = LeadIngestion::query()
            ->where('counts_as_lead', true)
            ->when($filter->dateFrom && $filter->dateTo, fn (Builder $q) => $q->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo]))
            ->when($filter->sourceType, fn (Builder $q) => $q->where('platform', $filter->sourceType));

        return $this->scopeResolver->applyLeadScope($query, $user, $filter);
    }

    /** @return Builder<Order> */
    public function ordersForSeries(User $user, ReportFilterData $filter): Builder
    {
        return $this->orders($user, $filter)->whereNotNull($this->dateColumn($filter));
    }

    /** @return Builder<Order> */
    public function ordersGroupedBySale(User $user, ReportFilterData $filter): Builder
    {
        return $this->orders($user, $filter)
            ->select('sale_user_id', DB::raw('count(*) as orders_count'), DB::raw('sum(total) as revenue_total'))
            ->whereNotNull('sale_user_id')
            ->groupBy('sale_user_id');
    }

    /** @return Builder<Order> */
    public function ordersGroupedByMarketingSource(User $user, ReportFilterData $filter): Builder
    {
        return $this->orders($user, $filter)
            ->select('marketing_source_id', DB::raw('count(*) as orders_count'), DB::raw('sum(total) as revenue_total'))
            ->whereNotNull('marketing_source_id')
            ->groupBy('marketing_source_id');
    }

    /** @return Builder<LeadIngestion> */
    public function leadsGroupedByPlatform(User $user, ReportFilterData $filter): Builder
    {
        return $this->leads($user, $filter)
            ->select('platform', DB::raw('count(*) as leads_count'))
            ->groupBy('platform');
    }

    public function dateColumn(ReportFilterData $filter): string
    {
        return $filter->dateType->orderColumn();
    }
}
