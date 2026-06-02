<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('pick_province')->nullable()->after('address');
            $table->string('pick_district')->nullable()->after('pick_province');
            $table->string('pick_ward')->nullable()->after('pick_district');
            $table->string('ghtk_pick_address_id', 40)->nullable()->after('vtp_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('shipping_geo')->nullable()->after('shipping_address');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('partner_order_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->unsignedBigInteger('tracking_id')->nullable();
            $table->unsignedSmallInteger('status_id')->nullable();
            $table->string('status_text')->nullable();
            $table->unsignedBigInteger('fee')->nullable();
            $table->unsignedBigInteger('insurance_fee')->nullable();
            $table->string('transport', 20)->nullable();
            $table->string('state', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'partner_order_id']);
            $table->index(['provider', 'tracking_number']);
        });

        Schema::create('shipping_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('method', 10);
            $table->string('endpoint');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->string('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('log_id')->nullable();
            $table->timestamps();

            $table->index(['provider', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_api_logs');
        Schema::dropIfExists('shipments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_geo');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['pick_province', 'pick_district', 'pick_ward', 'ghtk_pick_address_id']);
        });
    }
};
