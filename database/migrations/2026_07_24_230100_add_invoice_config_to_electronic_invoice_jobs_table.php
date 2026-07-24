<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoice_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('electronic_invoice_jobs', 'electronic_invoice_config_id')) {
                $table->foreignId('electronic_invoice_config_id')->nullable()->after('order_id')->constrained('electronic_invoice_configs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoice_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('electronic_invoice_jobs', 'electronic_invoice_config_id')) {
                $table->dropConstrainedForeignId('electronic_invoice_config_id');
            }
        });
    }
};
