<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $this->addIndexIfMissing($table, ['company_id', 'sale_user_id', 'data_arrived_at'], 'orders_leader_sale_arrived_idx');
            $this->addIndexIfMissing($table, ['company_id', 'sale_user_id', 'assigned_at'], 'orders_leader_sale_assigned_idx');
            $this->addIndexIfMissing($table, ['company_id', 'sale_user_id', 'closed_at'], 'orders_leader_sale_closed_idx');
            $this->addIndexIfMissing($table, ['company_id', 'operation_stage', 'sale_user_id'], 'orders_leader_stage_sale_idx');
            $this->addIndexIfMissing($table, ['company_id', 'team_id', 'data_arrived_at'], 'orders_leader_team_arrived_idx');
            $this->addIndexIfMissing($table, ['company_id', 'is_returning_customer', 'sale_user_id'], 'orders_leader_returning_sale_idx');
            $this->addIndexIfMissing($table, ['company_id', 'is_duplicate_phone', 'sale_user_id'], 'orders_leader_duplicate_sale_idx');
            $this->addIndexIfMissing($table, ['company_id', 'delivery_status', 'sale_user_id'], 'orders_leader_delivery_sale_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'orders_leader_sale_arrived_idx',
                'orders_leader_sale_assigned_idx',
                'orders_leader_sale_closed_idx',
                'orders_leader_stage_sale_idx',
                'orders_leader_team_arrived_idx',
                'orders_leader_returning_sale_idx',
                'orders_leader_duplicate_sale_idx',
                'orders_leader_delivery_sale_idx',
            ] as $name) {
                try {
                    $table->dropIndex($name);
                } catch (\Throwable) {
                    // Index may already be absent on some environments.
                }
            }
        });
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(Blueprint $table, array $columns, string $name): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        $indexes = method_exists($sm, 'getIndexes') ? $sm->getIndexes('orders') : [];
        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === $name) {
                return;
            }
        }

        try {
            $table->index($columns, $name);
        } catch (\Throwable) {
            // Ignore duplicate-index races across environments.
        }
    }
};
