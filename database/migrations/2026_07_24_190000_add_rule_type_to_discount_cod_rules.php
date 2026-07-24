<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discount_cod_rules')) {
            return;
        }

        Schema::table('discount_cod_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('discount_cod_rules', 'rule_type')) {
                $table->string('rule_type', 24)->default('discount')->after('id')->index();
            }
        });

        DB::table('discount_cod_rules')
            ->whereNull('rule_type')
            ->orWhere('rule_type', '')
            ->update(['rule_type' => 'discount']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('discount_cod_rules') || ! Schema::hasColumn('discount_cod_rules', 'rule_type')) {
            return;
        }

        Schema::table('discount_cod_rules', function (Blueprint $table): void {
            $table->dropColumn('rule_type');
        });
    }
};
