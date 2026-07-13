<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_page_mappings')) {
            Schema::create('facebook_page_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('page_id');
                $table->string('page_name');
                $table->string('creator_name')->nullable();
                $table->foreignId('marketer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'page_id']);
            });
        }

        if (! Schema::hasTable('partner_connections')) {
            Schema::create('partner_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('partner_type')->default('api')->index();
                $table->string('endpoint_url')->nullable();
                $table->text('access_token')->nullable();
                $table->foreignId('marketing_source_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
                $table->foreignId('marketer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('ad_channel')->nullable();
                $table->string('sale_priority')->default('round_robin');
                $table->boolean('manual_import')->default(false);
                $table->boolean('is_approved')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_connections');
        Schema::dropIfExists('facebook_page_mappings');
    }
};
