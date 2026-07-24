<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_catalog_items')) {
            return;
        }

        Schema::create('kpi_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position_key', 32)->index();
            $table->string('kpi_name');
            $table->unsignedBigInteger('daily_budget')->default(0);
            $table->unsignedInteger('daily_clicks')->default(0);
            $table->unsignedInteger('daily_contacts')->default(0);
            $table->unsignedBigInteger('daily_revenue')->default(0);
            $table->unsignedInteger('daily_new_contacts')->default(0);
            $table->unsignedInteger('daily_new_closed')->default(0);
            $table->unsignedInteger('daily_old_contacts')->default(0);
            $table->unsignedInteger('daily_old_closed')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'position_key', 'kpi_name'], 'kpi_catalog_company_position_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_catalog_items');
    }
};
