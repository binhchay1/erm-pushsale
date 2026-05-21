<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20); // sale | marketing
            $table->foreignId('leader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('ad_channel')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedInteger('interactions')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('sale_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('marketer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('marketing_source_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('phone_carrier', 30)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->text('accounting_notes')->nullable();
            $table->string('internal_recon_note')->nullable();

            $table->timestamp('data_arrived_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('desired_delivery_at')->nullable();

            $table->string('operation_stage', 40)->default('new_customer');
            $table->string('operation_result')->nullable();
            $table->string('closing_status', 40)->nullable();
            $table->string('delivery_status', 40)->default('waiting_waybill');
            $table->string('shipping_method')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('reconciliation_status', 30)->default('pending');

            $table->boolean('is_returning_customer')->default(false);
            $table->boolean('is_duplicate_phone')->default(false);

            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('vat')->default(0);
            $table->unsignedBigInteger('shipping_fee_collected')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('deposit')->default(0);
            $table->unsignedBigInteger('amount_to_collect')->default(0);
            $table->unsignedBigInteger('carrier_service_fee')->default(0);
            $table->unsignedBigInteger('shipping_support_fee')->default(0);
            $table->unsignedBigInteger('cod_fee')->default(0);
            $table->unsignedBigInteger('cod_support')->default(0);

            $table->unsignedInteger('contact_count')->default(1);
            $table->decimal('sales_kpi', 14, 0)->nullable();

            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->timestamps();
        });

        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('location_code')->nullable();
            $table->string('uom')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('pending_sales_quantity')->default(0);
            $table->boolean('is_discontinued')->default(false);
            $table->string('business_status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('failed_partner_orders', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 40);
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_name')->nullable();
            $table->string('partner_order_id');
            $table->text('error_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_partner_orders');
        Schema::dropIfExists('warehouse_inventories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('marketing_sources');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('teams');
    }
};
