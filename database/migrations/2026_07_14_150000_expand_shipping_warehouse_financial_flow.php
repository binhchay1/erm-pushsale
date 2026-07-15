<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('default_shipping_provider', 50)->nullable();
            $table->string('default_shipping_method', 80)->nullable()->after('default_shipping_provider');
        });

        Schema::table('shipping_partner_connections', function (Blueprint $table): void {
            // Bảng cũ unique toàn cục theo provider; V16 cho phép mỗi tenant có cấu hình riêng.
            $table->dropUnique('shipping_partner_connections_provider_unique');
            $table->string('integration_mode', 30)->default('direct')->after('provider');
            $table->json('settings')->nullable()->after('credentials');
            $table->unique(['company_id', 'provider'], 'shipping_partner_connections_company_provider_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('warehouse_care_status', 60)->nullable()->after('delivery_status');
            $table->text('warehouse_care_note')->nullable()->after('warehouse_care_status');
            $table->foreignId('warehouse_care_user_id')->nullable()->after('warehouse_care_note')->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at')->nullable()->after('warehouse_care_user_id');
            $table->unsignedBigInteger('carrier_return_fee')->default(0)->after('carrier_service_fee');
            $table->unsignedBigInteger('carrier_other_fee')->default(0)->after('carrier_return_fee');
            $table->unsignedBigInteger('carrier_compensation_amount')->default(0)->after('carrier_other_fee');
            $table->timestamp('last_delivery_event_at')->nullable()->after('settlement_matched_at');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            // Vận đơn cũng phải unique trong tenant, không được khóa trùng mã đối tác giữa hai công ty.
            $table->dropUnique('shipments_provider_partner_order_id_unique');
            $table->unique(['company_id', 'provider', 'partner_order_id'], 'shipments_company_provider_partner_unique');
            $table->unsignedBigInteger('cod_amount')->default(0)->after('insurance_fee');
            $table->unsignedBigInteger('cod_collected')->default(0)->after('cod_amount');
            $table->unsignedBigInteger('cod_remitted')->default(0)->after('cod_collected');
            $table->unsignedBigInteger('cod_fee')->default(0)->after('cod_remitted');
            $table->unsignedBigInteger('return_fee')->default(0)->after('cod_fee');
            $table->unsignedBigInteger('other_fee')->default(0)->after('return_fee');
            $table->unsignedBigInteger('compensation_amount')->default(0)->after('other_fee');
            $table->timestamp('posted_at')->nullable()->after('submitted_at');
            $table->timestamp('picked_up_at')->nullable()->after('posted_at');
            $table->timestamp('delivered_at')->nullable()->after('picked_up_at');
            $table->timestamp('returning_at')->nullable()->after('delivered_at');
            $table->timestamp('returned_at')->nullable()->after('returning_at');
            $table->timestamp('cod_remitted_at')->nullable()->after('returned_at');
            $table->timestamp('last_event_at')->nullable()->after('last_synced_at');
        });

        Schema::table('shipping_webhook_events', function (Blueprint $table): void {
            $table->string('event_hash', 64)->nullable()->after('provider');
            $table->timestamp('occurred_at')->nullable()->after('received_at');
            $table->unsignedBigInteger('shipping_fee')->default(0)->after('system_cod');
            $table->unsignedBigInteger('return_fee')->default(0)->after('shipping_fee');
            $table->unsignedBigInteger('cod_fee')->default(0)->after('return_fee');
            $table->unsignedBigInteger('other_fee')->default(0)->after('cod_fee');
            $table->unsignedBigInteger('compensation_amount')->default(0)->after('other_fee');
            $table->json('normalized_payload')->nullable()->after('payload');
            $table->unique(['company_id', 'provider', 'event_hash'], 'shipping_webhook_event_hash_unique');
            $table->index(['order_id', 'occurred_at'], 'shipping_webhook_order_occurred_idx');
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('return_fee')->default(0)->after('carrier_fee');
            $table->unsignedBigInteger('cod_fee')->default(0)->after('return_fee');
            $table->unsignedBigInteger('insurance_fee')->default(0)->after('cod_fee');
            $table->unsignedBigInteger('other_fee')->default(0)->after('insurance_fee');
            $table->unsignedBigInteger('compensation_amount')->default(0)->after('other_fee');
        });

        Schema::create('shipping_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50);
            $table->string('event_key', 120);
            $table->string('raw_status')->nullable();
            $table->string('mapped_status', 50)->nullable();
            $table->string('location')->nullable();
            $table->text('note')->nullable();
            $table->json('financials')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider', 'event_key'], 'shipping_status_events_unique');
            $table->index(['order_id', 'occurred_at']);
            $table->index(['shipment_id', 'occurred_at']);
        });

        Schema::create('warehouse_return_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('manual');
            $table->string('status', 30)->default('received');
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'order_id'], 'warehouse_return_receipts_order_unique');
        });

        Schema::create('warehouse_return_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receipt_id')->constrained('warehouse_return_receipts')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('restock_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->string('condition', 30)->default('sellable');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['receipt_id', 'order_item_id'], 'warehouse_return_receipt_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_return_receipt_lines');
        Schema::dropIfExists('warehouse_return_receipts');
        Schema::dropIfExists('shipping_status_events');

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropColumn(['return_fee', 'cod_fee', 'insurance_fee', 'other_fee', 'compensation_amount']);
        });

        Schema::table('shipping_webhook_events', function (Blueprint $table): void {
            $table->dropUnique('shipping_webhook_event_hash_unique');
            $table->dropIndex('shipping_webhook_order_occurred_idx');
            $table->dropColumn([
                'event_hash', 'occurred_at', 'shipping_fee', 'return_fee', 'cod_fee',
                'other_fee', 'compensation_amount', 'normalized_payload',
            ]);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('shipments_company_provider_partner_unique');
            $table->dropColumn([
                'cod_amount', 'cod_collected', 'cod_remitted', 'cod_fee', 'return_fee', 'other_fee',
                'compensation_amount', 'posted_at', 'picked_up_at', 'delivered_at', 'returning_at',
                'returned_at', 'cod_remitted_at', 'last_event_at',
            ]);
            $table->unique(['provider', 'partner_order_id'], 'shipments_provider_partner_order_id_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_care_user_id');
            $table->dropColumn([
                'warehouse_care_status', 'warehouse_care_note', 'printed_at', 'carrier_return_fee', 'carrier_other_fee',
                'carrier_compensation_amount', 'last_delivery_event_at',
            ]);
        });

        Schema::table('shipping_partner_connections', function (Blueprint $table): void {
            $table->dropUnique('shipping_partner_connections_company_provider_unique');
            $table->dropColumn(['integration_mode', 'settings']);
            $table->unique('provider', 'shipping_partner_connections_provider_unique');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['default_shipping_provider', 'default_shipping_method']);
        });
    }
};
