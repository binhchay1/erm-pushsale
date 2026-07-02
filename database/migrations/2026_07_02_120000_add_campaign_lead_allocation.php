<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->string('lead_allocation', 20)->default('inherit')->after('is_approved');
        });

        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->foreignId('marketing_source_id')->nullable()->after('company_id')
                ->constrained('marketing_sources')->nullOnDelete();
            $table->index(['marketing_source_id', 'status', 'created_at'], 'lead_ingestions_campaign_pool_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table) {
            $table->dropForeign(['marketing_source_id']);
            $table->dropIndex('lead_ingestions_campaign_pool_idx');
            $table->dropColumn('marketing_source_id');
        });

        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropColumn('lead_allocation');
        });
    }
};
