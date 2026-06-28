<?php

namespace Tests;

use App\Models\Company;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Multi-tenant: chạy test trong ngữ cảnh 1 công ty dùng chung để dữ liệu
        // tạo ra (factory/seed) và user đăng nhập (actingAs / SetTenant) cùng company.
        if (Schema::hasTable('companies')) {
            $company = Company::query()->firstOrCreate(
                ['slug' => 'test-co'],
                ['name' => 'Test Co', 'status' => Company::STATUS_ACTIVE, 'plan' => 'pro'],
            );

            app(TenantManager::class)->set($company->id);
        }
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clear();

        parent::tearDown();
    }
}
