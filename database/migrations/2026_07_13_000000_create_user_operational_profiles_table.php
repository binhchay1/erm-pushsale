<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_operational_profiles')) {
            return;
        }

        Schema::create('user_operational_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('employee_code', 60)->nullable()->index();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->boolean('receive_data')->default(true)->index();
            $table->unsignedBigInteger('work_shift_id')->nullable()->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id', 'uop_company_fk')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id', 'uop_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('work_shift_id', 'uop_shift_fk')->references('id')->on('work_shifts')->nullOnDelete();
            $table->foreign('updated_by_user_id', 'uop_updated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['company_id', 'employee_code'], 'uop_company_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_operational_profiles');
    }
};
