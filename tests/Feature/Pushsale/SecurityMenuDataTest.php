<?php

namespace Tests\Feature\Pushsale;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Pushsale\PushsalePageService;
use App\Support\ActivityLogger;
use App\Support\TenantManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SecurityAuditDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityMenuDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_demo_seed_populates_login_history_and_login_access(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty bảo mật',
            'slug' => 'security-menu-data',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 50,
        ]);

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->seed(AccountSeeder::class);
            $this->seed(SecurityAuditDemoSeeder::class);

            $this->assertGreaterThan(0, ActivityLog::query()->where('action', ActivityLogger::AUTH_LOGIN_SUCCESS)->count());
            $this->assertGreaterThan(0, User::query()->whereNotNull('permissions')->count());

            $service = app(PushsalePageService::class);
            $loginRows = $service->rows('1.7.1', request())['data'];
            $permissionRows = $service->rows('1.7.2', request())['data'];

            $this->assertNotEmpty($loginRows);
            $this->assertNotEmpty($permissionRows);
            $this->assertArrayHasKey('account', $loginRows[0]);
            $this->assertArrayHasKey('access_code', $permissionRows[0]);
        });
    }
}
