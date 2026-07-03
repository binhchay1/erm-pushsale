<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketing_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 64)->unique();
            $table->string('customer_phone', 20)->nullable();
            // open → khách đang thao tác; thankyou → đang xem upsale; closed → chốt phiên.
            $table->string('status', 20)->default('open');
            $table->foreignId('lead_ingestion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['marketing_source_id', 'customer_phone']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sessions');
    }
};
