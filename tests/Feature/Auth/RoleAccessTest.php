<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->assertRoleCanAccess(UserRole::Admin, '/admin/dashboard');
    }

    public function test_sales_can_access_sales_workspace(): void
    {
        $this->assertRoleCanAccess(UserRole::Sales, '/sales/workspace');
    }

    public function test_marketing_can_access_marketing_workspace(): void
    {
        $this->assertRoleCanAccess(UserRole::Marketing, '/marketing/workspace');
    }

    public function test_warehouse_can_access_warehouse_workspace(): void
    {
        $this->assertRoleCanAccess(UserRole::Warehouse, '/warehouse/workspace');
    }

    public function test_sales_cannot_access_admin_dashboard(): void
    {
        $this->assertRoleCannotAccess(UserRole::Sales, '/admin/dashboard');
    }

    public function test_marketing_cannot_access_sales_workspace(): void
    {
        $this->assertRoleCannotAccess(UserRole::Marketing, '/sales/workspace');
    }

    public function test_warehouse_cannot_access_marketing_workspace(): void
    {
        $this->assertRoleCannotAccess(UserRole::Warehouse, '/marketing/workspace');
    }

    public function test_sales_cannot_access_warehouse_workspace(): void
    {
        $this->assertRoleCannotAccess(UserRole::Sales, '/warehouse/workspace');
    }

    private function assertRoleCanAccess(UserRole $role, string $path): void
    {
        $user = User::factory()->create([
            'role' => $role,
        ]);

        $this->actingAs($user)
            ->get($path)
            ->assertOk();
    }

    private function assertRoleCannotAccess(UserRole $role, string $path): void
    {
        $user = User::factory()->create([
            'role' => $role,
        ]);

        $this->actingAs($user)
            ->get($path)
            ->assertForbidden();
    }
}
