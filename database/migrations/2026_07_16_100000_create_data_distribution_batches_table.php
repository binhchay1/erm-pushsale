<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_distribution_batches')) {
            Schema::create('data_distribution_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('filters')->nullable();
                $table->json('flags')->nullable();
                $table->unsignedInteger('total_contacts')->default(0);
                $table->unsignedInteger('allocated_contacts')->default(0);
                $table->string('status', 30)->default('running')->index();
                $table->json('line_stats')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'created_at'], 'data_distribution_company_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_distribution_batches');
    }
};
