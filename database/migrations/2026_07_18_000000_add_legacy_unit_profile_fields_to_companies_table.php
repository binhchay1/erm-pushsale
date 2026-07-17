<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = array_values(array_filter([
            'product_field',
            'address_2',
            'use_two_level_address',
            'province_name',
            'district_name',
            'ward_name',
        ], static fn (string $column): bool => ! Schema::hasColumn('companies', $column)));

        if ($missing === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($missing): void {
            if (in_array('product_field', $missing, true)) {
                $table->string('product_field', 120)->nullable()->after('tax_code');
            }
            if (in_array('address_2', $missing, true)) {
                $table->string('address_2', 500)->nullable()->after('address');
            }
            if (in_array('use_two_level_address', $missing, true)) {
                $table->boolean('use_two_level_address')->default(false)->after('address_2');
            }
            if (in_array('province_name', $missing, true)) {
                $table->string('province_name', 120)->nullable()->after('use_two_level_address');
            }
            if (in_array('district_name', $missing, true)) {
                $table->string('district_name', 120)->nullable()->after('province_name');
            }
            if (in_array('ward_name', $missing, true)) {
                $table->string('ward_name', 120)->nullable()->after('district_name');
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter([
            'product_field',
            'address_2',
            'use_two_level_address',
            'province_name',
            'district_name',
            'ward_name',
        ], static fn (string $column): bool => Schema::hasColumn('companies', $column)));

        if ($existing === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};
