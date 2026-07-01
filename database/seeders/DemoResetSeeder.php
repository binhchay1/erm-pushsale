<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Làm mới toàn bộ dữ liệu demo: xóa sạch dữ liệu nghiệp vụ + tài khoản
 * trước khi seed lại từ đầu, đảm bảo `php artisan db:seed --force`
 * luôn cho ra một bộ dữ liệu đồng bộ, kể cả trên production.
 *
 * Giữ lại: cấu hình kết nối nền tảng (integration_connections),
 * cấu hình hãng vận chuyển (shipping_partner_connections), cache, queue.
 */
class DemoResetSeeder extends Seeder
{
    /** Thứ tự không quan trọng vì đã tắt kiểm tra khóa ngoại. */
    private const TABLES = [
        'user_notifications',
        'warehouse_inventory_movements',
        'warehouse_inventories',
        'shipping_api_logs',
        'shipments',
        'shipping_webhook_events',
        'failed_partner_orders',
        'lead_ingestions',
        'order_items',
        'orders',
        'marketing_sources',
        'products',
        'warehouses',
        'teams',
        'user_preferences',
        'personal_access_tokens',
        'sessions',
        'users',
        'companies',
        'inbound_events',
        'carrier_settlement_lines',
        'carrier_settlement_batches',
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info('Đã xóa sạch dữ liệu demo cũ.');
    }
}
