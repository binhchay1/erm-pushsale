<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Người nhận hàng khác khách hàng (khi bỏ tick "khách hàng là người nhận").
            // Trống → khách hàng chính là người nhận.
            $table->string('receiver_name')->nullable()->after('shipping_address_2');
            $table->string('receiver_phone', 30)->nullable()->after('receiver_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receiver_name', 'receiver_phone']);
        });
    }
};
