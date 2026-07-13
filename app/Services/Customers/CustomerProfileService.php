<?php

namespace App\Services\Customers;

use App\Data\Customers\CustomerProfileFilterData;
use App\Models\Order;
use App\Services\Operations\OrderOperationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CustomerProfileService
{
    /** @return array<string, mixed> */
    public function paginate(CustomerProfileFilterData $filter): array
    {
        $base = $this->filteredQuery($filter);
        $phoneExpression = $this->normalizedPhoneExpression('orders.customer_phone');

        // Một khách hàng chỉ có đúng một dòng theo số điện thoại đã chuẩn hóa.
        // Dòng đại diện là đơn mới nhất còn thỏa bộ lọc.
        $grouped = (clone $base)
            ->reorder()
            ->selectRaw('MAX(orders.id) AS latest_order_id')
            ->whereNotNull('orders.customer_phone')
            ->where('orders.customer_phone', '!=', '')
            ->groupByRaw($phoneExpression);

        /** @var LengthAwarePaginator $idPaginator */
        $idPaginator = DB::query()
            ->fromSub($grouped->toBase(), 'unique_customers')
            ->select('latest_order_id')
            ->orderByDesc('latest_order_id')
            ->paginate(
                perPage: $filter->perPage,
                columns: ['*'],
                pageName: 'page',
                page: $filter->page,
            )
            ->withQueryString();

        $ids = collect($idPaginator->items())
            ->pluck('latest_order_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $orders = Order::query()
            ->with([
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
                'saleUser:id,name,email,team_id,manager_user_id',
                'marketerUser:id,name,email,team_id,manager_user_id',
                'marketingSource:id,name,marketer_user_id,ad_channel,utm_source,utm_campaign',
                'marketingSource.marketer:id,name,email',
                'warehouse:id,name',
                'team:id,name,leader_user_id',
                'supplementalOriginPacket.relatedOrder:id,order_code',
            ])
            ->withCount('pendingSupplementPackets')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = $ids->map(function (int $id) use ($orders): ?array {
            $order = $orders->get($id);
            if (! $order) {
                return null;
            }

            $row = OrderOperationPresenter::toArray($order);
            $row['saleEmail'] = $order->saleUser?->email;
            $row['marketerName'] = $order->marketerUser?->name ?? $order->marketingSource?->marketer?->name;
            $row['marketerEmail'] = $order->marketerUser?->email ?? $order->marketingSource?->marketer?->email;
            $row['sourceChannel'] = $order->marketingSource?->ad_channel;
            $row['sourceUtm'] = $order->marketingSource?->utm_source;
            $row['lastOperationAt'] = $order->updated_at?->toIso8601String();
            $row['shippingMethod'] = $order->shipping_method;
            $row['reconciliationStatus'] = $order->reconciliation_status;

            return $row;
        })->filter()->values()->all();

        return [
            'rows' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $idPaginator->currentPage(),
                    'last_page' => $idPaginator->lastPage(),
                    'per_page' => $idPaginator->perPage(),
                    'total' => $idPaginator->total(),
                    'from' => $idPaginator->firstItem(),
                    'to' => $idPaginator->lastItem(),
                ],
            ],
        ];
    }

    public function filteredQuery(CustomerProfileFilterData $filter): Builder
    {
        $query = Order::query();
        $dateColumn = match ($filter->dateType) {
            'sale_received_data' => 'orders.assigned_at',
            'sale_operation_date', 'updated_at' => 'orders.updated_at',
            'closing_date' => 'orders.closed_at',
            'expected_delivery_date' => 'orders.desired_delivery_at',
            default => 'orders.data_arrived_at',
        };

        if ($filter->dateFrom && $filter->dateTo) {
            $query->whereBetween($dateColumn, [$filter->dateFrom, $filter->dateTo]);
        }

        if ($filter->sourceId) {
            $query->where('orders.marketing_source_id', $filter->sourceId);
        }

        if ($filter->saleLeaderId) {
            $query->whereHas('team', fn (Builder $team) => $team->where('leader_user_id', $filter->saleLeaderId));
        }

        if ($filter->saleTeamId) {
            $query->where('orders.team_id', $filter->saleTeamId);
        }

        if ($filter->saleId) {
            $query->where('orders.sale_user_id', $filter->saleId);
        }

        if ($filter->marketingLeaderId) {
            $query->whereHas('marketerUser.team', fn (Builder $team) => $team->where('leader_user_id', $filter->marketingLeaderId));
        }

        if ($filter->marketingTeamId) {
            $query->whereHas('marketerUser', fn (Builder $user) => $user->where('team_id', $filter->marketingTeamId));
        }

        if ($filter->marketerId) {
            $query->where(function (Builder $scope) use ($filter): void {
                $scope->where('orders.marketer_user_id', $filter->marketerId)
                    ->orWhereHas('marketingSource', fn (Builder $source) => $source->where('marketer_user_id', $filter->marketerId));
            });
        }

        if ($filter->productId) {
            $query->where(function (Builder $scope) use ($filter): void {
                $scope->where('orders.product_id', $filter->productId)
                    ->orWhereHas('items', fn (Builder $items) => $items->where('product_id', $filter->productId));
            });
        }

        if ($filter->warehouseId) {
            $query->where('orders.warehouse_id', $filter->warehouseId);
        }

        if ($filter->careStatus === 'care') {
            $query->whereNotNull('orders.operation_stage')->where('orders.operation_stage', '!=', 'new_customer');
        } elseif ($filter->careStatus === 'not_care') {
            $query->where(function (Builder $scope): void {
                $scope->whereNull('orders.operation_stage')->orWhere('orders.operation_stage', 'new_customer');
            });
        }

        if ($filter->closingStatus === 'open') {
            $query->whereNull('orders.closed_at')->where(function (Builder $scope): void {
                $scope->whereNull('orders.closing_status')->orWhere('orders.closing_status', 'open');
            });
        } elseif ($filter->closingStatus) {
            $query->where('orders.closing_status', $filter->closingStatus);
        }

        if ($filter->operationStage) {
            $query->where('orders.operation_stage', $filter->operationStage);
        }

        if ($filter->operationResult) {
            $query->where('orders.operation_result', $filter->operationResult);
        }

        if ($filter->deliveryStatus) {
            $query->where('orders.delivery_status', $filter->deliveryStatus);
        }

        if ($filter->reconciliationStatus) {
            $query->where('orders.reconciliation_status', $filter->reconciliationStatus);
        }

        if ($filter->duplicateStatus === 'duplicate') {
            $query->where('orders.is_duplicate_phone', true);
        } elseif ($filter->duplicateStatus === 'unique') {
            $query->where('orders.is_duplicate_phone', false);
        }

        if ($filter->customerType === 'returning') {
            $query->where('orders.is_returning_customer', true);
        } elseif ($filter->customerType === 'new') {
            $query->where('orders.is_returning_customer', false);
        }

        if ($filter->allocationStatus === 'assigned') {
            $query->whereNotNull('orders.sale_user_id');
        } elseif ($filter->allocationStatus === 'unassigned') {
            $query->whereNull('orders.sale_user_id');
        }

        if ($filter->shippingMethod) {
            $query->where('orders.shipping_method', $filter->shippingMethod);
        }

        if ($filter->search) {
            $term = '%'.$filter->search.'%';
            $query->where(function (Builder $scope) use ($term): void {
                $scope->where('orders.customer_name', 'like', $term)
                    ->orWhere('orders.customer_phone', 'like', $term)
                    ->orWhere('orders.order_code', 'like', $term);
            });
        }

        return $query;
    }

    public function normalizedPhoneExpression(string $column): string
    {
        $digits = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '.', ''), '-', ''), '(', ''), ')', ''), '+', ''), '/', '')";

        return "CASE WHEN {$digits} LIKE '84%' THEN CONCAT('0', SUBSTRING({$digits}, 3)) ELSE {$digits} END";
    }
}
