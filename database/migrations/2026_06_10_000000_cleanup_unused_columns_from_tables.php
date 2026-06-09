<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dọn dẹp cột rác sau audit toàn bộ schema (2026-06-10).
 *
 * Các cột bị xóa đã được quét usage trên toàn bộ app/, resources/js/,
 * database/seeders/, config/, routes/ — không còn nơi nào đọc/ghi ngoài
 * khai báo $fillable/$casts trong model:
 *
 * - orders.sales_kpi                          — cột KPI cũ, chưa từng có logic tính/hiển thị.
 * - integration_connections.metadata          — JSON catch-all, không nơi nào đọc/ghi.
 * - shipping_partner_connections.metadata     — JSON catch-all, không nơi nào đọc/ghi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sales_kpi');
        });

        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('shipping_partner_connections', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('sales_kpi', 14, 0)->nullable();
        });

        Schema::table('integration_connections', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });

        Schema::table('shipping_partner_connections', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });
    }
};
