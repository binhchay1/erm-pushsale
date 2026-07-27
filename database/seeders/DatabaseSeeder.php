<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point seed mặc định — chỉ bootstrap superadmin + đơn vị nội bộ.
 *
 * Dữ liệu demo nghiệp vụ: php artisan db:seed --class=Database\\Seeders\\FullBusinessDemoSeeder --force
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);
    }
}
