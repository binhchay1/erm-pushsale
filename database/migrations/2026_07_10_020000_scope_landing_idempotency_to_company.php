<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Các token/submission id do landing bên ngoài sinh ra chỉ duy nhất bên
         * trong một tenant. Scope unique theo company tránh hai doanh nghiệp vô
         * tình dùng cùng submission_id/session_id làm chặn dữ liệu của nhau.
         */
        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->dropUnique('lead_ingestions_platform_external_id_unique');
            $table->unique(
                ['company_id', 'platform', 'external_id'],
                'lead_ingestions_company_platform_external_unique',
            );
            $table->index(
                ['company_id', 'marketing_source_id', 'counts_as_lead', 'status', 'created_at'],
                'lead_ingestions_landing_packet_lookup_idx',
            );
        });

        Schema::table('landing_sessions', function (Blueprint $table): void {
            $table->dropUnique('landing_sessions_session_key_unique');
            $table->unique(
                ['company_id', 'session_key'],
                'landing_sessions_company_session_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('landing_sessions', function (Blueprint $table): void {
            $table->dropUnique('landing_sessions_company_session_unique');
            $table->unique('session_key', 'landing_sessions_session_key_unique');
        });

        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->dropIndex('lead_ingestions_landing_packet_lookup_idx');
            $table->dropUnique('lead_ingestions_company_platform_external_unique');
            $table->unique(
                ['platform', 'external_id'],
                'lead_ingestions_platform_external_id_unique',
            );
        });
    }
};
