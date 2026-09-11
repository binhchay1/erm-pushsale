<?php

namespace Tests\Unit\Repositories;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WarehouseApproversFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_admin_when_no_warehouse_leader(): void
    {
        $company = Company::query()->create([
            'name' => 'Co',
            'code' => 'co-'.uniqid(),
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Super',
            'email' => 'super-'.uniqid().'@saleops.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_platform_admin' => true,
        ]);

        $options = app(UserRepository::class)->warehouseApprovers();

        $this->assertNotEmpty($options);
        $this->assertTrue(collect($options)->contains(fn ($row) => (int) $row['id'] === (int) $admin->id));
    }
}
