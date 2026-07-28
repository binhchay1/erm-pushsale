<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingApprovalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_orphan_marketer_connection_on_approval_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $orphan = User::factory()->create(['role' => UserRole::Marketing, 'team_id' => null]);
        $connection = $this->createConnection($orphan, 'Orphan landing');

        $this->actingAs($admin)
            ->get('/admin/marketing/landing-approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Marketing/LandingApprovalPage')
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $connection->id)
                ->where('canApprove', true)
            );
    }

    public function test_orphan_marketer_connection_hidden_from_team_lead_and_self_on_approval_page(): void
    {
        $team = Team::query()->create(['name' => 'MKT Team A', 'type' => 'marketing']);
        $leader = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => $team->id,
            'is_team_leader' => true,
        ]);
        $orphan = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => null,
            'company_id' => $leader->company_id,
        ]);

        $this->createConnection($orphan, 'Orphan pending');

        $this->actingAs($leader)
            ->get('/admin/marketing/landing-approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 0));

        $this->actingAs($orphan)
            ->get('/admin/marketing/landing-approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 0));
    }

    public function test_team_lead_sees_team_member_connection_on_approval_page(): void
    {
        $team = Team::query()->create(['name' => 'MKT Team B', 'type' => 'marketing']);
        $leader = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => $team->id,
            'is_team_leader' => true,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => $team->id,
            'manager_user_id' => $leader->id,
            'company_id' => $leader->company_id,
        ]);

        $connection = $this->createConnection($member, 'Team member landing');

        $this->actingAs($leader)
            ->get('/admin/marketing/landing-approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $connection->id)
                ->where('canApprove', true)
            );
    }

    public function test_team_lead_cannot_approve_orphan_marketer_connection(): void
    {
        $team = Team::query()->create(['name' => 'MKT Team C', 'type' => 'marketing']);
        $leader = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => $team->id,
            'is_team_leader' => true,
        ]);
        $orphan = User::factory()->create([
            'role' => UserRole::Marketing,
            'team_id' => null,
            'company_id' => $leader->company_id,
        ]);
        $product = Product::query()->create([
            'name' => 'SP orphan',
            'sku' => 'SKU-ORPHAN',
            'unit_price' => 100000,
            'is_active' => true,
            'available_marketing' => true,
            'company_id' => $leader->company_id,
        ]);

        $connection = $this->createConnection($orphan, 'Orphan approve block');

        $this->actingAs($leader)
            ->post("/admin/marketing/landing-approvals/{$connection->id}/approve", [
                'product_ids' => [$product->id],
            ])
            ->assertForbidden();
    }

    private function createConnection(User $marketer, string $name): LandingConnection
    {
        return LandingConnection::query()->create([
            'company_id' => $marketer->company_id,
            'name' => $name,
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'google_ads',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => false,
            'is_active' => true,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
        ]);
    }
}
