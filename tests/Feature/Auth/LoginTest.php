<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')
            ->assertOk();
    }

    public function test_admin_credentials_redirect_to_admin_dashboard(): void
    {
        $this->assertValidCredentialsRedirectToRoleHome(UserRole::Admin, '/admin/dashboard');
    }

    public function test_sales_credentials_redirect_to_sales_workspace(): void
    {
        $this->assertValidCredentialsRedirectToRoleHome(UserRole::Sales, '/sales/workspace');
    }

    public function test_marketing_credentials_redirect_to_marketing_workspace(): void
    {
        $this->assertValidCredentialsRedirectToRoleHome(UserRole::Marketing, '/marketing/workspace');
    }

    public function test_warehouse_credentials_redirect_to_warehouse_workspace(): void
    {
        $this->assertValidCredentialsRedirectToRoleHome(UserRole::Warehouse, '/warehouse/workspace');
    }

    public function test_invalid_credentials_return_email_validation_error(): void
    {
        User::factory()->create([
            'email' => 'sales@saleops.local',
            'password' => 'password',
            'role' => UserRole::Sales,
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'sales@saleops.local',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_invalidates_auth_and_redirects_to_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Sales,
        ]);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_authenticated_admin_root_redirects_to_admin_dashboard(): void
    {
        $this->assertAuthenticatedRootRedirectsToRoleHome(UserRole::Admin, '/admin/dashboard');
    }

    public function test_authenticated_sales_root_redirects_to_sales_workspace(): void
    {
        $this->assertAuthenticatedRootRedirectsToRoleHome(UserRole::Sales, '/sales/workspace');
    }

    public function test_authenticated_marketing_root_redirects_to_marketing_workspace(): void
    {
        $this->assertAuthenticatedRootRedirectsToRoleHome(UserRole::Marketing, '/marketing/workspace');
    }

    public function test_authenticated_warehouse_root_redirects_to_warehouse_workspace(): void
    {
        $this->assertAuthenticatedRootRedirectsToRoleHome(UserRole::Warehouse, '/warehouse/workspace');
    }

    private function assertValidCredentialsRedirectToRoleHome(UserRole $role, string $home): void
    {
        $user = User::factory()->create([
            'email' => "{$role->value}@saleops.local",
            'password' => 'password',
            'role' => $role,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($home);

        $this->assertAuthenticatedAs($user);
    }

    private function assertAuthenticatedRootRedirectsToRoleHome(UserRole $role, string $home): void
    {
        $user = User::factory()->create([
            'role' => $role,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect($home);
    }
}
