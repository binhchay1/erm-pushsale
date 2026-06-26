<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index cho tra cứu theo SĐT — tăng tốc chống trùng lead & tìm khách.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->index('customer_phone', 'lead_ingestions_customer_phone_index');
            $table->index('created_at', 'lead_ingestions_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_phone', 'created_at'], 'orders_phone_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->dropIndex('lead_ingestions_customer_phone_index');
            $table->dropIndex('lead_ingestions_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_phone_created_index');
        });
    }
};
