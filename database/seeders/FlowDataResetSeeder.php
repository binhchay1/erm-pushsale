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
        'report_query_snapshots',
        'report_daily_inventory_facts',
        'report_daily_cashflow_facts',
        'report_daily_product_facts',
        'report_daily_order_facts',
        'report_daily_lead_facts',
        'report_dirty_dates',
        'report_daily_closures',
        'analytics_cold_records',
        'analytics_archive_manifests',
        'activity_logs',
        'order_operation_histories',
        'customer_internal_messages',
        'pancake_customer_messages',
        'pancake_sync_records',
        'customer_phone_locks',
        'landing_sessions',
        'user_notifications',
        'data_distribution_batches',
        'warehouse_return_receipt_lines',
        'warehouse_return_receipts',
        'warehouse_inventory_movements',
        'warehouse_voucher_lines',
        'warehouse_vouchers',
        'shipping_status_events',
        'shipping_api_logs',
        'shipments',
        'shipping_webhook_events',
        'carrier_settlement_lines',
        'carrier_settlement_batches',
        'failed_partner_orders',
        'lead_ingestions',
        'inbound_events',
        'order_items',
        'orders',
        'landing_connection_sales',
        'landing_connection_products',
        'landing_connection_sources',
        'landing_connections',
        'marketing_source_daily_metrics',
        'operation_workflows',
        'operation_categories',
        'lead_distribution_rules',
        'care_distribution_rules',
        'report_access_rules',
        'seeding_phone_numbers',
        'customer_care_campaigns',
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
