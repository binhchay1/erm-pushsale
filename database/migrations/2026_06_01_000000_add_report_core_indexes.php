<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['data_arrived_at', 'delivery_status'], 'orders_report_arrival_delivery_idx');
            $table->index(['assigned_at', 'sale_user_id'], 'orders_report_assigned_sale_idx');
            $table->index(['closed_at', 'marketer_user_id'], 'orders_report_closed_marketer_idx');
            $table->index(['marketing_source_id', 'data_arrived_at'], 'orders_report_source_arrival_idx');
            $table->index(['warehouse_id', 'delivery_status'], 'orders_report_warehouse_delivery_idx');
            $table->index(['reconciliation_status', 'delivery_status'], 'orders_report_reconciliation_delivery_idx');
        });

        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->index(['created_at', 'platform'], 'lead_ingestions_report_created_platform_idx');
            $table->index(['status', 'created_at'], 'lead_ingestions_report_status_created_idx');
            $table->index(['utm_campaign', 'created_at'], 'lead_ingestions_report_campaign_created_idx');
        });

        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->index(['utm_campaign', 'is_active'], 'marketing_sources_report_campaign_active_idx');
            $table->index(['utm_source', 'is_active'], 'marketing_sources_report_source_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropIndex('marketing_sources_report_campaign_active_idx');
            $table->dropIndex('marketing_sources_report_source_active_idx');
        });

        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->dropIndex('lead_ingestions_report_created_platform_idx');
            $table->dropIndex('lead_ingestions_report_status_created_idx');
            $table->dropIndex('lead_ingestions_report_campaign_created_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_report_arrival_delivery_idx');
            $table->dropIndex('orders_report_assigned_sale_idx');
            $table->dropIndex('orders_report_closed_marketer_idx');
            $table->dropIndex('orders_report_source_arrival_idx');
            $table->dropIndex('orders_report_warehouse_delivery_idx');
            $table->dropIndex('orders_report_reconciliation_delivery_idx');
        });
    }
};
