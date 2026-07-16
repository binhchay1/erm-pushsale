<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_inventory_movements') || Schema::hasColumn('warehouse_inventory_movements', 'unit_cost')) {
            return;
        }

        Schema::table('warehouse_inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_cost')->default(0)->after('quantity');
            $table->index(['company_id', 'created_at', 'warehouse_id'], 'inventory_movements_report_day_idx');
        });

        DB::table('warehouse_inventory_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('m.unit_cost', 0)
            ->select(['m.id', 'p.cost_price'])
            ->orderBy('m.id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('warehouse_inventory_movements')
                        ->where('id', $row->id)
                        ->update(['unit_cost' => (int) $row->cost_price]);
                }
            }, 'm.id', 'id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouse_inventory_movements') || ! Schema::hasColumn('warehouse_inventory_movements', 'unit_cost')) {
            return;
        }

        Schema::table('warehouse_inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_report_day_idx');
            $table->dropColumn('unit_cost');
        });
    }
};
