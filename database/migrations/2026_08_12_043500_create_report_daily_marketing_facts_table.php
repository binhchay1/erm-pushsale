<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_marketing_facts', function (Blueprint $table): void {
            $table->id();
            $table->date('fact_date');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('marketing_source_id')->default(0);
            $table->string('utm_campaign', 191)->default('');
            $table->string('status', 64)->default('');
            $table->unsignedBigInteger('total_leads')->default(0);
            $table->unsignedBigInteger('total_valid_leads')->default(0);
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->timestamp('last_aggregated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['fact_date', 'company_id', 'marketing_source_id', 'utm_campaign', 'status'],
                'rdmf_unique_day_company_source_campaign_status'
            );
            $table->index(['company_id', 'fact_date'], 'rdmf_company_date_idx');
            $table->index(['fact_date', 'marketing_source_id'], 'rdmf_date_source_idx');
            $table->index(['fact_date', 'utm_campaign'], 'rdmf_date_campaign_idx');
            $table->index(['fact_date', 'status'], 'rdmf_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_daily_marketing_facts');
    }
};
