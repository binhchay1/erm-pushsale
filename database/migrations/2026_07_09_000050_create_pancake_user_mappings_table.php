<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pancake_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->string('shop_id', 80)->nullable()->index();
            $table->string('page_id', 80)->nullable()->index();
            $table->string('pancake_user_key', 191);
            $table->string('pancake_user_id', 120)->nullable();
            $table->string('pancake_user_email', 191)->nullable();
            $table->string('pancake_user_name', 191)->nullable();
            $table->foreignId('internal_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'integration_connection_id', 'shop_id', 'page_id', 'pancake_user_key'],
                'pancake_user_mapping_unique'
            );
            $table->index(['company_id', 'internal_user_id', 'is_active'], 'pancake_user_mapping_internal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_user_mappings');
    }
};
