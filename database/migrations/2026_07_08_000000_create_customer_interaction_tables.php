<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_operation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('action', 50);
            $table->string('operation_stage_before', 40)->nullable();
            $table->string('operation_stage_after', 40)->nullable();
            $table->string('operation_result')->nullable();
            $table->timestamp('next_operation_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'order_id', 'created_at'], 'order_history_company_order_created_index');
            $table->index(['actor_user_id', 'created_at'], 'order_history_actor_created_index');
        });

        Schema::create('customer_internal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_role', 30)->nullable();
            $table->string('customer_phone', 30);
            $table->text('message');
            $table->timestamps();

            $table->index(['company_id', 'customer_phone', 'created_at'], 'customer_messages_phone_created_index');
            $table->index(['order_id', 'created_at'], 'customer_messages_order_created_index');
            $table->index(['author_user_id', 'created_at'], 'customer_messages_author_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_internal_messages');
        Schema::dropIfExists('order_operation_histories');
    }
};
