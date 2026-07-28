<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'warehouse_care_updated_at')) {
                $table->timestamp('warehouse_care_updated_at')->nullable()->after('warehouse_care_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'warehouse_care_updated_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('warehouse_care_updated_at');
        });
    }
};
