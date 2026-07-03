<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Loại dòng hàng: product (SP đơn lẻ) | combo (gói) | upsell (mua thêm ở trang cảm ơn) | gift
            $table->string('item_type', 20)->default('product')->after('product_name');
            // Nguồn tạo dòng: landing | upsell | telesale | system
            $table->string('origin', 20)->default('system')->after('item_type');
            // Chiết khấu theo dòng (VND), tổng dòng = unit_price*quantity - discount_amount
            $table->unsignedBigInteger('discount_amount')->default(0)->after('unit_price');
            // Chi tiết bổ sung: nhãn combo gốc, phân loại (01 Trắng sáng…), thành phần combo
            $table->json('meta')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'origin', 'discount_amount', 'meta']);
        });
    }
};
