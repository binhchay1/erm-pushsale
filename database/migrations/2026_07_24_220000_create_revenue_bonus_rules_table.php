<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('revenue_bonus_rules')) {
            return;
        }

        Schema::create('revenue_bonus_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position_key', 40)->default('marketing')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->unsignedBigInteger('revenue_from')->default(0);
            $table->unsignedBigInteger('revenue_to')->default(0);
            $table->decimal('bonus_percent', 8, 2)->default(0);
            $table->unsignedBigInteger('bonus_amount')->default(0);
            $table->boolean('locked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'position_key', 'year', 'month'], 'revenue_bonus_rules_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_bonus_rules');
    }
};
