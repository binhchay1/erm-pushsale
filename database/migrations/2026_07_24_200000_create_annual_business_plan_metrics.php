<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('annual_business_plan_metrics')) {
            return;
        }

        Schema::create('annual_business_plan_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->string('metric_code', 8);
            $table->string('metric_name');
            $table->decimal('planned_value', 18, 2)->default(0);
            $table->boolean('locked')->default(false)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'month', 'metric_code'], 'annual_business_plan_metric_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_business_plan_metrics');
    }
};
