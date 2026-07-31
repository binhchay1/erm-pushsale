<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_connections')) {
            return;
        }

        Schema::table('partner_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_connections', 'landing_connection_id')) {
                $table->foreignId('landing_connection_id')
                    ->nullable()
                    ->after('marketing_source_id')
                    ->constrained('landing_connections')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('partner_connections', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('access_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_connections')) {
            return;
        }

        Schema::table('partner_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_connections', 'landing_connection_id')) {
                $table->dropConstrainedForeignId('landing_connection_id');
            }
            if (Schema::hasColumn('partner_connections', 'public_token')) {
                $table->dropColumn('public_token');
            }
        });
    }
};
