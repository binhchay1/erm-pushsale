<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderTrace;
use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderTraceSearch
{
    /**
     * @return array{orders: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public function search(string $term): array
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) > 64) {
            return ['orders' => [], 'events' => []];
        }

        $orders = $this->orders()
            ->where(function ($query) use ($term): void {
                $query->where('order_code', $term)
                    ->orWhere('customer_phone', $term)
                    ->orWhere('receiver_phone', $term)
                    ->orWhere('tracking_number', $term);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'order_code', 'customer_name', 'customer_phone', 'tracking_number']);

        $linkedOrderIds = OrderTrace::query()
            ->where(fn ($query) => $this->matchTerm($query, $term))
            ->limit(50)
            ->pluck('order_id');

        if ($linkedOrderIds->filter()->isNotEmpty()) {
            $extra = $this->orders()
                ->whereIn('id', $linkedOrderIds->filter()->unique()->all())
                ->get(['id', 'order_code', 'customer_name', 'customer_phone', 'tracking_number']);
            $orders = $orders->concat($extra)->unique('id')->values();
        }

        $events = $this->events($term, $orders);

        return [
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'orderCode' => $order->order_code,
                'customerName' => $order->customer_name,
                'phone' => $order->customer_phone,
                'trackingNumber' => $order->tracking_number,
            ])->all(),
            'events' => $events->map(function (OrderTrace $trace) use ($orders): array {
                $order = $orders->firstWhere('id', $trace->order_id);

                return [
                    'id' => $trace->id,
                    'at' => $trace->occurred_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'stage' => $trace->stage,
                    'source' => $trace->source,
                    'action' => $trace->action,
                    'orderCode' => $trace->order_code ?: $order?->order_code,
                    'customerName' => $order?->effectiveReceiverName(),
                    'phone' => $trace->phone ?: $order?->effectiveReceiverPhone() ?: $order?->customer_phone,
                    'externalCode' => $trace->external_code ?: $order?->tracking_number,
                    'gatewayOrderId' => $trace->gateway_order_id,
                    'statusCode' => $trace->status_code,
                    'summary' => $trace->summary,
                    'payload' => $trace->payload,
                ];
            })->all(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, OrderTrace>
     */
    private function events(string $term, Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id')->all();
        $phones = $orders->pluck('customer_phone')->filter()->unique()->values()->all();
        $codes = $orders->pluck('order_code')->filter()->unique()->values()->all();
        $trackings = $orders->pluck('tracking_number')->filter()->unique()->values()->all();

        return OrderTrace::query()
            ->where(function ($query) use ($term, $orderIds, $phones, $codes, $trackings): void {
                $this->matchTerm($query, $term);

                if ($orderIds !== []) {
                    $query->orWhereIn('order_id', $orderIds);
                }
                if ($phones !== []) {
                    $query->orWhereIn('phone', $phones);
                }
                if ($codes !== []) {
                    $query->orWhereIn('order_code', $codes);
                }
                if ($trackings !== []) {
                    $query->orWhereIn('external_code', $trackings);
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(300)
            ->get();
    }

    private function orders(): Builder
    {
        return Order::query()->withoutGlobalScope(ShopScope::class);
    }

    private function matchTerm($query, string $term): void
    {
        $query->where('order_code', $term)
            ->orWhere('phone', $term)
            ->orWhere('external_code', $term)
            ->orWhere('gateway_order_id', $term)
            ->orWhere('lookup_code', $term)
            ->orWhere('partner_order_code', $term);
    }
}
