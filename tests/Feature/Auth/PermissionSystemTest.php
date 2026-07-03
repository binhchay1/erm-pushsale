<?php

namespace Tests\Feature\Auth;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Users\UserHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $super = User::factory()->create([
            'role' => UserRole::Admin,
            'is_platform_admin' => true,
        ]);

        User::query()->withoutGlobalScope(TenantScope::class)
            ->whereKey($super->id)
            ->update(['company_id' => null]);

        return $super->fresh();
    }

    public function test_super_admin_sees_internal_dashboard_and_platform(): void
    {
        $super = $this->makeSuperAdmin();

        // Chủ project: xem full dữ liệu nội bộ.
        $this->actingAs($super)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Và quản trị nền tảng.
        $this->actingAs($super)
            ->get(route('platform.companies.index'))
            ->assertOk();
    }

    public function test_company_admin_has_full_permissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($admin->allows(PermissionArea::Reports, PermissionLevel::Full));
        $this->assertTrue($admin->allows(PermissionArea::Hr, PermissionLevel::Full));
    }

    public function test_sales_default_permissions(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->assertTrue($sales->allows(PermissionArea::Telesale, PermissionLevel::Full));
        $this->assertTrue($sales->allows(PermissionArea::Reports, PermissionLevel::View));
        $this->assertFalse($sales->allows(PermissionArea::Hr, PermissionLevel::View));
    }

    public function test_custom_permissions_override_defaults(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'permissions' => ['hr' => 'full'],
        ]);

        $this->assertTrue($sales->allows(PermissionArea::Hr, PermissionLevel::Full));
    }

    public function test_head_can_manage_staff_in_same_role(): void
    {
        $hierarchy = app(UserHierarchyService::class);

        $head = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Head,
        ]);
        $staff = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Staff,
            'manager_user_id' => $head->id,
        ]);

        $this->assertTrue($hierarchy->canManage($head, $staff));
        $this->assertFalse($hierarchy->canManage($staff, $head));
    }

    public function test_staff_cannot_manage_head(): void
    {
        $hierarchy = app(UserHierarchyService::class);

        $head = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Head,
        ]);
        $staff = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Staff,
        ]);

        $this->assertFalse($hierarchy->canManage($staff, $head));
    }

    public function test_head_with_hr_permission_can_create_staff(): void
    {
        $head = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Head,
            'permissions' => ['hr' => 'full'],
        ]);

        $this->actingAs($head)
            ->get(route('admin.users.create'))
            ->assertOk();
    }

    public function test_plain_sales_cannot_create_staff(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)
            ->get(route('admin.users.create'))
            ->assertForbidden();
    }
}
