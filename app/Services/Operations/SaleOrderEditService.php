<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadOrderFactory;
use App\Support\ActivityLogger;
use App\Support\ShippingProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sửa nội dung đơn từ màn tác nghiệp telesale: sản phẩm/combo, chiết khấu,
 * đơn vị vận chuyển. Doanh thu (total) được tính lại theo giá trị cuối đơn.
 */
class SaleOrderEditService
{
    public function __construct(
        private readonly LeadOrderFactory $factory,
    ) {}

    /**
     * @param  array{items?: array<int, array<string, mixed>>, discount?: int|null, shipping_provider?: string|null, carrier_name?: string|null}  $payload
     */
    public function update(Order $order, User $actor, array $payload): Order
    {
        if ($actor->isSales() && $order->sale_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.no_permission_operate'),
            ]);
        }

        if (! SaleOperationPolicy::canChangeStatus($order)) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.cannot_change_status'),
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $payload) {
            if (array_key_exists('items', $payload) && is_array($payload['items'])) {
                $order->items()->delete();
                foreach ($this->factory->buildItemRows($payload['items'], 'telesale') as $row) {
                    $order->items()->create($row);
                }
            }

            // Thông tin khách hàng & giao hàng (chỉ cập nhật khi FE gửi lên).
            foreach ([
                'customer_name' => 'customer_name',
                'customer_phone' => 'customer_phone',
                'shipping_address' => 'shipping_address',
                'customer_note' => 'customer_note',
            ] as $key => $column) {
                if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                    $order->{$column} = $payload[$key];
                }
            }

            if (array_key_exists('warehouse_id', $payload)) {
                $order->warehouse_id = $payload['warehouse_id'] ?: null;
            }

            if (array_key_exists('shipping_fee_collected', $payload)) {
                $order->shipping_fee_collected = max(0, (int) ($payload['shipping_fee_collected'] ?? 0));
            }

            if (array_key_exists('deposit', $payload)) {
                $order->deposit = max(0, (int) ($payload['deposit'] ?? 0));
            }

            if (array_key_exists('vat', $payload)) {
                $order->vat = max(0, (int) ($payload['vat'] ?? 0));
            }

            if (array_key_exists('discount', $payload)) {
                $order->discount = max(0, (int) ($payload['discount'] ?? 0));
            }

            if (array_key_exists('shipping_provider', $payload)) {
                $provider = $payload['shipping_provider'] ?: null;
                $order->shipping_provider = $provider;
                // Đồng bộ tên hãng hiển thị theo provider, trừ khi FE gửi carrier_name riêng.
                if (empty($payload['carrier_name'])) {
                    $order->carrier_name = ShippingProviders::label($provider);
                }
            }

            if (! empty($payload['carrier_name'])) {
                $order->carrier_name = $payload['carrier_name'];
            }

            $order->save();

            $fresh = $this->factory->syncTotals($order->fresh(['items']));

            ActivityLogger::log(
                ActivityLogger::ORDER_UPDATED,
                $fresh,
                [
                    'total' => $fresh->total,
                    'discount' => $fresh->discount,
                    'items' => $fresh->items->count(),
                    'shipping_provider' => $fresh->shipping_provider,
                ],
                $fresh->order_code ?? ('#'.$fresh->id),
                $actor,
            );

            return $fresh->fresh(['items', 'saleUser', 'team', 'marketingSource', 'warehouse']);
        });
    }
}
