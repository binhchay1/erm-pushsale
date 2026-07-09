<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pancake_sync_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('shop_id', 80)->nullable()->index();
            $table->string('external_type', 40);
            $table->string('external_id', 120);
            $table->string('external_code', 120)->nullable();
            $table->foreignId('lead_ingestion_id')->nullable()->constrained('lead_ingestions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 40)->default('synced');
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_type', 'external_id'], 'pancake_sync_company_type_external_unique');
            $table->index(['company_id', 'shop_id', 'external_type']);
            $table->index(['order_id', 'external_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_sync_records');
    }
};
