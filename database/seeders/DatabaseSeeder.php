<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point chuẩn cho dữ liệu demo ERM Pushsale.
 *
 * Giữ DatabaseSeeder cực mỏng để mọi nơi chỉ còn 1 bộ seed nguồn:
 * - php artisan db:seed --force
 * - php artisan db:seed --class=Database\\Seeders\\FullBusinessDemoSeeder --force
 * - php artisan erm:test-all --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FullBusinessDemoSeeder::class);
    }
}
