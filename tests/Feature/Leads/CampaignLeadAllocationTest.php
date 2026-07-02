<?php

namespace Tests\Feature\Leads;

use App\Enums\CampaignLeadAllocation;
use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLeadAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_manual_allocation_keeps_lead_in_pool_when_global_auto(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);

        $campaign = MarketingSource::query()->create([
            'name' => 'Manual Pool Campaign',
            'utm_campaign' => 'manual-pool',
            'webhook_token' => 'manualpool1234567890123456789012',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
            'lead_allocation' => CampaignLeadAllocation::Manual,
        ]);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-manual-campaign',
            'name' => 'Khách chia tay',
            'phone' => '0905555666',
        ])->assertAccepted();

        $this->assertDatabaseHas('lead_ingestions', [
            'external_id' => 'lp-manual-campaign',
            'marketing_source_id' => $campaign->id,
            'status' => LeadIngestionStatus::Pending->value,
            'order_id' => null,
        ]);
        $this->assertNull(Order::query()->where('customer_phone', '0905555666')->first());
    }

    public function test_campaign_auto_allocation_assigns_even_when_global_manual(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Manual);

        $campaign = MarketingSource::query()->create([
            'name' => 'Force Auto Campaign',
            'utm_campaign' => 'force-auto',
            'webhook_token' => 'forceauto123456789012345678901',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
            'lead_allocation' => CampaignLeadAllocation::Auto,
        ]);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-force-auto',
            'name' => 'Khách auto',
            'phone' => '0907777888',
        ])->assertAccepted();

        $order = Order::query()->where('customer_phone', '0907777888')->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->sale_user_id);
        $this->assertSame($campaign->id, $order->marketing_source_id);
    }
}
