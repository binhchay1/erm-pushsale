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
            ->with(['items', 'saleUser', 'marketerUser', 'marketingSource.product', 'warehouse', 'team', 'supplementalOriginPacket.relatedOrder:id,order_code'])
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
