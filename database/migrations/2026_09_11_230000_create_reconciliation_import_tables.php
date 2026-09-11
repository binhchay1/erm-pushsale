<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_code', 64);
            $table->string('filename')->nullable();
            $table->boolean('is_ghtk')->default(false);
            $table->string('state', 30)->default('uploaded'); // uploaded|applied|cleared
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'batch_code'], 'recon_import_batches_company_code_uq');
            $table->index(['company_id', 'created_at'], 'recon_import_batches_company_created_idx');
        });

        Schema::create('reconciliation_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('reconciliation_import_batches')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_code', 80)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('reconciliation_status_raw', 120)->nullable();
            $table->string('reconciliation_status', 50)->nullable();
            $table->string('note', 2000)->nullable();
            $table->string('process_status', 30)->default('pending'); // pending|processed
            $table->string('result_status', 30)->default('pending'); // pending|success|error
            $table->string('message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'process_status', 'result_status'], 'recon_import_rows_batch_status_idx');
            $table->index(['batch_id', 'order_code'], 'recon_import_rows_batch_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_import_rows');
        Schema::dropIfExists('reconciliation_import_batches');
    }
};
