<?php

namespace Tests\Feature\Leads;

use App\Enums\CampaignLeadAllocation;
use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Jobs\Leads\FinalizeLandingSupplementPacketJob;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignLandingComboUpsellTest extends TestCase
{
    use RefreshDatabase;

    private function autoCampaign(bool $jsTracking = false): MarketingSource
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);

        return MarketingSource::query()->create([
            'name' => 'Combo Campaign',
            'utm_campaign' => 'combo-camp',
            'webhook_token' => 'combocamp1234567890123456789012',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
            'js_tracking_enabled' => $jsTracking,
            'lead_allocation' => CampaignLeadAllocation::Auto,
        ]);
    }

    public function test_landing_webhook_saves_address_combo_and_discount(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-combo-1',
            'name' => 'Chị Hồng',
            'phone' => '0905123456',
            'address' => '123 Đường Demo, Quận 1',
            'combo' => 'Mua 2 Thỏi : 289k + Miễn Ship (Bán Chạy)',
            'discount' => '20k',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0905123456')->with('items')->first();

        $this->assertNotNull($order);
        $this->assertSame('123 Đường Demo, Quận 1', $order->shipping_address);
        $this->assertSame(20_000, (int) $order->discount);

        $this->assertCount(1, $order->items);
        $combo = $order->items->first();
        $this->assertSame('combo', $combo->item_type);
        $this->assertSame(289_000, (int) $combo->unit_price);
        $this->assertSame(2, (int) ($combo->meta['detected_qty'] ?? 0));

        // Giá trị cuối đơn = 289.000 − 20.000.
        $this->assertSame(289_000, (int) $order->subtotal);
        $this->assertSame(269_000, (int) $order->total);
        $this->assertSame(269_000, $order->effectiveRevenue());
    }

    public function test_landing_webhook_captures_vietnamese_labeled_address(): void
    {
        $campaign = $this->autoCampaign();

        // Ladipage gửi field theo nhãn tiếng Việt có dấu ("Địa chỉ nhận hàng").
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-vn-addr-1',
            'fields' => [
                ['name' => 'Họ và tên', 'value' => 'Chị Mai'],
                ['name' => 'Số điện thoại', 'value' => '0905999888'],
                ['name' => 'Địa chỉ nhận hàng', 'value' => '45 Lê Lợi, Hải Châu, Đà Nẵng'],
            ],
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0905999888')->first();

        $this->assertNotNull($order);
        $this->assertSame('45 Lê Lợi, Hải Châu, Đà Nẵng', $order->shipping_address);
    }

    public function test_thankyou_upsell_appends_to_existing_order_without_duplicate(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-base-1',
            'name' => 'Anh Nam',
            'phone' => '0906222333',
            'combo' => 'Mua 1 Thỏi : 149k',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906222333')->first();
        $this->assertNotNull($order);
        $this->assertSame(149_000, (int) $order->total);
        $ordersBefore = Order::query()->count();

        // Trang cảm ơn: khách mua thêm → cộng vào ĐƠN CŨ, không tạo đơn mới.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'lp-upsell-1',
            'phone' => '0906222333',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
        ])->assertAccepted();

        $this->assertSame($ordersBefore, Order::query()->count());

        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
        $upsell = $order->items->firstWhere('item_type', 'upsell');
        $this->assertNotNull($upsell);
        $this->assertSame(89_000, (int) $upsell->unit_price);

        // Giá trị cuối đơn cập nhật: 149.000 + 89.000.
        $this->assertSame(238_000, (int) $order->total);

        $packets = LeadIngestion::query()->where('customer_phone', '0906222333')->orderBy('id')->get();
        $this->assertCount(2, $packets);
        $this->assertTrue($packets->first()->counts_as_lead);
        $this->assertFalse($packets->last()->counts_as_lead);
        $this->assertSame(LeadPacketType::Upsell, $packets->last()->packet_type);
        $this->assertSame($packets->first()->id, $packets->last()->parent_ingestion_id);
        $this->assertSame($order->id, $packets->last()->order_id);
        $this->assertStringNotContainsString('[Upsale]', (string) $order->customer_note);
    }

    public function test_orphan_upsell_never_creates_or_routes_a_standalone_order(): void
    {
        Queue::fake([FinalizeLandingSupplementPacketJob::class]);
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'lp-orphan-1',
            'name' => 'Khách lạ',
            'phone' => '0907999888',
            'mua_them_1' => 'Mua Thêm 1 Kem Thoa Tay: 79K',
        ])->assertAccepted();

        $this->assertDatabaseMissing('orders', ['customer_phone' => '0907999888']);

        $packet = LeadIngestion::query()->where('customer_phone', '0907999888')->firstOrFail();
        $this->assertFalse($packet->counts_as_lead);
        $this->assertSame(LeadIngestionStatus::Gathering, $packet->status);
        Queue::assertPushed(FinalizeLandingSupplementPacketJob::class);

        // Hết cửa sổ chờ form chính: chuyển hàng cần kiểm tra, vẫn không tạo/chia đơn.
        $packet->forceFill(['created_at' => now()->subSeconds(95)])->save();
        app(LeadIngestionService::class)->resolvePendingSupplementPacket($packet->fresh());

        $packet->refresh();
        $this->assertSame(LeadIngestionStatus::NeedsReview, $packet->status);
        $this->assertSame(LeadPacketType::OrphanUpsell, $packet->packet_type);
        $this->assertTrue($packet->requires_review);
        $this->assertDatabaseMissing('orders', ['customer_phone' => '0907999888']);
    }

    public function test_two_receive_packets_same_phone_merge_into_single_order(): void
    {
        $campaign = $this->autoCampaign();

        // Gói 1: form đặt hàng.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'dup-1',
            'name' => 'Chị Dup',
            'phone' => '0905777888',
            'combo' => 'Mua 1 Thỏi : 149k',
        ])->assertAccepted();

        // Gói 2: cùng khách bắn lại vào /receive (Ladipage chỉ cấu hình 1 URL) → phải GỘP.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'dup-2',
            'phone' => '0905777888',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
        ])->assertAccepted();

        $orders = Order::query()->where('customer_phone', '0905777888')->get();
        $this->assertCount(1, $orders, 'Cùng 1 khách phải chỉ có 1 đơn (không trùng số).');

        $order = $orders->first()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame(238_000, (int) $order->total);

        $packets = LeadIngestion::query()->where('customer_phone', '0905777888')->orderBy('id')->get();
        $this->assertCount(2, $packets);
        $this->assertSame(1, $packets->where('counts_as_lead', true)->count());
        $this->assertSame(LeadPacketType::FollowUp, $packets->last()->packet_type);
        $this->assertSame($order->id, $packets->last()->order_id);
    }

    public function test_non_js_campaign_also_holds_lead_for_upsell_window(): void
    {
        Queue::fake([FinalizeLandingLeadJob::class]);

        $campaign = $this->autoCampaign(jsTracking: false);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'nojs-hold-1',
            'name' => 'Anh Chờ',
            'phone' => '0906555000',
            'combo' => 'Mua 1 Thỏi : 149k',
        ])->assertAccepted();

        // Chia số ngay; đơn giữ cửa sổ upsale (không chờ job mới có data).
        $order = Order::query()->where('customer_phone', '0906555000')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->isAwaitingLandingUpsell());

        $lead = LeadIngestion::query()->where('customer_phone', '0906555000')->firstOrFail();
        $this->assertSame(LeadIngestionStatus::Processed, $lead->status);
        $this->assertSame($order->id, $lead->order_id);
        Queue::assertPushed(FinalizeLandingLeadJob::class);
    }

    public function test_duplicate_lead_stores_reason_and_conflict_order(): void
    {
        $campaign = $this->autoCampaign();

        // Đơn đầu của khách.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'dupe-base-1',
            'name' => 'Chị Trùng',
            'phone' => '0906666111',
            'combo' => 'Mua 1 Thỏi : 149k',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906666111')->firstOrFail();
        // Đẩy đơn ra ngoài cửa sổ gộp → gói tin sau KHÔNG tự gộp được.
        $order->forceFill(['created_at' => now()->subMinutes(60)])->save();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'dupe-late-1',
            'name' => 'Chị Trùng',
            'phone' => '0906666111',
            'combo' => 'Mua 2 Thỏi : 289k',
        ])->assertAccepted();

        // Case ngoại lệ được LƯU lại kèm lý do + đơn liên quan để kiểm soát.
        $dup = LeadIngestion::query()
            ->where('customer_phone', '0906666111')
            ->where('status', LeadIngestionStatus::Duplicate)
            ->latest('id')
            ->first();

        $this->assertNotNull($dup, 'Gói tin trùng phải được lưu lại để kiểm soát.');
        $this->assertNotEmpty($dup->error_message);
        $this->assertSame($order->order_code, $dup->payload['conflict_order_code'] ?? null);
    }

    public function test_js_tracking_holds_lead_then_finalizes_as_single_order(): void
    {
        Queue::fake([FinalizeLandingLeadJob::class]);

        $campaign = $this->autoCampaign(jsTracking: true);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'hold-1',
            'name' => 'Anh Hold',
            'phone' => '0906111222',
            'combo' => 'Mua 1 Thỏi : 149k',
            'session_id' => 'sess-hold-abc',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906111222')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->isAwaitingLandingUpsell());

        $lead = LeadIngestion::query()->where('customer_phone', '0906111222')->firstOrFail();
        $this->assertSame(LeadIngestionStatus::Processed, $lead->status);
        Queue::assertPushed(FinalizeLandingLeadJob::class);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'hold-2',
            'phone' => '0906111222',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
            'session_id' => 'sess-hold-abc',
        ])->assertAccepted();

        $this->assertSame(1, Order::query()->where('customer_phone', '0906111222')->count());
        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame(238_000, (int) $order->total);

        app(LeadIngestionService::class)->releaseLandingUpsellHold($lead->fresh());

        $order->refresh();
        $this->assertFalse($order->isAwaitingLandingUpsell());
        $this->assertNotNull($order->sale_user_id);
    }

    public function test_session_cannot_bypass_expired_90_second_merge_window(): void
    {
        $campaign = $this->autoCampaign();
        $sessionId = 'sess-expired-upsell-'.uniqid();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'base-session-expired-1',
            'name' => 'Anh Session',
            'phone' => '0906333444',
            'combo' => 'Mua 1 Thỏi : 149k',
            'session_id' => $sessionId,
            'saleops_client_ref' => 'cref-session-expired',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906333444')->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subSeconds(120),
            'landing_upsell_hold_until' => now()->subSecond(),
        ])->save();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'upsell-session-expired-1',
            'phone' => '0906333444',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
            'session_id' => $sessionId,
        ])->assertAccepted();

        $order->refresh()->load('items');
        $this->assertCount(1, $order->items, 'Hết 90 giây thì session cũ không được gộp thêm hàng.');
        $this->assertSame(149_000, (int) $order->total);
        $this->assertSame(1, Order::query()->where('customer_phone', '0906333444')->count());

        $late = LeadIngestion::query()
            ->where('customer_phone', '0906333444')
            ->where('counts_as_lead', false)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(LeadIngestionStatus::NeedsReview, $late->status);
        $this->assertSame(LeadPacketType::LateUpsell, $late->packet_type);
        $this->assertSame($order->id, $late->related_order_id);
        $this->assertSame($order->sale_user_id, $late->relatedOrder?->sale_user_id);
        $this->assertTrue($late->requires_review);
    }

    public function test_cross_domain_upsell_can_merge_by_client_ref_without_phone(): void
    {
        $campaign = $this->autoCampaign();
        $clientRef = 'cref-cross-domain-'.uniqid();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'base-cref-cross-domain-1',
            'name' => 'Chị Ref',
            'phone' => '0906444555',
            'combo' => 'Mua 1 Thỏi : 149k',
            'saleops_client_ref' => $clientRef,
        ])->assertAccepted();

        // Trang cảm ơn khác domain chỉ cần opaque client-ref; không phải lộ SĐT trên URL.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'upsell-cref-cross-domain-1',
            'parent_submission_id' => $clientRef,
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
        ])->assertAccepted();

        $this->assertSame(1, Order::query()->where('customer_phone', '0906444555')->count());
        $order = Order::query()->where('customer_phone', '0906444555')->with('items')->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(238_000, (int) $order->total);
    }

    public function test_landing_merge_window_defaults_to_90_seconds(): void
    {
        $this->assertSame(90, app(\App\Services\Leads\LandingUpsellService::class)->holdSeconds());
        $this->assertSame(90, app(\App\Services\Leads\LandingUpsellService::class)->maxHoldSeconds());
    }

    public function test_sale_edit_locks_order_from_landing_upsell_merge(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lock-base-1',
            'name' => 'Chị Khóa',
            'phone' => '0907888999',
            'combo' => 'Mua 1 Thỏi : 149k',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0907888999')->firstOrFail();
        $this->assertTrue($order->isAwaitingLandingUpsell());

        $sale = User::query()->findOrFail($order->sale_user_id);
        app(\App\Services\Operations\SaleOperationStatusService::class)->logCall($order, $sale);

        $order->refresh();
        $this->assertFalse($order->isAwaitingLandingUpsell());
        $this->assertTrue($order->isLandingUpsellLocked());

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'lock-upsell-1',
            'phone' => '0907888999',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
        ])->assertAccepted();

        $order->refresh()->load('items');
        $this->assertCount(1, $order->items);
        $this->assertSame(149_000, (int) $order->total);
    }
    public function test_main_and_upsell_can_share_same_submission_id_without_losing_upsell(): void
    {
        $campaign = $this->autoCampaign();
        $sameReference = 'autofunnel-reference-1';

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => $sameReference,
            'name' => 'Chị Auto Funnel',
            'phone' => '0906123123',
            'combo' => 'Mua 1 Thỏi : 149k',
            'saleops_client_ref' => 'client-auto-funnel-1',
        ])->assertAccepted();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => $sameReference,
            'phone' => '0906123123',
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906123123')->with('items')->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(218_000, (int) $order->total);
        $this->assertDatabaseHas('lead_ingestions', ['external_id' => $sameReference]);
        $this->assertDatabaseHas('lead_ingestions', ['external_id' => $sameReference.':upsell']);
    }

    public function test_upsell_without_phone_or_reference_is_rejected(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'orphan-no-identity',
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ])->assertUnprocessable();
    }

    public function test_session_close_does_not_shorten_the_90_second_order_window(): void
    {
        Queue::fake([FinalizeLandingLeadJob::class]);
        $campaign = $this->autoCampaign(jsTracking: true);
        $sessionId = 'session-close-does-not-shortcut';

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'base-close-window',
            'name' => 'Anh Window',
            'phone' => '0906767676',
            'combo' => 'Mua 1 Thỏi : 149k',
            'session_id' => $sessionId,
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906767676')->firstOrFail();
        $deadline = $order->landing_upsell_hold_until;

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/session/close', [
            'session_id' => $sessionId,
        ])->assertAccepted();

        $order->refresh();
        $this->assertTrue($order->isAwaitingLandingUpsell());
        $this->assertTrue($order->landing_upsell_hold_until->equalTo($deadline));
    }

    public function test_landing_parses_shipping_fee_and_generic_thankyou_product(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'shipping-base-1',
            'name' => 'Chị Ship',
            'phone' => '0906989898',
            'combo' => 'Mua 1 Thỏi : 149k + 30k Ship',
            'saleops_client_ref' => 'shipping-client-ref-1',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906989898')->firstOrFail();
        $this->assertSame(30_000, (int) $order->shipping_fee_collected);
        $this->assertSame(179_000, (int) $order->amount_to_collect);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'shipping-upsell-1',
            'parent_submission_id' => 'shipping-client-ref-1',
            'product' => '1 hộp Bàn Chải (8 chiếc): 69k',
            'item_type' => 'upsell',
            'is_upsell' => '1',
        ])->assertAccepted();

        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertNotNull($order->items->firstWhere('item_type', 'upsell'));
        $this->assertSame(248_000, (int) $order->amount_to_collect);
    }

}
