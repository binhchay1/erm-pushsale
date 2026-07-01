<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xóa dữ liệu luồng nghiệp vụ (lead → đơn → vận chuyển → đối soát)
 * nhưng giữ: users, teams, companies, products, warehouses, tồn kho,
 * integration_connections, shipping_partner_connections, settings.
 */
class FlowDataResetSeeder extends Seeder
{
    private const FLOW_TABLES = [
        'user_notifications',
        'warehouse_inventory_movements',
        'carrier_settlement_lines',
        'carrier_settlement_batches',
        'inbound_events',
        'shipping_api_logs',
        'shipments',
        'shipping_webhook_events',
        'failed_partner_orders',
        'lead_ingestions',
        'order_items',
        'orders',
        'marketing_sources',
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::FLOW_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->command?->info("Truncated: {$table}");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info('Đã xóa dữ liệu luồng nghiệp vụ — giữ tài khoản, sản phẩm, kho, cấu hình tích hợp.');
    }
}
