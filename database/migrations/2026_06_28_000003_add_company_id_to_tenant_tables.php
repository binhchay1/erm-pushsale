<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'teams',
        'orders',
        'order_items',
        'lead_ingestions',
        'marketing_sources',
        'products',
        'warehouses',
        'warehouse_inventories',
        'warehouse_inventory_movements',
        'integration_connections',
        'shipping_partner_connections',
        'shipments',
        'failed_partner_orders',
        'user_notifications',
        'shipping_webhook_events',
        'shipping_api_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || Schema::hasColumn($name, 'company_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || ! Schema::hasColumn($name, 'company_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }
    }
};
