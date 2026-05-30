<?php

namespace Database\Seeders;

use App\Models\FailedPartnerOrder;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class FailedOrderSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::query()->where('code', 'HB')->first();

        FailedPartnerOrder::query()->firstOrCreate([
            'platform' => 'TikTok',
            'partner_order_id' => 'TT-DEMO-00001',
        ], [
            'warehouse_id' => $warehouse?->id,
            'shop_name' => 'Shop demo',
            'error_description' => 'Mã đơn không khớp kho',
        ]);
    }
}
