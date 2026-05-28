<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Quản trị viên',
            'email' => 'admin@saleops.local',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);
        $admin->ensurePreferences();

        $sales = User::factory()->create([
            'name' => 'Nhân viên Telesale',
            'email' => 'sales@saleops.local',
            'password' => 'password',
            'role' => UserRole::Sales,
        ]);
        $sales->ensurePreferences();

        User::factory()->create([
            'name' => 'Nhân viên Marketing',
            'email' => 'marketing@saleops.local',
            'password' => 'password',
            'role' => UserRole::Marketing,
        ])->ensurePreferences();

        User::factory()->create([
            'name' => 'Nhân viên Kho',
            'email' => 'warehouse@saleops.local',
            'password' => 'password',
            'role' => UserRole::Warehouse,
        ])->ensurePreferences();

        User::factory()->create([
            'name' => 'Nhân viên Chia số',
            'email' => 'allocator@saleops.local',
            'password' => 'password',
            'role' => UserRole::Allocator,
        ])->ensurePreferences();

        User::factory()->create([
            'name' => 'Nhân viên Kế toán',
            'email' => 'accounting@saleops.local',
            'password' => 'password',
            'role' => UserRole::Accounting,
        ])->ensurePreferences();

        $this->call(SaleOpsDemoSeeder::class);
    }
}
