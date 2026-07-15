<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        if (! Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                // Giá vốn được chụp tại thời điểm dòng hàng được tạo/sửa. Báo cáo lịch sử
                // không bị thay đổi khi giá nhập hiện tại của sản phẩm thay đổi.
                $table->unsignedBigInteger('cost_price')->default(0)->after('unit_price');
            });
        }

        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'cost_price')) {
            return;
        }

        // Dữ liệu cũ được chụp lại theo catalog tại thời điểm migrate. Với combo không
        // có giá vốn trực tiếp, giá vốn bằng tổng giá vốn thành phần × số lượng.
        DB::table('order_items')
            ->where('cost_price', 0)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->chunkById(1000, function ($items): void {
                $productIds = $items->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
                if ($productIds->isEmpty()) {
                    return;
                }

                $directCosts = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->pluck('cost_price', 'id')
                    ->map(fn ($value): int => max(0, (int) $value));

                $comboCosts = collect();
                if (Schema::hasTable('product_combo_items')) {
                    $comboCosts = DB::table('product_combo_items as combo_items')
                        ->join('products as components', 'components.id', '=', 'combo_items.component_product_id')
                        ->whereIn('combo_items.combo_product_id', $productIds)
                        ->selectRaw('combo_items.combo_product_id, SUM(combo_items.quantity * COALESCE(components.cost_price, 0)) as aggregate_cost')
                        ->groupBy('combo_items.combo_product_id')
                        ->pluck('aggregate_cost', 'combo_items.combo_product_id')
                        ->map(fn ($value): int => max(0, (int) $value));
                }

                foreach ($items as $item) {
                    $productId = (int) $item->product_id;
                    $cost = (int) ($directCosts[$productId] ?? 0);
                    if ($cost <= 0) {
                        $cost = (int) ($comboCosts[$productId] ?? 0);
                    }

                    if ($cost > 0) {
                        DB::table('order_items')->where('id', $item->id)->update(['cost_price' => $cost]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('cost_price');
            });
        }
    }
};
