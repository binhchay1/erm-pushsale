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
            '/sales/customers',
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
            ->has('navigation', 1)
            ->where('navigation.0.items.0.url', '/sales/dashboard')
            ->where('navigation.0.items.1.url', '/sales/workspace')
            ->where('navigation.0.items.2.url', '/sales/customers')
            ->where('navigation.0.items.3.url', '/settings')
        );
    }
}
