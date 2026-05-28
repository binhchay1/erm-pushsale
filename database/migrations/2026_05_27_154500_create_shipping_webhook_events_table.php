<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('event_type')->nullable();
            $table->string('partner_order_code')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('raw_status')->nullable();
            $table->string('mapped_status', 40)->nullable();
            $table->integer('partner_cod')->nullable();
            $table->integer('system_cod')->nullable();
            $table->boolean('is_cod_mismatch')->default(false);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamp('received_at')->nullable();
            $table->string('result', 30)->default('processed');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['result', 'is_cod_mismatch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_events');
    }
};
