<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'sale_operation_note')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('sale_operation_note', 500)->nullable()->after('customer_note');
            });
        }

        // MySQL giữ nguyên unique index và cho phép nhiều NULL. Đơn chưa chốt không có mã đơn.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `orders` MODIFY `order_code` VARCHAR(255) NULL');
        } else {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('order_code')->nullable()->change();
            });
        }

        DB::table('orders')->whereNull('closed_at')->update(['order_code' => null]);
    }

    public function down(): void
    {
        DB::table('orders')
            ->whereNull('order_code')
            ->orderBy('id')
            ->eachById(function (object $order): void {
                DB::table('orders')->where('id', $order->id)->update([
                    'order_code' => 'PS'.str_pad((string) $order->id, 11, '0', STR_PAD_LEFT).'PS',
                ]);
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `orders` MODIFY `order_code` VARCHAR(255) NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('order_code')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('orders', 'sale_operation_note')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('sale_operation_note');
            });
        }
    }
};
