<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['company_id', 'marketer_user_id', 'data_arrived_at'], 'orders_mkt_arrived_idx');
            $table->index(['company_id', 'marketing_source_id', 'data_arrived_at'], 'orders_source_arrived_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['company_id', 'order_id', 'item_type'], 'order_items_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_mkt_arrived_idx');
            $table->dropIndex('orders_source_arrived_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_type_idx');
        });
    }
};
