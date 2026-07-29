<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_optimization_levels')) {
            Schema::create('sale_optimization_levels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('metric_key', 80);
                $table->string('label', 120);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->decimal('min_ratio', 8, 2)->default(0);
                $table->decimal('max_ratio', 8, 2)->nullable();
                $table->string('tone', 40)->default('average');
                $table->timestamps();
                $table->unique(['company_id', 'metric_key', 'label'], 'sale_opt_levels_company_metric_label_uq');
            });
        }

        if (! Schema::hasTable('sale_optimization_alert_thresholds')) {
            Schema::create('sale_optimization_alert_thresholds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('metric_key', 80);
                $table->decimal('low_ratio', 8, 2)->default(80);
                $table->decimal('high_ratio', 8, 2)->default(100);
                $table->timestamps();
                $table->unique(['company_id', 'metric_key'], 'sale_opt_alert_company_metric_uq');
            });
        }

        if (! Schema::hasTable('sale_optimization_targets')) {
            Schema::create('sale_optimization_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('sale_user_id')->nullable()->index();
                $table->string('metric_key', 80);
                $table->decimal('target_value', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'sale_user_id', 'metric_key'], 'sale_opt_targets_company_sale_metric_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_optimization_targets');
        Schema::dropIfExists('sale_optimization_alert_thresholds');
        Schema::dropIfExists('sale_optimization_levels');
    }
};
