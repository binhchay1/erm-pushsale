<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureUser('Quản trị viên', 'admin@saleops.local', UserRole::Admin);
        $this->ensureUser('Nhân viên Telesale', 'sales@saleops.local', UserRole::Sales);
        $this->ensureUser('Nhân viên Marketing', 'marketing@saleops.local', UserRole::Marketing);
        $this->ensureUser('Nhân viên Kho', 'warehouse@saleops.local', UserRole::Warehouse);
        $this->ensureUser('Nhân viên Chia số', 'allocator@saleops.local', UserRole::Allocator);
        $this->ensureUser('Nhân viên Kế toán', 'accounting@saleops.local', UserRole::Accounting);

        $this->call(SaleOpsDemoSeeder::class);
    }

    protected function ensureUser(string $name, string $email, UserRole $role): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'role' => $role]
        );
        $user->ensurePreferences();
    }
}
