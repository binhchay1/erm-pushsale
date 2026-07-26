<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_post_mappings')) {
            Schema::create('facebook_post_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('facebook_page_mapping_id')->nullable()->constrained('facebook_page_mappings')->nullOnDelete();
                $table->string('page_id')->index();
                $table->string('page_name')->nullable();
                $table->string('post_id');
                $table->text('content')->nullable();
                $table->timestamp('posted_at')->nullable()->index();
                $table->boolean('is_used')->default(false)->index();
                $table->foreignId('landing_connection_id')->nullable()->constrained('landing_connections')->nullOnDelete();
                $table->string('status')->default('active')->index();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'post_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_post_mappings');
    }
};
