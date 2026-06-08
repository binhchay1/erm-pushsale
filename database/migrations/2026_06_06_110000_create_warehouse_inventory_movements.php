<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->integer('stock_after')->default(0);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_deducted_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('inventory_deducted_at');
        });

        Schema::dropIfExists('warehouse_inventory_movements');
    }
};
