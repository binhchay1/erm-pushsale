<?php

namespace App\Services\Customers;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CustomerMultidimensionalReportService
{
    /** @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>, filters: array<string, mixed>, filterOptions: array<string, mixed>} */
    public function build(Request $request, ?User $user = null): array
    {
        $filters = $this->filtersFromRequest($request);
        $orders = $this->filteredOrders($filters);
        $dimension = $filters['dimension'];

        $buckets = match ($dimension) {
            'repurchase' => $this->repurchaseBuckets($orders),
            'delivery' => $this->deliveryBuckets($orders),
            'customer_type' => $this->customerTypeBuckets($orders),
            'care' => $this->careBuckets($orders),
            default => $this->repurchaseBuckets($orders),
        };

        $total = max(1, (int) $buckets->sum('quantity'));
        $rows = $buckets->values()->map(function (array $row, int $index) use ($total): array {
            return [
                'index' => $index + 1,
                'dimension' => $row['label'],
                'quantity' => (int) $row['quantity'],
                'ratio' => round(((int) $row['quantity'] / $total) * 100, 2),
            ];
        })->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => max(count($rows), 1),
                'total' => count($rows),
                'from' => count($rows) ? 1 : 0,
                'to' => count($rows),
            ],
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($user),
        ];
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        $from = $this->parseDate($request->input('date_from'), false) ?? now()->subDays(30)->startOfDay();
        $to = $this->parseDate($request->input('date_to'), true) ?? now()->endOfDay();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'date_type' => (string) $request->input('date_type', 'data_arrival'),
            'sale_id' => $request->integer('sale_id') ?: null,
            'marketer_id' => $request->integer('marketer_id') ?: null,
            'dimension' => (string) $request->input('dimension', 'repurchase'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function filteredOrders(array $filters): Collection
    {
        $dateColumn = match ($filters['date_type']) {
            'sale_received_data' => 'assigned_at',
            'sale_operation_date', 'updated_at' => 'updated_at',
            'closing_date' => 'closed_at',
            default => 'data_arrived_at',
        };

        $query = Order::query()
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->whereBetween($dateColumn, [
                Carbon::parse($filters['date_from'])->startOfDay(),
                Carbon::parse($filters['date_to'])->endOfDay(),
            ]);

        if ($filters['sale_id']) {
            $query->where('sale_user_id', $filters['sale_id']);
        }

        if ($filters['marketer_id']) {
            $query->where(function ($scope) use ($filters): void {
                $scope->where('marketer_user_id', $filters['marketer_id'])
                    ->orWhereHas('marketingSource', fn ($source) => $source->where('marketer_user_id', $filters['marketer_id']));
            });
        }

        return $query->get(['id', 'customer_phone', 'delivery_status', 'is_returning_customer', 'next_operation_at', 'operation_stage']);
    }

    private function repurchaseBuckets(Collection $orders): Collection
    {
        $counts = $orders->groupBy(function (Order $order): string {
            $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';
            if (str_starts_with($digits, '84')) {
                $digits = '0'.substr($digits, 2);
            }

            return $digits;
        })->map->count();

        $once = $counts->filter(fn ($count) => (int) $count === 1)->count();
        $repeat = $counts->filter(fn ($count) => (int) $count > 1)->count();

        return collect([
            ['label' => 'Khách mua 1 lần', 'quantity' => $once],
            ['label' => 'Khách mua lại (2+)', 'quantity' => $repeat],
            ['label' => 'Tổng số điện thoại', 'quantity' => $counts->count()],
        ]);
    }

    private function deliveryBuckets(Collection $orders): Collection
    {
        return $orders->groupBy(fn (Order $order) => $order->delivery_status ?: 'unknown')
            ->map(fn (Collection $group, string $status) => [
                'label' => match ($status) {
                    'delivered' => 'Đã giao',
                    'paid' => 'Đã thanh toán',
                    'shipping' => 'Đang giao',
                    'cancelled' => 'Hủy vận đơn',
                    'returned' => 'Đã hoàn',
                    default => $status,
                },
                'quantity' => $group->count(),
            ])->values();
    }

    private function customerTypeBuckets(Collection $orders): Collection
    {
        return collect([
            ['label' => 'Khách mới', 'quantity' => $orders->where('is_returning_customer', false)->count()],
            ['label' => 'Khách cũ', 'quantity' => $orders->where('is_returning_customer', true)->count()],
        ]);
    }

    private function careBuckets(Collection $orders): Collection
    {
        return collect([
            ['label' => 'Đang chăm sóc', 'quantity' => $orders->whereNotNull('next_operation_at')->count()],
            ['label' => 'Chưa có lịch chăm sóc', 'quantity' => $orders->whereNull('next_operation_at')->count()],
        ]);
    }

    /** @return array<string, mixed> */
    private function filterOptions(?User $user): array
    {
        $options = app(CustomerProfileOptionsService::class)->build($user);

        return [
            'sales' => $options['sales'] ?? [],
            'marketers' => $options['marketers'] ?? [],
            'dimensions' => [
                ['value' => 'repurchase', 'label' => '1. Số lần mua lại'],
                ['value' => 'delivery', 'label' => '2. Trạng thái giao hàng'],
                ['value' => 'customer_type', 'label' => '3. Khách mới / cũ'],
                ['value' => 'care', 'label' => '4. Tình trạng chăm sóc'],
            ],
            'dateTypes' => $options['dateTypes'] ?? [],
        ];
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
