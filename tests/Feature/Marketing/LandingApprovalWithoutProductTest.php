<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\LandingConnectionSource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingApprovalWithoutProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_approve_landing_connection_without_products(): void
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
            ->assertSessionHasErrors('product_ids');

        $connection->refresh();
        $this->assertFalse((bool) $connection->is_approved);
        $this->assertSame(0, $connection->products()->count());
    }

    public function test_admin_can_approve_landing_connection_with_products(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $product = Product::query()->create([
            'company_id' => $admin->company_id,
            'name' => 'SP duyệt landing',
            'sku' => 'LAND-APP-001',
            'unit' => 'chai',
            'unit_price' => 100_000,
            'cost_price' => 50_000,
            'is_active' => true,
            'available_marketing' => true,
            'type' => 'product',
        ]);

        $connection = LandingConnection::query()->create([
            'company_id' => $admin->company_id,
            'name' => 'Landing approve with product',
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
            'source_url' => 'https://landing.example.test/with-product',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post("/admin/marketing/landing-approvals/{$connection->id}/approve", [
                'product_ids' => [$product->id],
                'budget_type' => 'total',
                'budget_amount' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $connection->refresh();
        $this->assertTrue((bool) $connection->is_approved);
        $this->assertSame(1, $connection->products()->count());
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

        $listed = LandingConnection::query()
            ->withoutGlobalScopes()
            ->whereKey($connection->id)
            ->first();
        $this->assertNotNull($listed);
        $this->assertNotEmpty(data_get($listed->metadata, 'rejected_at'));
    }
}
