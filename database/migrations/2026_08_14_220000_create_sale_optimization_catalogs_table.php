<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_optimization_catalogs')) {
            return;
        }

        Schema::create('sale_optimization_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('leader_user_id')->nullable()->index();
            $table->string('name', 120);
            $table->json('metrics')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'leader_user_id'], 'sale_opt_catalogs_company_leader_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_optimization_catalogs');
    }
};
