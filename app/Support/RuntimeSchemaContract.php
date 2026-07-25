<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RuntimeSchemaContract
{
    /**
     * Repair non-destructive schema contracts that current business code depends on.
     *
     * This is intentionally narrow: it only restores columns that were removed by
     * older cleanup migrations but are now used again by real seeders/audits/pages.
     * It is safe to call before db:seed on staging because every operation is guarded
     * by hasTable/hasColumn checks.
     *
     * @return list<string>
     */
    public function ensure(): array
    {
        $changes = [];

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $column = $table->boolean('is_active')->default(true);
                if (Schema::hasColumn('users', 'is_platform_admin')) {
                    $column->after('is_platform_admin');
                }
                $table->index('is_active', 'users_is_active_idx');
            });
            $changes[] = 'users.is_active';
        }

        if (Schema::hasTable('integration_connections') && ! Schema::hasColumn('integration_connections', 'metadata')) {
            Schema::table('integration_connections', function (Blueprint $table): void {
                $column = $table->json('metadata')->nullable();
                if (Schema::hasColumn('integration_connections', 'last_synced_at')) {
                    $column->after('last_synced_at');
                }
            });
            $changes[] = 'integration_connections.metadata';
        }

        if (Schema::hasTable('shipping_partner_connections') && ! Schema::hasColumn('shipping_partner_connections', 'metadata')) {
            Schema::table('shipping_partner_connections', function (Blueprint $table): void {
                $column = $table->json('metadata')->nullable();
                if (Schema::hasColumn('shipping_partner_connections', 'last_synced_at')) {
                    $column->after('last_synced_at');
                }
            });
            $changes[] = 'shipping_partner_connections.metadata';
        }

        return $changes;
    }
}
