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

        $this->call(SaleOpsDemoSeeder::class);
    }
}
