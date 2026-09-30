<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 40);
            $table->string('source', 30);
            $table->string('action', 60)->nullable();
            $table->string('order_code', 64)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('external_code', 64)->nullable();
            $table->string('partner_order_code', 64)->nullable();
            $table->string('gateway_order_id', 64)->nullable();
            $table->string('lookup_code', 64)->nullable();
            $table->string('status_code', 40)->nullable();
            $table->string('summary', 255)->nullable();
            $table->json('payload')->nullable();
            $table->string('dedupe_key', 40);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique('dedupe_key', 'order_traces_dedupe_uq');
            $table->index(['company_id', 'phone'], 'order_traces_phone_idx');
            $table->index(['company_id', 'order_code'], 'order_traces_order_code_idx');
            $table->index(['company_id', 'external_code'], 'order_traces_external_idx');
            $table->index(['company_id', 'gateway_order_id'], 'order_traces_gw_id_idx');
            $table->index(['company_id', 'partner_order_code'], 'order_traces_partner_idx');
            $table->index(['order_id', 'occurred_at'], 'order_traces_order_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_traces');
    }
};
