<?php

namespace Tests\Feature\Pushsale;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Pushsale\PushsalePageService;
use App\Support\ActivityLogger;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UiContractV17Test extends TestCase
{
    use RefreshDatabase;

    public function test_login_history_options_are_built_from_real_tenant_users(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty kiểm thử',
            'slug' => 'ui-contract-v17',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 20,
        ]);

        $user = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Nhân viên thực tế',
            'email' => 'real-user-v17@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SALES,
        ]);

        app(TenantManager::class)->set($company->id);

        ActivityLog::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'action' => ActivityLogger::AUTH_LOGIN_SUCCESS,
            'subject_label' => $user->email,
            'properties' => ['status' => 'success'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);

        $options = app(PushsalePageService::class)->filterOptions('1.7.1');
        $loginUser = collect($options['loginUsers'])->firstWhere('id', $user->id);

        $this->assertNotNull($loginUser);
        $this->assertSame('Nhân viên thực tế', $loginUser['name']);
        $this->assertSame(1, $loginUser['login_count']);
        $this->assertContains(User::ROLE_SALES, collect($options['roles'])->pluck('id')->all());
        $this->assertContains($company->id, collect($options['companies'])->pluck('id')->all());
        app(TenantManager::class)->clear();
    }

    public function test_successful_login_creates_a_real_login_audit_record(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty đăng nhập',
            'slug' => 'login-audit-v17',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 20,
        ]);

        $user = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Tài khoản đăng nhập',
            'email' => 'login-v17@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'action' => ActivityLogger::AUTH_LOGIN_SUCCESS,
        ]);
    }

    public function test_public_and_internal_css_entries_are_isolated(): void
    {
        $appCss = file_get_contents(resource_path('css/app.css'));
        $publicCss = file_get_contents(resource_path('css/public.css'));
        $pushsaleCss = file_get_contents(resource_path('css/pushsale.css'));

        $this->assertStringNotContainsString('pushsale-layout.css', $appCss);
        $this->assertStringNotContainsString('public-shell.css', $appCss);
        $this->assertStringContainsString('public-shell.css', $publicCss);
        $this->assertStringContainsString('pushsale-system-v17.css', $pushsaleCss);
        $this->assertFileDoesNotExist(resource_path('css/pushsale-v12-fixes.css'));
        $this->assertFileDoesNotExist(resource_path('css/pushsale-v13-fixes.css'));
    }
}
