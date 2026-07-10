<?php

namespace Tests\Feature\Leads;

use App\Enums\ClosingStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Http\Middleware\SetTenant;
use App\Services\Leads\LeadSupplementReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LandingSupplementReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_merge_late_upsell_into_still_editable_original_order(): void
    {
        [$admin, $sale, $source, $order, $packet] = $this->fixtures();

        $service = app(LeadSupplementReviewService::class);
        $service->resolve($packet, $admin, LeadSupplementReviewService::MERGE_ORIGINAL);

        $order->refresh()->load('items');
        $packet->refresh();

        $this->assertCount(2, $order->items);
        $this->assertSame(169_000, (int) $order->total);
        $this->assertSame($order->id, $packet->order_id);
        $this->assertNull($packet->related_order_id);
        $this->assertSame(LeadPacketType::Upsell, $packet->packet_type);
        $this->assertSame(LeadIngestionStatus::Processed, $packet->status);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertFalse($packet->requires_review);
        $this->assertSame(LeadSupplementReviewService::MERGE_ORIGINAL, $packet->review_resolution);
        $this->assertNotNull($packet->reviewed_at);
        $this->assertSame($admin->id, $packet->reviewed_by_user_id);
        $this->assertSame($sale->id, $order->sale_user_id);
    }

    public function test_closed_order_cannot_be_mutated_and_can_create_supplemental_order_for_same_sale(): void
    {
        [$admin, $sale, $source, $order, $packet] = $this->fixtures();
        $order->forceFill([
            'closing_status' => ClosingStatus::Closed->value,
            'closed_at' => now(),
        ])->save();

        $service = app(LeadSupplementReviewService::class);

        try {
            $service->resolve($packet, $admin, LeadSupplementReviewService::MERGE_ORIGINAL);
            $this->fail('Closed order must not accept a late upsell merge.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, Order::query()->count());
        $this->assertNull($packet->fresh()->reviewed_at);

        $service->resolve($packet->fresh(), $admin, LeadSupplementReviewService::CREATE_SUPPLEMENTAL_ORDER);

        $packet->refresh();
        $newOrder = Order::query()->whereKey($packet->order_id)->with('items')->firstOrFail();

        $this->assertSame(2, Order::query()->count());
        $this->assertNotSame($order->id, $newOrder->id);
        $this->assertSame($order->id, $packet->related_order_id);
        $this->assertSame($sale->id, $newOrder->sale_user_id);
        $this->assertSame($source->id, $newOrder->marketing_source_id);
        $this->assertTrue($newOrder->is_returning_customer);
        $this->assertCount(1, $newOrder->items);
        $this->assertSame(69_000, (int) $newOrder->total);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertSame(LeadSupplementReviewService::CREATE_SUPPLEMENTAL_ORDER, $packet->review_resolution);
    }

    public function test_review_permissions_follow_business_ownership_and_roles(): void
    {
        [$admin, $assignedSale, , $order, $packet] = $this->fixtures();
        $service = app(LeadSupplementReviewService::class);

        $unrelatedSale = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = User::factory()->create(['role' => UserRole::Warehouse]);
        $allocator = User::factory()->create(['role' => UserRole::Allocator]);

        $this->assertTrue($service->canReview($admin, $packet, $order));
        $this->assertTrue($service->canReview($allocator, $packet, $order));
        $this->assertTrue($service->canReview($assignedSale, $packet, $order));
        $this->assertFalse($service->canReview($unrelatedSale, $packet, $order));
        $this->assertFalse($service->canReview($warehouse, $packet, $order));
    }

    public function test_supplemental_order_keeps_explicit_link_to_original_order(): void
    {
        [$admin, , , $order, $packet] = $this->fixtures();
        $order->forceFill([
            'closing_status' => ClosingStatus::Closed->value,
            'closed_at' => now(),
        ])->save();

        app(LeadSupplementReviewService::class)->resolve(
            $packet,
            $admin,
            LeadSupplementReviewService::CREATE_SUPPLEMENTAL_ORDER,
        );

        $newOrder = Order::query()->whereKey($packet->fresh()->order_id)->firstOrFail();
        $origin = $newOrder->supplementalOriginPacket()->with('relatedOrder')->firstOrFail();

        $this->assertSame($order->id, $origin->related_order_id);
        $this->assertSame($order->order_code, $origin->relatedOrder?->order_code);
        $this->assertFalse($origin->counts_as_lead);
    }

    public function test_review_resolution_is_idempotent(): void
    {
        [$admin, , , $order, $packet] = $this->fixtures();
        $service = app(LeadSupplementReviewService::class);

        $service->resolve($packet, $admin, LeadSupplementReviewService::MERGE_ORIGINAL);
        $service->resolve($packet->fresh(), $admin, LeadSupplementReviewService::MERGE_ORIGINAL);

        $this->assertCount(2, $order->fresh()->items);
    }


    public function test_customer_packet_endpoint_returns_detailed_action_safe_payload(): void
    {
        [$admin, $sale, , $order, $packet] = $this->fixtures();
        $this->withoutMiddleware(SetTenant::class);

        $response = $this->actingAs($admin)->getJson(
            route('customers.orders.supplement-packets.index', $order),
        );

        $response
            ->assertOk()
            ->assertJsonPath('order.order_code', $order->order_code)
            ->assertJsonPath('order.sale_name', $sale->name)
            ->assertJsonPath('order.can_accept_merge', true)
            ->assertJsonPath('summary.pending_count', 1)
            ->assertJsonPath('summary.pending_value', 69_000)
            ->assertJsonPath('packets.0.id', (string) $packet->id)
            ->assertJsonPath('packets.0.item_quantity', 1)
            ->assertJsonPath('packets.0.subtotal', 69_000)
            ->assertJsonPath('packets.0.total', 69_000)
            ->assertJsonPath('packets.0.items.0.line_total', 69_000)
            ->assertJsonPath('packets.0.can_merge_original', true)
            ->assertJsonPath('packets.0.can_create_supplemental_order', true)
            ->assertJsonPath('packets.0.merge_block_reason', null);
    }

    public function test_customer_packet_endpoint_explains_when_original_order_cannot_be_merged(): void
    {
        [$admin, , , $order] = $this->fixtures();
        $order->forceFill([
            'closing_status' => ClosingStatus::Closed->value,
            'closed_at' => now(),
        ])->save();
        $this->withoutMiddleware(SetTenant::class);

        $this->actingAs($admin)
            ->getJson(route('customers.orders.supplement-packets.index', $order))
            ->assertOk()
            ->assertJsonPath('order.can_accept_merge', false)
            ->assertJsonPath('order.merge_block_reason', 'order_closed')
            ->assertJsonPath('packets.0.can_merge_original', false)
            ->assertJsonPath('packets.0.can_create_supplemental_order', true)
            ->assertJsonPath('packets.0.merge_block_reason', 'order_closed');
    }

    public function test_customer_packet_review_endpoint_persists_audit_note_and_returns_pending_count(): void
    {
        [$admin, , , $order, $packet] = $this->fixtures();
        $this->withoutMiddleware(SetTenant::class);

        $this->actingAs($admin)
            ->postJson(
                route('customers.orders.supplement-packets.review', [$order, $packet]),
                [
                    'resolution' => LeadSupplementReviewService::ACKNOWLEDGE,
                    'note' => 'Khách xác nhận không lấy thêm sản phẩm.',
                ],
            )
            ->assertOk()
            ->assertJsonPath('pending_count', 0)
            ->assertJsonPath('packet.review_resolution', LeadSupplementReviewService::ACKNOWLEDGE)
            ->assertJsonPath('packet.review_note', 'Khách xác nhận không lấy thêm sản phẩm.')
            ->assertJsonPath('packet.requires_review', false);

        $packet->refresh();
        $this->assertSame('Khách xác nhận không lấy thêm sản phẩm.', $packet->review_note);
        $this->assertNotNull($packet->reviewed_at);
    }

    /** @return array{User, User, MarketingSource, Order, LeadIngestion} */
    private function fixtures(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        $source = MarketingSource::query()->create([
            'name' => 'Late upsell review source',
            'utm_source' => 'landing',
            'utm_campaign' => 'late-review',
            'webhook_token' => 'late-review-token-1234567890',
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $order = Order::query()->create([
            'order_code' => 'PS-LATE-ORIGINAL',
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $source->id,
            'customer_name' => 'Khách late upsell',
            'customer_phone' => '0909123456',
            'shipping_address' => '12 Phố Huế, Hà Nội',
            'data_arrived_at' => now()->subMinutes(2),
            'assigned_at' => now()->subMinutes(2),
            'subtotal' => 100_000,
            'total' => 100_000,
            'amount_to_collect' => 100_000,
        ]);
        $order->items()->create([
            'product_name' => 'Sản phẩm chính',
            'item_type' => 'combo',
            'origin' => 'landing',
            'quantity' => 1,
            'unit_price' => 100_000,
        ]);

        $primary = LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'late-review-base',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'utm_source' => 'landing',
            'utm_campaign' => 'late-review',
            'marketing_source_id' => $source->id,
            'payload' => ['items' => []],
            'order_id' => $order->id,
            'processed_at' => now(),
        ]);

        $packet = LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'late-review-upsell:upsell',
            'status' => LeadIngestionStatus::NeedsReview,
            'packet_type' => LeadPacketType::LateUpsell,
            'counts_as_lead' => false,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'utm_source' => 'landing',
            'utm_campaign' => 'late-review',
            'marketing_source_id' => $source->id,
            'payload' => [
                'items' => [[
                    'product_name' => 'Bàn chải upsell',
                    'item_type' => 'upsell',
                    'quantity' => 1,
                    'unit_price' => 69_000,
                ]],
            ],
            'parent_ingestion_id' => $primary->id,
            'related_order_id' => $order->id,
            'requires_review' => true,
            'error_message' => 'Late upsell review',
            'processed_at' => now(),
        ]);

        return [$admin, $sale, $source, $order, $packet];
    }
}
