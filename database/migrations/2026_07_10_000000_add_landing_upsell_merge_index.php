<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Lookup hot path: tenant + campaign + phone + open merge window.
            $table->index(
                ['company_id', 'marketing_source_id', 'customer_phone', 'landing_upsell_locked', 'landing_upsell_hold_until'],
                'orders_landing_upsell_merge_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_landing_upsell_merge_idx');
        });
    }
};
