<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function queryFiltered(ReportFilterData $filter): Builder
    {
        return Order::query()
            ->with([
                'items.product:id,name,sku,parent_id,unit_price',
                'internalMessages' => fn ($query) => $query->latest('id')->limit(1),
                'saleUser',
                'marketerUser',
                'marketingSource.product',
                'landingConnection:id,success_url',
                'landingConnection.sources:id,landing_connection_id,source_type,source_url,sort_order,is_active',
                'landingConnectionSource:id,landing_connection_id,name,source_url',
                'warehouse',
                'team',
                'supplementalOriginPacket.relatedOrder:id,order_code',
                'leadPackets:id,order_id,payload',
                'relatedLeadPackets:id,related_order_id,payload',
            ])
            ->withCount('pendingSupplementPackets')
            ->applyReportFilter($filter)
            ->latest('data_arrived_at');
    }

    public function allFiltered(ReportFilterData $filter): Collection
    {
        return $this->queryFiltered($filter)->get();
    }

    public function paginatedFiltered(ReportFilterData $filter): LengthAwarePaginator
    {
        return $this->queryFiltered($filter)->paginate($filter->perPage)->withQueryString();
    }
}
