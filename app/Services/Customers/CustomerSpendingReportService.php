<?php

namespace App\Services\Customers;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CustomerSpendingReportService
{
    /** @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>, filters: array<string, mixed>, filterOptions: array<string, mixed>} */
    public function build(Request $request, ?User $user = null): array
    {
        $filters = $this->filtersFromRequest($request);
        $orders = $this->filteredOrders($filters);

        $phoneGroups = $orders->groupBy(function (Order $order): string {
            $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';
            if (str_starts_with($digits, '84')) {
                $digits = '0'.substr($digits, 2);
            }

            return $digits;
        });

        $totalPhones = max(1, $phoneGroups->count());
        $rows = collect();

        foreach (['new' => 'Khách mới', 'returning' => 'Khách cũ'] as $type => $label) {
            $typeOrders = $orders->filter(fn (Order $order) => $type === 'returning'
                ? (bool) $order->is_returning_customer
                : ! (bool) $order->is_returning_customer);

            $byDelivery = $typeOrders->groupBy(fn (Order $order) => $order->delivery_status ?: 'all');
            if ($byDelivery->isEmpty()) {
                $rows->push([
                    'customer_type' => $label,
                    'delivery_status' => 'Tất cả',
                    'customer_count' => 0,
                    'ratio' => 0,
                    'description' => $type === 'returning' ? 'Khách hàng có lịch sử mua trước đó' : 'Khách hàng phát sinh lần đầu',
                    'revenue' => 0,
                ]);
                continue;
            }

            foreach ($byDelivery as $status => $group) {
                $phones = $group->pluck('customer_phone')->filter()->unique()->count();
                $rows->push([
                    'customer_type' => $label,
                    'delivery_status' => $this->deliveryLabel((string) $status),
                    'customer_count' => $phones,
                    'ratio' => round(($phones / $totalPhones) * 100, 2),
                    'description' => $type === 'returning' ? 'Khách hàng có lịch sử mua trước đó' : 'Khách hàng phát sinh lần đầu',
                    'revenue' => (int) $group->sum('total'),
                ]);
            }
        }

        $mapped = $rows->values()->map(fn (array $row, int $index) => ['index' => $index + 1] + $row)->all();

        return [
            'rows' => $mapped,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => max(count($mapped), 1),
                'total' => count($mapped),
                'from' => count($mapped) ? 1 : 0,
                'to' => count($mapped),
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
            'delivery_status' => trim((string) $request->input('delivery_status', '')) ?: null,
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

        if ($filters['delivery_status']) {
            $query->where('delivery_status', $filters['delivery_status']);
        }

        return $query->get(['id', 'customer_phone', 'delivery_status', 'is_returning_customer', 'total']);
    }

    /** @return array<string, mixed> */
    private function filterOptions(?User $user): array
    {
        $options = app(CustomerProfileOptionsService::class)->build($user);

        return [
            'sales' => $options['sales'] ?? [],
            'marketers' => $options['marketers'] ?? [],
            'deliveryStatuses' => $options['deliveryStatuses'] ?? [],
            'dateTypes' => $options['dateTypes'] ?? [],
        ];
    }

    private function deliveryLabel(string $status): string
    {
        return match ($status) {
            'all' => 'Tất cả',
            'delivered' => 'Đã giao',
            'paid' => 'Đã thanh toán',
            'shipping' => 'Đang giao',
            'cancelled' => 'Hủy vận đơn',
            'returned' => 'Đã hoàn',
            default => $status,
        };
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
