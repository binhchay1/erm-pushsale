<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_navigation_only_includes_sales_menus(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $navigation = app(NavigationService::class)->forUser($user);

        $urls = collect($navigation)
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('url'))
            ->all();

        $this->assertSame([
            '/sales/dashboard',
            '/sales/workspace',
            '/sales/performance',
            '/sales/reports/sale-1',
            '/sales/rankings',
            '/customers',
            '/org-chart',
            '/settings',
        ], $urls);

        $this->assertNotContains('/admin/dashboard', $urls);
    }

    public function test_admin_navigation_includes_admin_menus(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $navigation = app(NavigationService::class)->forUser($user);

        $urls = collect($navigation)
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('url'))
            ->all();

        $this->assertContains('/admin/dashboard', $urls);
        $this->assertContains('/customers', $urls);
        $this->assertNotContains('/sales/workspace', $urls);
    }

    public function test_inertia_share_includes_role_scoped_navigation_for_sales(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $response = $this->actingAs($user)->get('/sales/workspace');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Sales/Workspace')
            ->where('auth.user.role', 'sales')
            ->has('navigation', 5)
            ->where('navigation.0.label_key', 'operations')
            ->where('navigation.0.items.0.url', '/sales/dashboard')
            ->where('navigation.0.items.1.url', '/sales/workspace')
            ->where('navigation.1.label_key', 'reports_sales')
            ->where('navigation.2.label_key', 'telesale')
            ->where('navigation.2.items.0.url', '/customers')
            ->where('navigation.3.label_key', 'hr_catalog')
            ->where('navigation.3.items.0.url', '/org-chart')
            ->where('navigation.4.label_key', 'platform')
            ->where('navigation.4.items.0.url', '/settings')
        );
    }
    public function test_customer_profile_is_available_in_every_role_navigation_by_default(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $urls = collect(app(NavigationService::class)->forUser($user))
                ->flatMap(fn (array $group) => collect($group['items'])->pluck('url'))
                ->all();

            $this->assertContains('/customers', $urls, "Role {$role->value} is missing customer profile menu.");
        }
    }

}
