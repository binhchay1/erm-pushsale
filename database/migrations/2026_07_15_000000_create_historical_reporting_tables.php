<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('lead_rows')->default(0);
            $table->unsignedBigInteger('order_rows')->default(0);
            $table->unsignedBigInteger('product_rows')->default(0);
            $table->unsignedBigInteger('cashflow_rows')->default(0);
            $table->unsignedBigInteger('inventory_rows')->default(0);
            $table->char('source_checksum', 64)->nullable();
            $table->char('facts_checksum', 64)->nullable();
            $table->timestamp('source_watermark_at')->nullable();
            $table->timestamp('last_rebuilt_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'metric_date']);
            $table->index(['status', 'metric_date']);
        });

        Schema::create('report_dirty_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('last_reason', 120)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('event_count')->default(1);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'metric_date']);
            $table->index(['next_attempt_at', 'locked_at']);
        });

        Schema::create('report_daily_lead_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('platform', 50)->default('');
            $table->string('status', 30)->default('');
            $table->string('packet_type', 30)->default('');
            $table->unsignedBigInteger('marketing_source_id')->default(0);
            $table->unsignedBigInteger('landing_connection_id')->default(0);
            $table->unsignedBigInteger('landing_connection_source_id')->default(0);
            $table->unsignedBigInteger('sale_user_id')->default(0);
            $table->unsignedBigInteger('marketer_user_id')->default(0);
            $table->unsignedBigInteger('team_id')->default(0);
            $table->unsignedBigInteger('warehouse_id')->default(0);
            $table->string('delivery_status', 50)->default('');
            $table->string('reconciliation_status', 40)->default('');
            $table->char('dimension_hash', 64);
            $table->unsignedBigInteger('packet_count')->default(0);
            $table->unsignedBigInteger('lead_count')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->unsignedBigInteger('duplicate_count')->default(0);
            $table->unsignedBigInteger('review_count')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'metric_date', 'dimension_hash'], 'report_daily_lead_facts_unique');
            $table->index(['company_id', 'metric_date', 'sale_user_id'], 'report_lead_company_day_sale_idx');
            $table->index(['company_id', 'metric_date', 'marketer_user_id'], 'report_lead_company_day_marketer_idx');
            $table->index(['company_id', 'metric_date', 'marketing_source_id'], 'report_lead_company_day_source_idx');
            $table->index(['company_id', 'metric_date', 'warehouse_id'], 'report_lead_company_day_warehouse_idx');
        });

        Schema::create('report_daily_order_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('date_basis', 40);
            $table->unsignedBigInteger('sale_user_id')->default(0);
            $table->unsignedBigInteger('marketer_user_id')->default(0);
            $table->unsignedBigInteger('team_id')->default(0);
            $table->unsignedBigInteger('marketing_source_id')->default(0);
            $table->unsignedBigInteger('landing_connection_id')->default(0);
            $table->unsignedBigInteger('warehouse_id')->default(0);
            $table->string('shipping_provider', 60)->default('');
            $table->string('delivery_status', 50)->default('');
            $table->string('reconciliation_status', 40)->default('');
            $table->string('operation_stage', 50)->default('');
            $table->string('closing_status', 50)->default('');
            $table->char('dimension_hash', 64);
            $table->unsignedBigInteger('order_count')->default(0);
            $table->unsignedBigInteger('closed_order_count')->default(0);
            $table->unsignedBigInteger('open_order_count')->default(0);
            $table->unsignedBigInteger('delivered_order_count')->default(0);
            $table->unsignedBigInteger('partial_delivery_count')->default(0);
            $table->unsignedBigInteger('returned_order_count')->default(0);
            $table->unsignedBigInteger('cancelled_order_count')->default(0);
            $table->unsignedBigInteger('upsell_order_count')->default(0);
            $table->unsignedBigInteger('contact_count')->default(0);
            $table->unsignedBigInteger('contacted_order_count')->default(0);
            $table->bigInteger('gross_sales')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('vat_amount')->default(0);
            $table->bigInteger('shipping_collected')->default(0);
            $table->bigInteger('order_value')->default(0);
            $table->bigInteger('recognized_revenue')->default(0);
            $table->bigInteger('deposit_amount')->default(0);
            $table->bigInteger('amount_to_collect')->default(0);
            $table->bigInteger('settled_cod_amount')->default(0);
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('closed_shipping_cost')->default(0);
            $table->bigInteger('return_fee')->default(0);
            $table->bigInteger('compensation_amount')->default(0);
            $table->bigInteger('net_cashflow')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'metric_date', 'date_basis', 'dimension_hash'], 'report_daily_order_facts_unique');
            $table->index(['company_id', 'metric_date', 'date_basis', 'sale_user_id'], 'report_order_company_day_basis_sale_idx');
            $table->index(['company_id', 'metric_date', 'date_basis', 'marketer_user_id'], 'report_order_company_day_basis_marketer_idx');
            $table->index(['company_id', 'metric_date', 'date_basis', 'warehouse_id'], 'report_order_company_day_basis_warehouse_idx');
            $table->index(['company_id', 'metric_date', 'date_basis', 'marketing_source_id'], 'report_order_company_day_basis_source_idx');
        });

        Schema::create('report_daily_product_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('date_basis', 40);
            $table->unsignedBigInteger('product_id')->default(0);
            $table->unsignedBigInteger('sale_user_id')->default(0);
            $table->unsignedBigInteger('marketer_user_id')->default(0);
            $table->unsignedBigInteger('team_id')->default(0);
            $table->unsignedBigInteger('marketing_source_id')->default(0);
            $table->unsignedBigInteger('landing_connection_id')->default(0);
            $table->unsignedBigInteger('warehouse_id')->default(0);
            $table->string('item_origin', 40)->default('');
            $table->boolean('is_upsell')->default(false);
            $table->string('delivery_status', 50)->default('');
            $table->string('reconciliation_status', 40)->default('');
            $table->char('dimension_hash', 64);
            $table->unsignedBigInteger('order_count')->default(0);
            $table->unsignedBigInteger('line_count')->default(0);
            $table->bigInteger('quantity')->default(0);
            $table->bigInteger('gross_sales')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('net_sales')->default(0);
            $table->bigInteger('cost_of_goods')->default(0);
            $table->bigInteger('recognized_revenue')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'metric_date', 'date_basis', 'dimension_hash'], 'report_daily_product_facts_unique');
            $table->index(['company_id', 'metric_date', 'date_basis', 'product_id'], 'report_product_company_day_basis_product_idx');
            $table->index(['company_id', 'metric_date', 'date_basis', 'warehouse_id'], 'report_product_company_day_basis_warehouse_idx');
        });

        Schema::create('report_daily_cashflow_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('event_basis', 30);
            $table->unsignedBigInteger('marketing_source_id')->default(0);
            $table->unsignedBigInteger('warehouse_id')->default(0);
            $table->string('shipping_provider', 60)->default('');
            $table->char('dimension_hash', 64);
            $table->unsignedBigInteger('shipment_count')->default(0);
            $table->unsignedBigInteger('cod_mismatch_count')->default(0);
            $table->bigInteger('cod_expected')->default(0);
            $table->bigInteger('cod_collected')->default(0);
            $table->bigInteger('cod_remitted')->default(0);
            $table->bigInteger('shipping_fee')->default(0);
            $table->bigInteger('return_fee')->default(0);
            $table->bigInteger('cod_fee')->default(0);
            $table->bigInteger('insurance_fee')->default(0);
            $table->bigInteger('other_fee')->default(0);
            $table->bigInteger('compensation_amount')->default(0);
            $table->bigInteger('net_cashflow')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'metric_date', 'event_basis', 'dimension_hash'], 'report_daily_cashflow_facts_unique');
            $table->index(['company_id', 'metric_date', 'shipping_provider'], 'report_cashflow_company_day_provider_idx');
        });

        Schema::create('report_daily_inventory_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('warehouse_id')->default(0);
            $table->unsignedBigInteger('product_id')->default(0);
            $table->string('movement_type', 30)->default('');
            $table->char('dimension_hash', 64);
            $table->unsignedBigInteger('movement_count')->default(0);
            $table->bigInteger('quantity_in')->default(0);
            $table->bigInteger('quantity_out')->default(0);
            $table->bigInteger('quantity_net')->default(0);
            $table->bigInteger('value_in')->default(0);
            $table->bigInteger('value_out')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'metric_date', 'dimension_hash'], 'report_daily_inventory_facts_unique');
            $table->index(['company_id', 'metric_date', 'warehouse_id', 'product_id'], 'report_inventory_company_day_wh_product_idx');
        });

        Schema::create('report_query_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_key', 120);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('date_type', 40)->nullable();
            $table->char('filter_hash', 64);
            $table->json('filter_payload')->nullable();
            $table->longText('payload');
            $table->string('encoding', 20)->default('gzip-base64-json');
            $table->unsignedInteger('data_revision')->default(1);
            $table->boolean('is_final')->default(false);
            $table->timestamp('source_watermark_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'report_key', 'filter_hash'], 'report_query_snapshots_unique');
            $table->index(['company_id', 'date_from', 'date_to'], 'report_snapshot_company_range_idx');
            $table->index(['expires_at', 'is_final']);
        });

        Schema::create('analytics_archive_manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_table', 80);
            $table->string('archive_table', 120);
            $table->char('archive_month', 7);
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('source_rows')->default(0);
            $table->unsignedBigInteger('archive_rows')->default(0);
            $table->char('source_checksum', 64)->nullable();
            $table->char('archive_checksum', 64)->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('source_purged')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source_table', 'archive_month'], 'analytics_archive_manifest_unique');
            $table->index(['archive_month', 'status']);
        });

        Schema::create('analytics_cold_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_table', 80);
            $table->char('archive_month', 7);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('source_created_at')->nullable();
            $table->char('row_checksum', 64);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['company_id', 'source_table', 'source_id'], 'analytics_cold_record_unique');
            $table->index(['company_id', 'source_table', 'archive_month'], 'analytics_cold_record_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_cold_records');
        Schema::dropIfExists('analytics_archive_manifests');
        Schema::dropIfExists('report_query_snapshots');
        Schema::dropIfExists('report_daily_inventory_facts');
        Schema::dropIfExists('report_daily_cashflow_facts');
        Schema::dropIfExists('report_daily_product_facts');
        Schema::dropIfExists('report_daily_order_facts');
        Schema::dropIfExists('report_daily_lead_facts');
        Schema::dropIfExists('report_dirty_dates');
        Schema::dropIfExists('report_daily_closures');
    }
};
