<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DemoStaffSeeder::class,
            OrganizationSeeder::class,
            CatalogSeeder::class,
            MarketingSourceSeeder::class,
            OrderSeeder::class,
            SaleOperationSeeder::class,
            LeadIngestionSeeder::class,
            WarehouseInventorySeeder::class,
            FailedOrderSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
