<?php

namespace Tests\Feature\Leads;

use App\Enums\CampaignLeadAllocation;
use App\Enums\LeadAllocationMode;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLandingComboUpsellTest extends TestCase
{
    use RefreshDatabase;

    private function autoCampaign(): MarketingSource
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
}
