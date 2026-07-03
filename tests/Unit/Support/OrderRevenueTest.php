<?php

namespace Tests\Unit\Support;

use App\Enums\DeliveryStatus;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Support\OrderRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_subtracts_shipping_and_marketing_costs(): void
    {
        $source = MarketingSource::query()->create([
            'name' => 'FB Ads',
            'budget' => 500_000,
        ]);

        Order::query()->create([
            'order_code' => 'ORD-PNL-1',
            'marketing_source_id' => $source->id,
            'customer_name' => 'Test',
            'customer_phone' => '0900111222',
            'delivery_status' => DeliveryStatus::Paid->value,
            'total' => 2_000_000,
            'carrier_service_fee' => 50_000,
            'cod_fee' => 20_000,
            'updated_at' => now(),
        ]);

        $breakdown = OrderRevenue::aggregate(Order::query());

        $this->assertSame(2_000_000, $breakdown['gross']);
        $this->assertSame(70_000, $breakdown['shipping_cost']);
        $this->assertSame(500_000, $breakdown['marketing_cost']);
        $this->assertSame(1_430_000, $breakdown['net']);
    }

    public function test_net_revenue_uses_final_total_without_double_discount(): void
    {
        // `total` là GIÁ TRỊ CUỐI của đơn (đã trừ chiết khấu) → không trừ discount lần nữa.
        $order = Order::query()->make([
            'total' => 1_000_000,
            'discount' => 100_000,
            'carrier_service_fee' => 30_000,
            'cod_fee' => 10_000,
        ]);

        $this->assertSame(1_000_000, $order->effectiveRevenue());
        $this->assertSame(40_000, $order->shippingCost());
        $this->assertSame(960_000, $order->netRevenue());
    }

    public function test_effective_revenue_falls_back_to_subtotal_minus_discount_for_legacy_orders(): void
    {
        // Đơn cũ chưa có total → fallback subtotal − discount.
        $order = Order::query()->make([
            'total' => 0,
            'subtotal' => 800_000,
            'discount' => 100_000,
        ]);

        $this->assertSame(700_000, $order->effectiveRevenue());
    }
}
