<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecommerce_shop_connections')) {
            Schema::create('ecommerce_shop_connections', function (Blueprint $table): void {
                $table->id();
                $table->string('platform', 32)->default('tiktok')->index();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('marketing_source_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
                $table->string('shop_id')->nullable()->index();
                $table->string('shop_name')->nullable();
                $table->string('logo_url')->nullable();
                $table->text('note')->nullable();
                $table->unsignedTinyInteger('logistics_mode')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->json('credentials')->nullable();
                $table->timestamps();
                $table->unique(['platform', 'shop_id']);
            });
        }

        if (! Schema::hasTable('ecommerce_product_links')) {
            Schema::create('ecommerce_product_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_connection_id')->constrained('ecommerce_shop_connections')->cascadeOnDelete();
                $table->string('platform', 32)->default('tiktok')->index();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('external_product_id')->index();
                $table->string('external_sku_id')->nullable();
                $table->string('external_name');
                $table->string('external_sku')->nullable();
                $table->json('external_attributes')->nullable();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_sku')->nullable();
                $table->unsignedInteger('sync_quantity')->default(0);
                $table->string('connection_status', 32)->default('unlinked')->index();
                $table->text('note')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_connection_id', 'external_product_id', 'external_sku_id'], 'ecommerce_product_links_unique_external');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_product_links');
        Schema::dropIfExists('ecommerce_shop_connections');
    }
};
