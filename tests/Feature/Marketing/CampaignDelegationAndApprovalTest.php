<?php

namespace Tests\Feature\Marketing;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\MarketingSource;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignDelegationAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegated_marketer_sees_campaign_with_delegated_filter(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Marketing]);
        $delegate = User::factory()->create(['role' => UserRole::Marketing]);

        MarketingSource::query()->create([
            'name' => 'Campaign delegated',
            'created_by_user_id' => $creator->id,
            'marketer_user_id' => $delegate->id,
            'utm_campaign' => 'camp-delegated',
            'webhook_token' => 'tok-delegated',
            'budget' => 500000,
            'is_active' => true,
        ]);

        $this->actingAs($delegate)
            ->get('/marketing/campaigns?ownership=delegated')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Campaigns/Index')
                ->has('campaigns', 1)
                ->where('campaigns.0.ownership', 'delegated')
            );

        $this->actingAs($delegate)
            ->get('/marketing/campaigns?ownership=created')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 0));
    }

    public function test_marketing_head_can_approve_team_campaign_but_not_own(): void
    {
        $head = User::factory()->create([
            'role' => UserRole::Marketing,
            'org_level' => OrgLevel::Head,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Marketing,
            'manager_user_id' => $head->id,
        ]);

        $ownCampaign = MarketingSource::query()->create([
            'name' => 'Own campaign',
            'created_by_user_id' => $head->id,
            'marketer_user_id' => $head->id,
            'utm_campaign' => 'camp-own',
            'webhook_token' => 'tok-own',
            'budget' => 100000,
            'is_active' => true,
        ]);

        $teamCampaign = MarketingSource::query()->create([
            'name' => 'Team campaign',
            'created_by_user_id' => $member->id,
            'marketer_user_id' => $member->id,
            'utm_campaign' => 'camp-team',
            'webhook_token' => 'tok-team',
            'budget' => 200000,
            'is_active' => true,
        ]);

        $this->actingAs($head)
            ->post("/marketing/landing-approvals/{$ownCampaign->id}/approve")
            ->assertForbidden();

        $this->actingAs($head)
            ->post("/marketing/landing-approvals/{$teamCampaign->id}/approve")
            ->assertRedirect();

        $teamCampaign->refresh();
        $this->assertTrue($teamCampaign->is_approved);
        $this->assertSame($head->id, $teamCampaign->approved_by_user_id);

        $this->assertDatabaseHas('activity_logs', [
            'action' => ActivityLogger::CAMPAIGN_APPROVED,
            'subject_id' => $teamCampaign->id,
            'user_id' => $head->id,
        ]);
    }

    public function test_approve_without_product_succeeds_when_webhook_exists(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $campaign = MarketingSource::query()->create([
            'name' => 'Campaign no product',
            'product_id' => null,
            'created_by_user_id' => $admin->id,
            'marketer_user_id' => $admin->id,
            'utm_campaign' => 'camp-noproduct',
            'webhook_token' => 'tok-noproduct',
            'budget' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post("/admin/landing-approvals/{$campaign->id}/approve")
            ->assertRedirect();

        $this->assertTrue($campaign->fresh()->is_approved);
    }

    public function test_admin_can_view_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Sales, 'created_by_user_id' => $admin->id]);

        ActivityLog::query()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'action' => ActivityLogger::USER_CREATED,
            'subject_type' => 'user',
            'subject_id' => $target->id,
            'subject_label' => $target->name,
            'properties' => ['role' => 'sales'],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/activity-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ActivityLogs/Index')
                ->has('logs.data', 1)
            );
    }
}
