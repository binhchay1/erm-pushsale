<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_module_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module_code', 32);
            $table->string('status', 40)->nullable();
            $table->json('payload');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'module_code', 'created_at'], 'legacy_module_company_code_created_index');
            $table->index(['company_id', 'module_code', 'status'], 'legacy_module_company_code_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_module_records');
    }
};
