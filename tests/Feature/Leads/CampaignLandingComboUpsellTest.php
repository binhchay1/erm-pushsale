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

    public function test_js_tracking_holds_lead_then_finalizes_as_single_order(): void
    {
        // Chặn job chốt tự chạy để mô phỏng "đang gom" trong lúc chờ upsale.
        Queue::fake([FinalizeLandingLeadJob::class]);

        $campaign = $this->autoCampaign(jsTracking: true);

        // Gói 1: form đầu → lead "đang gom", CHƯA tạo đơn / CHƯA chia số.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'hold-1',
            'name' => 'Anh Hold',
            'phone' => '0906111222',
            'combo' => 'Mua 1 Thỏi : 149k',
            'session_id' => 'sess-hold-abc',
        ])->assertAccepted();

        $this->assertSame(0, Order::query()->where('customer_phone', '0906111222')->count());
        $lead = LeadIngestion::query()->where('customer_phone', '0906111222')->firstOrFail();
        $this->assertSame(LeadIngestionStatus::Gathering, $lead->status);
        Queue::assertPushed(FinalizeLandingLeadJob::class);

        // Gói 2: upsale trang cảm ơn → gộp vào lead đang gom, không tạo đơn mới.
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', [
            'submission_id' => 'hold-2',
            'phone' => '0906111222',
            'mua_them_1' => 'Mua Thêm 1 Má Hồng Kem: 89K',
            'session_id' => 'sess-hold-abc',
        ])->assertAccepted();

        $this->assertSame(0, Order::query()->where('customer_phone', '0906111222')->count());
        $this->assertSame(1, LeadIngestion::query()->where('customer_phone', '0906111222')->count());

        // Khách xong phiên → chốt: đúng 1 đơn đủ 2 dòng hàng, chia 1 số.
        app(LeadIngestionService::class)->finalizeGatheringLead($lead->fresh());

        $orders = Order::query()->where('customer_phone', '0906111222')->get();
        $this->assertCount(1, $orders);
        $order = $orders->first()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame(238_000, (int) $order->total);
        $this->assertNotNull($order->sale_user_id);
    }
}
