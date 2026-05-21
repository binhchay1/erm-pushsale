<?php

namespace App\Contracts\Repositories;

use App\Data\ReportFilterData;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    public function queryFiltered(ReportFilterData $filter): Builder;

    /** @return Collection<int, Order> */
    public function allFiltered(ReportFilterData $filter): Collection;

    public function paginatedFiltered(ReportFilterData $filter): LengthAwarePaginator;
}
