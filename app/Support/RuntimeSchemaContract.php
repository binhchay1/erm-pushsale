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




        if (Schema::hasTable('teams')) {
            if (! Schema::hasColumn('teams', 'leader_user_id')) {
                Schema::table('teams', function (Blueprint $table): void {
                    $table->foreignId('leader_user_id')->nullable()->after('type')->constrained('users')->nullOnDelete();
                });
                $changes[] = 'teams.leader_user_id';
            }

            // Một số file legacy/template cũ vẫn gọi leader_id. Giữ alias nullable
            // để các route cũ không 500, nhưng nghiệp vụ chuẩn vẫn dùng leader_user_id.
            if (! Schema::hasColumn('teams', 'leader_id')) {
                Schema::table('teams', function (Blueprint $table): void {
                    $table->unsignedBigInteger('leader_id')->nullable()->after('leader_user_id')->index('teams_leader_id_legacy_idx');
                });
                try {
                    \Illuminate\Support\Facades\DB::table('teams')
                        ->whereNotNull('leader_user_id')
                        ->update(['leader_id' => \Illuminate\Support\Facades\DB::raw('leader_user_id')]);
                } catch (\Throwable) {
                    // ignore backfill errors; the column itself is enough to prevent 500.
                }
                $changes[] = 'teams.leader_id';
            }
        }

        foreach ([
            'company_subscription_histories' => [
                'company_id' => ['type' => 'foreign_id', 'after' => 'id'],
                'payment_code' => ['type' => 'string', 'after' => 'company_id'],
                'contract_type' => ['type' => 'string', 'after' => 'payment_code'],
                'description' => ['type' => 'text', 'after' => 'contract_type'],
                'amount' => ['type' => 'bigint', 'after' => 'description'],
                'paid_at' => ['type' => 'timestamp', 'after' => 'amount'],
                'duration_months' => ['type' => 'smallint', 'after' => 'paid_at'],
                'expires_at' => ['type' => 'timestamp', 'after' => 'duration_months'],
                'created_by_user_id' => ['type' => 'foreign_id', 'after' => 'expires_at'],
                'updated_by_user_id' => ['type' => 'foreign_id', 'after' => 'created_by_user_id'],
            ],
            'phone_blacklists' => [
                'company_id' => ['type' => 'foreign_id', 'after' => 'id'],
                'phone' => ['type' => 'string32', 'after' => 'company_id'],
                'reason' => ['type' => 'text', 'after' => 'phone'],
                'order_id' => ['type' => 'foreign_id', 'after' => 'reason'],
                'creation_type' => ['type' => 'string', 'after' => 'order_id'],
                'created_by_user_id' => ['type' => 'foreign_id', 'after' => 'creation_type'],
                'updated_by_user_id' => ['type' => 'foreign_id', 'after' => 'created_by_user_id'],
            ],
            'monthly_kpi_plans' => [
                'company_id' => ['type' => 'foreign_id', 'after' => 'id'],
                'user_id' => ['type' => 'foreign_id', 'after' => 'company_id'],
                'year' => ['type' => 'smallint', 'after' => 'user_id'],
                'month' => ['type' => 'tinyint', 'after' => 'year'],
                'kpi_name' => ['type' => 'string', 'after' => 'month'],
                'budget' => ['type' => 'bigint', 'after' => 'kpi_name'],
                'clicks_target' => ['type' => 'uint', 'after' => 'budget'],
                'contacts_target' => ['type' => 'uint', 'after' => 'clicks_target'],
                'revenue_target' => ['type' => 'bigint', 'after' => 'contacts_target'],
                'new_contacts_target' => ['type' => 'uint', 'after' => 'revenue_target'],
                'new_closed_target' => ['type' => 'uint', 'after' => 'new_contacts_target'],
                'old_contacts_target' => ['type' => 'uint', 'after' => 'new_closed_target'],
                'old_closed_target' => ['type' => 'uint', 'after' => 'old_contacts_target'],
                'bonus_percent' => ['type' => 'decimal', 'after' => 'old_closed_target'],
                'base_salary' => ['type' => 'bigint', 'after' => 'bonus_percent'],
                'working_days' => ['type' => 'uint', 'after' => 'base_salary'],
                'actual_days' => ['type' => 'uint', 'after' => 'working_days'],
                'locked' => ['type' => 'bool', 'after' => 'actual_days'],
                'created_by_user_id' => ['type' => 'foreign_id', 'after' => 'locked'],
                'updated_by_user_id' => ['type' => 'foreign_id', 'after' => 'created_by_user_id'],
            ],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column => $meta) {
                if (Schema::hasColumn($tableName, $column)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($tableName, $column, $meta): void {
                    $type = $meta['type'];
                    $after = $meta['after'] ?? null;
                    $definition = match ($type) {
                        'foreign_id' => $table->unsignedBigInteger($column)->nullable(),
                        'string32' => $table->string($column, 32)->nullable(),
                        'string' => $table->string($column)->nullable(),
                        'text' => $table->text($column)->nullable(),
                        'bigint' => $table->unsignedBigInteger($column)->default(0),
                        'uint' => $table->unsignedInteger($column)->default(0),
                        'smallint' => $table->unsignedSmallInteger($column)->default(0),
                        'tinyint' => $table->unsignedTinyInteger($column)->default(0),
                        'timestamp' => $table->timestamp($column)->nullable(),
                        'bool' => $table->boolean($column)->default(false),
                        'decimal' => $table->decimal($column, 8, 2)->default(0),
                        default => $table->string($column)->nullable(),
                    };

                    if ($after && Schema::hasColumn($tableName, $after)) {
                        $definition->after($after);
                    }
                });
                $changes[] = $tableName.'.'.$column;
            }
        }

        if (Schema::hasTable('products')) {
            foreach ([
                'marketing_team_ids' => 'available_care',
                'marketing_user_ids' => 'marketing_team_ids',
                'sale_team_ids' => 'marketing_user_ids',
                'sale_user_ids' => 'sale_team_ids',
                'care_team_ids' => 'sale_user_ids',
                'care_user_ids' => 'care_team_ids',
            ] as $column => $after) {
                if (! Schema::hasColumn('products', $column)) {
                    Schema::table('products', function (Blueprint $table) use ($column, $after): void {
                        $definition = $table->json($column)->nullable();
                        if (Schema::hasColumn('products', $after)) {
                            $definition->after($after);
                        }
                    });
                    $changes[] = 'products.'.$column;
                }
            }
        }

        return $changes;
    }
}
