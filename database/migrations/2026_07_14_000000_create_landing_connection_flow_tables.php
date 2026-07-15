<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_source_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
            $table->string('name');
            $table->foreignId('marketer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('connection_type', 24)->default('landing')->index();
            $table->string('ad_channel', 64)->nullable()->index();
            $table->string('allocation_method', 24)->default('inherit')->index();
            $table->string('public_token', 48)->unique();
            $table->text('success_url')->nullable();
            $table->boolean('manual_import')->default(false);
            $table->boolean('is_approved')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'marketing_source_id']);
            $table->index(['company_id', 'marketer_user_id', 'is_active'], 'landing_connections_company_marketer_active_idx');
        });

        Schema::create('landing_connection_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_connection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_type', 24)->default('main')->index();
            $table->text('source_url');
            $table->text('redirect_url')->nullable();
            $table->string('public_token', 48)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['landing_connection_id', 'source_type', 'sort_order'], 'landing_sources_connection_type_sort_idx');
        });

        Schema::create('landing_connection_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_connection_source_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('item_type', 24)->default('product')->index();
            $table->string('external_field')->nullable();
            $table->string('external_value')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_override')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['landing_connection_id', 'landing_connection_source_id'], 'landing_products_connection_source_idx');
        });

        Schema::create('landing_connection_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['landing_connection_id', 'user_id']);
            $table->index(['landing_connection_id', 'is_active', 'priority'], 'landing_sales_connection_active_priority_idx');
        });

        Schema::table('landing_sessions', function (Blueprint $table): void {
            $table->foreignId('landing_connection_id')->nullable()->after('marketing_source_id')
                ->constrained('landing_connections')->nullOnDelete();
            $table->foreignId('landing_connection_source_id')->nullable()->after('landing_connection_id')
                ->constrained('landing_connection_sources')->nullOnDelete();
            $table->index(['landing_connection_id', 'customer_phone', 'created_at'], 'landing_sessions_connection_phone_created_idx');
        });

        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->foreignId('landing_connection_id')->nullable()->after('marketing_source_id')
                ->constrained('landing_connections')->nullOnDelete();
            $table->foreignId('landing_connection_source_id')->nullable()->after('landing_connection_id')
                ->constrained('landing_connection_sources')->nullOnDelete();
            $table->index(['landing_connection_id', 'created_at'], 'lead_ingestions_landing_connection_created_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('landing_connection_id')->nullable()->after('marketing_source_id')
                ->constrained('landing_connections')->nullOnDelete();
            $table->foreignId('landing_connection_source_id')->nullable()->after('landing_connection_id')
                ->constrained('landing_connection_sources')->nullOnDelete();
            $table->index(['landing_connection_id', 'created_at'], 'orders_landing_connection_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_landing_connection_created_idx');
            $table->dropConstrainedForeignId('landing_connection_source_id');
            $table->dropConstrainedForeignId('landing_connection_id');
        });

        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->dropIndex('lead_ingestions_landing_connection_created_idx');
            $table->dropConstrainedForeignId('landing_connection_source_id');
            $table->dropConstrainedForeignId('landing_connection_id');
        });

        Schema::table('landing_sessions', function (Blueprint $table): void {
            $table->dropIndex('landing_sessions_connection_phone_created_idx');
            $table->dropConstrainedForeignId('landing_connection_source_id');
            $table->dropConstrainedForeignId('landing_connection_id');
        });

        Schema::dropIfExists('landing_connection_sales');
        Schema::dropIfExists('landing_connection_products');
        Schema::dropIfExists('landing_connection_sources');
        Schema::dropIfExists('landing_connections');
    }
};
