<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Database\Seeder;

class WarehouseInventorySeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::query()->where('code', 'HB')->first();
        $product = Product::query()->where('sku', 'SP292627')->first();
        $camera = Product::query()->where('sku', 'CAM-MINI')->first();

        if (! $warehouse || ! $product) {
            return;
        }

        WarehouseInventory::query()->updateOrCreate([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ], [
            'stock_quantity' => 36,
            'pending_sales_quantity' => 12,
            'location_code' => 'A-01',
        ]);

        if ($camera) {
            WarehouseInventory::query()->updateOrCreate([
                'warehouse_id' => $warehouse->id,
                'product_id' => $camera->id,
            ], [
                'stock_quantity' => 7,
                'pending_sales_quantity' => -2,
                'location_code' => 'B-12',
            ]);
        }
    }
}
