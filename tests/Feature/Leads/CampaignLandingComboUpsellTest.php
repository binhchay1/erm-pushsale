<?php

namespace Tests\Feature\Leads;

use App\Enums\CampaignLeadAllocation;
use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Jobs\Leads\FinalizeLandingLeadJob;
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
    }

    public function test_upsell_creates_new_lead_when_no_prior_order(): void
    {
        $campaign = $this->autoCampaign();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'lp-orphan-1',
            'name' => 'Khách lạ',
            'phone' => '0907999888',
            'mua_them_1' => 'Mua Thêm 1 Kem Thoa Tay: 79K',
        ])->assertAccepted();

        // Không có đơn gốc → xử lý như lead thường (tạo đơn mới).
        $order = Order::query()->where('customer_phone', '0907999888')->first();
        $this->assertNotNull($order);
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

    public function test_upsell_merges_via_session_after_grouping_window(): void
    {
        $campaign = $this->autoCampaign();
        $sessionId = 'sess-late-upsell-'.uniqid();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'base-session-1',
            'name' => 'Anh Session',
            'phone' => '0906333444',
            'combo' => 'Mua 1 Thỏi : 149k',
            'session_id' => $sessionId,
            'saleops_client_ref' => 'cref-session-abc',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906333444')->firstOrFail();
        $order->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'upsell-session-1',
            'phone' => '0906333444',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
            'session_id' => $sessionId,
        ])->assertAccepted();

        $this->assertSame(1, Order::query()->where('customer_phone', '0906333444')->count());
        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame(238_000, (int) $order->total);
    }

    public function test_upsell_merges_via_client_ref_after_grouping_window(): void
    {
        $campaign = $this->autoCampaign();
        $clientRef = 'cref-late-'.uniqid();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'base-cref-1',
            'name' => 'Chị Ref',
            'phone' => '0906444555',
            'combo' => 'Mua 1 Thỏi : 149k',
            'saleops_client_ref' => $clientRef,
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0906444555')->firstOrFail();
        $order->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'upsell-cref-1',
            'phone' => '0906444555',
            'parent_submission_id' => $clientRef,
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
        ])->assertAccepted();

        $this->assertSame(1, Order::query()->where('customer_phone', '0906444555')->count());
        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
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
}
