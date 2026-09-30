<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_gateway_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('action', 60);
            $table->string('external_code', 64)->nullable();
            $table->string('partner_order_code', 64)->nullable();
            $table->string('gateway_order_id', 64)->nullable();
            $table->string('lookup_code', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'external_code']);
            $table->index(['gateway', 'lookup_code']);
            $table->index(['gateway', 'gateway_order_id']);
            $table->index(['order_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_gateway_traces');
    }
};
