<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingApprovalWithoutProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_landing_connection_without_products(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $connection = LandingConnection::query()->create([
            'company_id' => $admin->company_id,
            'name' => 'Landing approve no product',
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook_ads',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => false,
            'is_active' => true,
        ]);

        LandingConnectionSource::query()->create([
            'company_id' => $admin->company_id,
            'landing_connection_id' => $connection->id,
            'name' => 'Main',
            'source_type' => LandingConnectionSource::TYPE_MAIN,
            'source_url' => 'https://landing.example.test/no-product',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post("/admin/marketing/landing-approvals/{$connection->id}/approve", [
                'product_ids' => [],
                'budget_type' => 'total',
                'budget_amount' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $connection->refresh();
        $this->assertTrue((bool) $connection->is_approved);
        $this->assertSame(0, $connection->products()->count());
        $this->assertNull(data_get($connection->metadata, 'rejected_at'));

        $campaign = MarketingSource::query()->whereKey($connection->marketing_source_id)->first();
        $this->assertNotNull($campaign);
        $this->assertTrue((bool) $campaign->is_approved);
        $this->assertNull($campaign->product_id);
    }

    public function test_reject_stores_status_and_connections_list_exposes_rejected_at(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $connection = LandingConnection::query()->create([
            'company_id' => $admin->company_id,
            'name' => 'Landing reject status',
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook_ads',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => false,
            'is_active' => true,
            'metadata' => ['request_approval' => true],
        ]);

        $this->actingAs($admin)
            ->post("/admin/marketing/landing-approvals/{$connection->id}/reject", [
                'reason' => 'Sai URL nguồn dữ liệu',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertFalse((bool) $connection->is_approved);
        $this->assertNotEmpty(data_get($connection->metadata, 'rejected_at'));
        $this->assertSame('Sai URL nguồn dữ liệu', data_get($connection->metadata, 'rejection_reason'));

        $this->actingAs($admin)
            ->get('/admin/marketing/landing-connections')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Marketing/LandingConnectionsPage')
                ->has('connections.data', 1)
                ->where('connections.data.0.id', $connection->id)
                ->where('connections.data.0.is_approved', false)
                ->where('connections.data.0.products', [])
                ->where('connections.data.0.rejected_at', fn ($value) => filled($value))
            );
    }
}
