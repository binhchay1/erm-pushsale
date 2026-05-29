<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['sale_user_id', 'closed_at'], 'orders_sale_closed_idx');
            $table->index(['marketer_user_id', 'closed_at'], 'orders_marketer_closed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_sale_closed_idx');
            $table->dropIndex('orders_marketer_closed_idx');
        });
    }
};
