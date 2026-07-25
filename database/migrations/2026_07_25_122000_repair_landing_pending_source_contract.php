<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landing_connections') && Schema::hasColumn('landing_connections', 'marketing_source_id')) {
            try {
                DB::statement('ALTER TABLE landing_connections MODIFY marketing_source_id BIGINT UNSIGNED NULL');
            } catch (Throwable) {
                // Best-effort repair; runtime repair command repeats this on MySQL.
            }
        }

        if (Schema::hasTable('marketing_sources') && Schema::hasColumn('marketing_sources', 'product_id')) {
            try {
                DB::statement('ALTER TABLE marketing_sources MODIFY product_id BIGINT UNSIGNED NULL');
            } catch (Throwable) {
                // Best-effort repair.
            }
        }
    }

    public function down(): void
    {
        // Non-destructive repair only.
    }
};
