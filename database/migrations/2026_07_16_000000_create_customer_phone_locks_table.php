<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_phone_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_key', 30);
            $table->foreignId('owner_sale_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('active_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->string('lock_reason', 80)->nullable();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'phone_key'], 'customer_phone_locks_company_phone_unique');
            $table->index(['company_id', 'owner_sale_user_id', 'status'], 'customer_phone_locks_owner_status_idx');
            $table->index(['active_order_id', 'status'], 'customer_phone_locks_order_status_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'phone_lock_conflict')) {
                $table->boolean('phone_lock_conflict')->default(false)->after('is_duplicate_phone');
            }
            if (! Schema::hasColumn('orders', 'phone_lock_note')) {
                $table->string('phone_lock_note', 500)->nullable()->after('phone_lock_conflict');
            }
        });

        Schema::table('lead_ingestions', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_ingestions', 'phone_lock_conflict')) {
                $table->boolean('phone_lock_conflict')->default(false)->after('requires_review');
            }
            if (! Schema::hasColumn('lead_ingestions', 'phone_lock_owner_user_id')) {
                $table->foreignId('phone_lock_owner_user_id')->nullable()->after('phone_lock_conflict')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_ingestions', 'phone_lock_owner_user_id')) {
                $table->dropConstrainedForeignId('phone_lock_owner_user_id');
            }
            if (Schema::hasColumn('lead_ingestions', 'phone_lock_conflict')) {
                $table->dropColumn('phone_lock_conflict');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'phone_lock_note')) {
                $table->dropColumn('phone_lock_note');
            }
            if (Schema::hasColumn('orders', 'phone_lock_conflict')) {
                $table->dropColumn('phone_lock_conflict');
            }
        });

        Schema::dropIfExists('customer_phone_locks');
    }
};
