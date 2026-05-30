<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseUsers = User::query()->where('role', UserRole::Warehouse)->get();
        $admin = User::query()->where('email', 'admin@saleops.local')->first();

        Product::query()->firstOrCreate(['sku' => 'SP-PARENT-01'], [
            'name' => 'Gối mây đan',
            'unit_price' => 299_000,
        ]);

        Product::query()->firstOrCreate(['sku' => 'SP292627'], [
            'parent_id' => Product::query()->where('sku', 'SP-PARENT-01')->value('id'),
            'name' => 'Gối mây đan (SP292627)',
            'unit_price' => 159_000,
        ]);

        Product::query()->firstOrCreate(['sku' => 'CAM-MINI'], [
            'name' => 'Camera mini NK',
            'unit_price' => 890_000,
        ]);

        Warehouse::query()->firstOrCreate(['code' => 'HB'], [
            'name' => 'Kho Hòa Bình',
            'phone' => '0988111222',
            'address' => 'KCN Hòa Bình, Hà Nội',
            'manager_user_id' => $warehouseUsers->first()?->id ?? $admin?->id,
            'vtp_code' => 'VTP-HB-01',
        ]);
    }
}
