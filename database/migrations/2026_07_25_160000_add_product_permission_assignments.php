<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'marketing_team_ids' => 'available_care',
                'marketing_user_ids' => 'marketing_team_ids',
                'sale_team_ids' => 'marketing_user_ids',
                'sale_user_ids' => 'sale_team_ids',
                'care_team_ids' => 'sale_user_ids',
                'care_user_ids' => 'care_team_ids',
            ] as $column => $after) {
                if (! Schema::hasColumn('products', $column)) {
                    $table->json($column)->nullable()->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'care_user_ids',
                'care_team_ids',
                'sale_user_ids',
                'sale_team_ids',
                'marketing_user_ids',
                'marketing_team_ids',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
