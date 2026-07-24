<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 120)->nullable()->after('vat_code');
            }
            if (! Schema::hasColumn('products', 'length_cm')) {
                $table->decimal('length_cm', 10, 2)->default(0)->after('weight_grams');
            }
            if (! Schema::hasColumn('products', 'width_cm')) {
                $table->decimal('width_cm', 10, 2)->default(0)->after('length_cm');
            }
            if (! Schema::hasColumn('products', 'height_cm')) {
                $table->decimal('height_cm', 10, 2)->default(0)->after('width_cm');
            }
            if (! Schema::hasColumn('products', 'warehouse_location')) {
                $table->string('warehouse_location', 120)->nullable()->after('height_cm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach (['warehouse_location', 'height_cm', 'width_cm', 'length_cm', 'barcode'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
