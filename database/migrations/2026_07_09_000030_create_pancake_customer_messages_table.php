<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pancake_customer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('page_id', 120)->nullable()->index();
            $table->string('conversation_id', 160)->index();
            $table->string('external_message_id', 180)->nullable();
            $table->string('direction', 20)->default('inbound')->index();
            $table->string('sender_id', 180)->nullable();
            $table->string('sender_name', 180)->nullable();
            $table->string('sender_type', 40)->nullable();
            $table->text('message')->nullable();
            $table->json('attachments')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['company_id', 'conversation_id', 'external_message_id'], 'pancake_customer_message_external_unique');
            $table->index(['company_id', 'conversation_id', 'sent_at'], 'pancake_customer_message_thread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_customer_messages');
    }
};
