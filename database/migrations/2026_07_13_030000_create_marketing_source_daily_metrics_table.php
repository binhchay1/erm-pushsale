<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_source_daily_metrics')) {
            return;
        }

        Schema::create('marketing_source_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_source_id')->constrained('marketing_sources')->cascadeOnDelete();
            $table->date('metric_date')->index();
            // Dùng chuỗi rỗng thay vì NULL để unique key hoạt động đúng trên MySQL.
            $table->string('utm_source', 191)->default('');
            $table->string('utm_campaign', 191)->default('');
            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['company_id', 'marketing_source_id', 'metric_date', 'utm_source', 'utm_campaign'],
                'msdm_company_source_day_utm_unique'
            );
            $table->index(
                ['marketing_source_id', 'metric_date'],
                'msdm_source_day_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_source_daily_metrics');
    }
};
