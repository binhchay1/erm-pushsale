<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_connections') && ! Schema::hasColumn('integration_connections', 'metadata')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('last_synced_at');
            });
        }

        if (Schema::hasTable('shipping_partner_connections') && ! Schema::hasColumn('shipping_partner_connections', 'metadata')) {
            Schema::table('shipping_partner_connections', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('last_synced_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('integration_connections') && Schema::hasColumn('integration_connections', 'metadata')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }

        if (Schema::hasTable('shipping_partner_connections') && Schema::hasColumn('shipping_partner_connections', 'metadata')) {
            Schema::table('shipping_partner_connections', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }
};
