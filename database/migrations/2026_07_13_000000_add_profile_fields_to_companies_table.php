<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = array_values(array_filter([
            'tax_code',
            'address',
            'website',
            'representative_name',
            'representative_title',
        ], static fn (string $column): bool => ! Schema::hasColumn('companies', $column)));

        if ($missing === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($missing): void {
            if (in_array('tax_code', $missing, true)) {
                $table->string('tax_code', 40)->nullable();
            }
            if (in_array('address', $missing, true)) {
                $table->string('address', 500)->nullable();
            }
            if (in_array('website', $missing, true)) {
                $table->string('website')->nullable();
            }
            if (in_array('representative_name', $missing, true)) {
                $table->string('representative_name', 160)->nullable();
            }
            if (in_array('representative_title', $missing, true)) {
                $table->string('representative_title', 160)->nullable();
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter([
            'tax_code',
            'address',
            'website',
            'representative_name',
            'representative_title',
        ], static fn (string $column): bool => Schema::hasColumn('companies', $column)));

        if ($existing === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};
