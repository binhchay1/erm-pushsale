<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRankingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_access_sales_rankings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($user)
            ->get('/sales/rankings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rankings/Index')
                ->where('showDepartmentTabs', false)
                ->where('highlightUserId', $user->id)
                ->has('departments', 1)
                ->has('filters')
                ->has('filterOptions')
                ->where('departments.0.key', 'sales')
            );
    }

    public function test_marketing_can_access_marketing_rankings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);

        $this->actingAs($user)
            ->get('/marketing/rankings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rankings/Index')
                ->where('departments.0.key', 'marketing')
            );
    }

    public function test_sales_cannot_access_admin_rankings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($user)
            ->get('/admin/rankings')
            ->assertForbidden();
    }

    public function test_sales_cannot_access_marketing_rankings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($user)
            ->get('/marketing/rankings')
            ->assertForbidden();
    }
}
