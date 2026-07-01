<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('source', 20)->default('import');
            $table->string('settlement_code', 120);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('lines_total')->default(0);
            $table->unsignedInteger('lines_matched')->default(0);
            $table->unsignedInteger('lines_unmatched')->default(0);
            $table->unsignedBigInteger('cod_total')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'settlement_code', 'company_id'], 'carrier_settlement_batches_unique');
            $table->index(['provider', 'imported_at']);
        });

        Schema::create('carrier_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('carrier_settlement_batches')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('settlement_code', 120)->nullable();
            $table->string('transaction_code', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('partner_order_code', 120)->nullable();
            $table->unsignedBigInteger('cod_amount')->default(0);
            $table->unsignedBigInteger('carrier_fee')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->string('match_status', 30)->default('pending');
            $table->string('match_method', 40)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'settlement_code', 'tracking_number', 'transaction_code'],
                'carrier_settlement_lines_dedupe'
            );
            $table->index(['order_id', 'provider']);
            $table->index(['match_status', 'provider']);
            $table->index('tracking_number');
            $table->index('partner_order_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('settled_cod_amount')->default(0)->after('amount_to_collect');
            $table->timestamp('settlement_matched_at')->nullable()->after('settled_cod_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['settled_cod_amount', 'settlement_matched_at']);
        });

        Schema::dropIfExists('carrier_settlement_lines');
        Schema::dropIfExists('carrier_settlement_batches');
    }
};
