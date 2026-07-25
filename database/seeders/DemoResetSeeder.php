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
 * Giữ lại: cache/queue/job tables và các cấu hình tích hợp thật như
 * integration_connections, shipping_partner_connections, settings.
 */
class DemoResetSeeder extends Seeder
{
    /** Thứ tự không quan trọng vì đã tắt kiểm tra khóa ngoại. */
    private const TABLES = [
        // Reporting/caches first so late model observers cannot see stale facts.
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

        // Customer/operation audit trails.
        'activity_logs',
        'order_operation_histories',
        'customer_internal_messages',
        'pancake_customer_messages',
        'pancake_sync_records',
        'pancake_user_mappings',
        'customer_phone_locks',
        'landing_sessions',
        'user_notifications',
        'data_distribution_batches',

        // Warehouse / shipping / reconciliation.
        'warehouse_return_receipt_lines',
        'warehouse_return_receipts',
        'warehouse_inventory_movements',
        'warehouse_voucher_lines',
        'warehouse_vouchers',
        'warehouse_incident_reports',
        'warehouse_inventories',
        'shipping_status_events',
        'shipping_api_logs',
        'shipments',
        'shipping_webhook_events',
        'carrier_settlement_lines',
        'carrier_settlement_batches',

        // Marketplace / invoice / failed partner rows.
        'ecommerce_product_links',
        'ecommerce_shop_connections',
        'failed_partner_orders',
        'electronic_invoice_jobs',
        'electronic_invoice_configs',

        // Lead -> order flow.
        'lead_ingestions',
        'inbound_events',
        'order_items',
        'orders',
        'landing_connection_sales',
        'landing_connection_products',
        'landing_connection_sources',
        'landing_connections',

        // Catalog, settings and Pushsale business pages.
        'product_combo_items',
        'facebook_page_mappings',
        'partner_connections',
        'company_subscription_histories',
        'work_shifts',
        'operation_workflows',
        'operation_categories',
        'lead_distribution_rules',
        'care_distribution_rules',
        'report_access_rules',
        'seeding_phone_numbers',
        'customer_care_campaigns',
        'expenses',
        'expense_categories',
        'expense_groups',
        'expense_units',
        'discount_cod_rules',
        'phone_blacklists',
        'annual_business_plan_metrics',
        'revenue_bonus_rules',
        'monthly_kpi_plans',
        'kpi_catalog_items',
        'product_attribute_value_product',
        'product_category_product',
        'product_attribute_values',
        'product_attributes',
        'product_categories',
        'marketing_source_daily_metrics',
        'marketing_sources',
        'legacy_module_records',
        'products',
        'warehouses',

        // Org / auth / tenant.
        'user_operational_profiles',
        'teams',
        'user_preferences',
        'personal_access_tokens',
        'sessions',
        'users',
        'companies',
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
