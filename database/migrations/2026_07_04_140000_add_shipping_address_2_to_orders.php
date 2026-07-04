<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Địa chỉ giao đã được sale xác nhận/chuẩn hoá (Tỉnh/Huyện/Xã + số nhà).
            // Nếu trống → dùng shipping_address gốc từ landing khi giao/xuất dữ liệu.
            $table->text('shipping_address_2')->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_address_2');
        });
    }
};
