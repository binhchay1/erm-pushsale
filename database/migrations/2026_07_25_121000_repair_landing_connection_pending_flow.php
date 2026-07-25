<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_sources')) {
            $this->addColumns('marketing_sources', [
                'company_id' => ['type' => 'foreign_id', 'after' => 'id'],
                'marketer_user_id' => ['type' => 'foreign_id', 'after' => 'product_id'],
                'created_by_user_id' => ['type' => 'foreign_id', 'after' => 'marketer_user_id'],
                'webhook_token' => ['type' => 'string64', 'after' => 'utm_campaign'],
                'is_active' => ['type' => 'bool_true', 'after' => 'contacts'],
                'is_approved' => ['type' => 'bool', 'after' => 'is_active'],
                'lead_allocation' => ['type' => 'string20', 'after' => 'is_approved'],
                'js_tracking_enabled' => ['type' => 'bool', 'after' => 'lead_allocation'],
                'approved_by_user_id' => ['type' => 'foreign_id', 'after' => 'is_approved'],
                'approved_at' => ['type' => 'timestamp', 'after' => 'approved_by_user_id'],
                'rejected_by_user_id' => ['type' => 'foreign_id', 'after' => 'approved_at'],
                'rejected_at' => ['type' => 'timestamp', 'after' => 'rejected_by_user_id'],
                'rejection_reason' => ['type' => 'string', 'after' => 'rejected_at'],
            ]);

            if (Schema::hasColumn('marketing_sources', 'product_id')) {
                try {
                    DB::statement('ALTER TABLE marketing_sources MODIFY product_id BIGINT UNSIGNED NULL');
                } catch (Throwable) {
                    // The runtime repair command repeats this best-effort change and the controller now catches DB errors.
                }
            }
        }

        if (Schema::hasTable('landing_connections')) {
            $this->addColumns('landing_connections', [
                'budget_type' => ['type' => 'string20', 'after' => 'allocation_method'],
                'budget_amount' => ['type' => 'bigint', 'after' => 'budget_type'],
                'budget_start_date' => ['type' => 'date', 'after' => 'budget_amount'],
                'budget_end_date' => ['type' => 'date', 'after' => 'budget_start_date'],
                'metadata' => ['type' => 'json', 'after' => 'is_active'],
                'approved_by_user_id' => ['type' => 'foreign_id', 'after' => 'metadata'],
                'approved_at' => ['type' => 'timestamp', 'after' => 'approved_by_user_id'],
                'updated_by_user_id' => ['type' => 'foreign_id', 'after' => 'created_by_user_id'],
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive repair migration.
    }

    /**
     * @param array<string, array{type: string, after?: string}> $columns
     */
    private function addColumns(string $tableName, array $columns): void
    {
        foreach ($columns as $column => $meta) {
            if (Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $column, $meta): void {
                $definition = match ($meta['type']) {
                    'foreign_id' => $table->unsignedBigInteger($column)->nullable(),
                    'string64' => $table->string($column, 64)->nullable(),
                    'string20' => $table->string($column, 20)->default($column === 'budget_type' ? 'total' : 'inherit'),
                    'string' => $table->string($column)->nullable(),
                    'timestamp' => $table->timestamp($column)->nullable(),
                    'date' => $table->date($column)->nullable(),
                    'bigint' => $table->unsignedBigInteger($column)->default(0),
                    'json' => $table->json($column)->nullable(),
                    'bool_true' => $table->boolean($column)->default(true),
                    'bool' => $table->boolean($column)->default(false),
                    default => $table->string($column)->nullable(),
                };

                $after = $meta['after'] ?? null;
                if ($after && Schema::hasColumn($tableName, $after)) {
                    $definition->after($after);
                }
            });
        }
    }
};
