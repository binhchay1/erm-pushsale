<?php

namespace Tests\Feature\Shipping;

use App\Enums\ReconciliationStatus;
use App\Models\CarrierSettlementBatch;
use App\Models\CarrierSettlementLine;
use App\Models\Order;
use App\Services\Shipping\Settlement\CarrierSettlementMatcher;
use App\Services\Shipping\Settlement\CarrierSettlementSyncService;
use App\Services\Shipping\Settlement\ShipmentReconciliationEngine;
use App\Services\Shipping\ShippingWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShipmentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_matcher_finds_order_by_tracking_then_order_code(): void
    {
        $order = $this->createClosedOrder('ORD-001', [
            'tracking_number' => 'VTP123',
            'shipping_provider' => 'viettel_post',
        ]);

        $matcher = app(CarrierSettlementMatcher::class);

        $byTracking = $matcher->match('viettel_post', 'VTP123', null);
        $this->assertSame($order->id, $byTracking['order']?->id);
        $this->assertSame('tracking_number', $byTracking['method']);

        $byCode = $matcher->match('viettel_post', null, 'ORD-001');
        $this->assertSame($order->id, $byCode['order']?->id);
        $this->assertSame('order_code', $byCode['method']);
    }

    public function test_settlement_ingest_marks_order_settled_when_cod_matches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));
        $order = $this->createClosedOrder('ORD-SETTLED', [
            'tracking_number' => 'GHTK999',
            'shipping_provider' => 'ghtk',
            'amount_to_collect' => 500_000,
            'delivery_status' => 'delivered',
        ]);

        app(CarrierSettlementSyncService::class)->ingestBatch(
            'ghtk',
            CarrierSettlementBatch::SOURCE_IMPORT,
            'stmt-june-1',
            [[
                'tracking_number' => 'GHTK999',
                'partner_order_code' => 'ORD-SETTLED',
                'cod_amount' => 500_000,
            ]],
        );

        $order->refresh();
        $this->assertSame(ReconciliationStatus::Settled->value, $order->reconciliation_status);
        $this->assertSame(500_000, (int) $order->settled_cod_amount);
        $this->assertNotNull($order->settlement_matched_at);
    }

    public function test_settlement_ingest_marks_short_paid_when_carrier_pays_less(): void
    {
        $order = $this->createClosedOrder('ORD-SHORT', [
            'tracking_number' => 'VTP555',
            'shipping_provider' => 'viettel_post',
            'amount_to_collect' => 1_000_000,
            'delivery_status' => 'delivered',
        ]);

        app(CarrierSettlementSyncService::class)->ingestBatch(
            'viettel_post',
            CarrierSettlementBatch::SOURCE_IMPORT,
            'stmt-short',
            [[
                'tracking_number' => 'VTP555',
                'cod_amount' => 400_000,
            ]],
        );

        $order->refresh();
        $this->assertSame(ReconciliationStatus::ShortPaid->value, $order->reconciliation_status);
        $this->assertSame(400_000, (int) $order->settled_cod_amount);
    }

    public function test_returned_order_gets_returned_reconciliation_status(): void
    {
        $order = $this->createClosedOrder('ORD-RET', [
            'delivery_status' => 'returned',
            'amount_to_collect' => 300_000,
        ]);

        app(ShipmentReconciliationEngine::class)->reconcileOrder($order->fresh(['settlementLines']));

        $this->assertSame(ReconciliationStatus::Returned->value, $order->fresh()->reconciliation_status);
    }

    public function test_unmatched_settlement_line_has_no_order_id(): void
    {
        app(CarrierSettlementSyncService::class)->ingestBatch(
            'ghtk',
            CarrierSettlementBatch::SOURCE_IMPORT,
            'stmt-unknown',
            [[
                'tracking_number' => 'UNKNOWN-TRACK',
                'cod_amount' => 200_000,
            ]],
        );

        $line = CarrierSettlementLine::query()->where('tracking_number', 'UNKNOWN-TRACK')->first();
        $this->assertNotNull($line);
        $this->assertNull($line->order_id);
        $this->assertSame(CarrierSettlementLine::MATCH_UNMATCHED, $line->match_status);
    }

    public function test_webhook_delivered_does_not_auto_reconcile_without_settlement(): void
    {
        $order = $this->createClosedOrder('ORD-DEL', [
            'tracking_number' => 'GHTK111',
            'amount_to_collect' => 250_000,
        ]);

        app(ShippingWebhookService::class)->process('ghtk', [
            'label' => 'GHTK111',
            'status_id' => 6,
            'status_text' => 'Đã giao hàng',
            'cod' => 250_000,
        ]);

        $order->refresh();
        $this->assertSame('delivered', $order->delivery_status);
        $this->assertNotSame(ReconciliationStatus::Settled->value, $order->reconciliation_status);
        $this->assertNotSame(ReconciliationStatus::Reconciled->value, $order->reconciliation_status);
    }

    public function test_webhook_paid_creates_settlement_and_settles_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00'));
        $order = $this->createClosedOrder('ORD-PAID', [
            'tracking_number' => 'GHTK222',
            'shipping_provider' => 'ghtk',
            'amount_to_collect' => 600_000,
        ]);

        app(ShippingWebhookService::class)->process('ghtk', [
            'label' => 'GHTK222',
            'status_text' => 'paid',
            'cod' => 600_000,
        ]);

        $order->refresh();
        $this->assertSame(ReconciliationStatus::Settled->value, $order->reconciliation_status);
        $this->assertTrue(
            CarrierSettlementLine::query()->where('order_id', $order->id)->exists()
        );
    }

    public function test_webhook_cod_mismatch_flags_order(): void
    {
        $order = $this->createClosedOrder('ORD-MIS', [
            'tracking_number' => 'GHTK333',
            'amount_to_collect' => 500_000,
        ]);

        app(ShippingWebhookService::class)->process('ghtk', [
            'label' => 'GHTK333',
            'status_text' => 'delivering',
            'cod' => 100_000,
        ]);

        $order->refresh();
        $this->assertSame(ReconciliationStatus::Mismatch->value, $order->reconciliation_status);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createClosedOrder(string $code, array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'order_code' => $code,
            'customer_name' => 'Test Customer',
            'customer_phone' => '0901234567',
            'delivery_status' => 'waiting_waybill',
            'reconciliation_status' => 'pending',
            'amount_to_collect' => 0,
            'total' => 500_000,
            'closed_at' => now(),
            'data_arrived_at' => now(),
        ], $attributes));
    }
}
