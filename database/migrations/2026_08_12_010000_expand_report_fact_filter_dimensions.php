<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_daily_order_facts')) {
            Schema::table('report_daily_order_facts', function (Blueprint $table): void {
                $this->stringIfMissing($table, 'customer_type', 20, 'closing_status');
                $this->stringIfMissing($table, 'duplicate_phone_status', 20, 'customer_type');
                $this->stringIfMissing($table, 'shipping_method', 60, 'shipping_provider');
                $this->stringIfMissing($table, 'operation_result', 80, 'operation_stage');
                $this->stringIfMissing($table, 'warehouse_care_status', 60, 'warehouse_id');
                $this->stringIfMissing($table, 'printed_status', 20, 'warehouse_care_status');
                $this->stringIfMissing($table, 'deposit_status', 20, 'printed_status');
            });

            Schema::table('report_daily_order_facts', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'report_order_company_day_basis_team_idx', ['company_id', 'metric_date', 'date_basis', 'team_id']);
                $this->indexIfMissing($table, 'report_order_company_day_basis_status_idx', ['company_id', 'metric_date', 'date_basis', 'delivery_status', 'reconciliation_status']);
                $this->indexIfMissing($table, 'report_order_company_day_basis_stage_idx', ['company_id', 'metric_date', 'date_basis', 'operation_stage', 'operation_result']);
                $this->indexIfMissing($table, 'report_order_company_day_basis_customer_idx', ['company_id', 'metric_date', 'date_basis', 'customer_type', 'duplicate_phone_status']);
                $this->indexIfMissing($table, 'report_order_company_day_basis_wh_care_idx', ['company_id', 'metric_date', 'date_basis', 'warehouse_id', 'warehouse_care_status']);
                $this->indexIfMissing($table, 'report_order_company_day_basis_ship_idx', ['company_id', 'metric_date', 'date_basis', 'shipping_method', 'shipping_provider']);
            });
        }

        if (Schema::hasTable('report_daily_product_facts')) {
            Schema::table('report_daily_product_facts', function (Blueprint $table): void {
                if (! Schema::hasColumn('report_daily_product_facts', 'parent_product_id')) {
                    $table->unsignedBigInteger('parent_product_id')->default(0)->after('product_id');
                }
            });

            Schema::table('report_daily_product_facts', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'report_product_company_day_basis_parent_idx', ['company_id', 'metric_date', 'date_basis', 'parent_product_id']);
                $this->indexIfMissing($table, 'report_product_company_day_basis_source_idx', ['company_id', 'metric_date', 'date_basis', 'marketing_source_id']);
                $this->indexIfMissing($table, 'report_product_company_day_basis_sale_idx', ['company_id', 'metric_date', 'date_basis', 'sale_user_id']);
                $this->indexIfMissing($table, 'report_product_company_day_basis_marketer_idx', ['company_id', 'metric_date', 'date_basis', 'marketer_user_id']);
            });
        }

        if (Schema::hasTable('report_daily_lead_facts')) {
            Schema::table('report_daily_lead_facts', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'report_lead_company_day_team_idx', ['company_id', 'metric_date', 'team_id']);
                $this->indexIfMissing($table, 'report_lead_company_day_status_idx', ['company_id', 'metric_date', 'status', 'packet_type']);
                $this->indexIfMissing($table, 'report_lead_company_day_landing_idx', ['company_id', 'metric_date', 'landing_connection_id', 'landing_connection_source_id']);
            });
        }

        if (Schema::hasTable('report_daily_marketing_packet_facts')) {
            Schema::table('report_daily_marketing_packet_facts', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'report_mkt_packets_company_day_team_idx', ['company_id', 'metric_date', 'team_id']);
                $this->indexIfMissing($table, 'report_mkt_packets_company_day_channel_idx', ['company_id', 'metric_date', 'ad_channel', 'source_type']);
                $this->indexIfMissing($table, 'report_mkt_packets_company_day_utm_campaign_idx', ['company_id', 'metric_date', 'utm_campaign']);
                $this->indexIfMissing($table, 'report_mkt_packets_company_day_status_idx', ['company_id', 'metric_date', 'status']);
            });
        }

        // Live-window indexes: current day and unsupported detail filters still query base tables.
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'orders_report_company_arrival_filters_idx', ['company_id', 'data_arrived_at', 'delivery_status', 'warehouse_id']);
                $this->indexIfMissing($table, 'orders_report_company_closed_filters_idx', ['company_id', 'closed_at', 'delivery_status', 'sale_user_id']);
                $this->indexIfMissing($table, 'orders_report_company_assigned_filters_idx', ['company_id', 'assigned_at', 'sale_user_id', 'operation_stage']);
                $this->indexIfMissing($table, 'orders_report_company_wh_care_idx', ['company_id', 'warehouse_care_updated_at', 'warehouse_id', 'warehouse_care_status']);
                $this->indexIfMissing($table, 'orders_report_company_shipping_idx', ['company_id', 'shipping_provider', 'delivery_status']);
            });
        }

        if (Schema::hasTable('lead_ingestions')) {
            Schema::table('lead_ingestions', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'lead_ingestions_company_created_source_idx', ['company_id', 'created_at', 'marketing_source_id']);
                $this->indexIfMissing($table, 'lead_ingestions_company_created_landing_idx', ['company_id', 'created_at', 'landing_connection_id']);
                $this->indexIfMissing($table, 'lead_ingestions_company_created_status_idx', ['company_id', 'created_at', 'status']);
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'order_items_company_product_order_idx', ['company_id', 'product_id', 'order_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'orders' => [
                'orders_report_company_arrival_filters_idx',
                'orders_report_company_closed_filters_idx',
                'orders_report_company_assigned_filters_idx',
                'orders_report_company_wh_care_idx',
                'orders_report_company_shipping_idx',
            ],
            'lead_ingestions' => [
                'lead_ingestions_company_created_source_idx',
                'lead_ingestions_company_created_landing_idx',
                'lead_ingestions_company_created_status_idx',
            ],
            'order_items' => ['order_items_company_product_order_idx'],
            'report_daily_order_facts' => [
                'report_order_company_day_basis_team_idx',
                'report_order_company_day_basis_status_idx',
                'report_order_company_day_basis_stage_idx',
                'report_order_company_day_basis_customer_idx',
                'report_order_company_day_basis_wh_care_idx',
                'report_order_company_day_basis_ship_idx',
            ],
            'report_daily_product_facts' => [
                'report_product_company_day_basis_parent_idx',
                'report_product_company_day_basis_source_idx',
                'report_product_company_day_basis_sale_idx',
                'report_product_company_day_basis_marketer_idx',
            ],
            'report_daily_lead_facts' => [
                'report_lead_company_day_team_idx',
                'report_lead_company_day_status_idx',
                'report_lead_company_day_landing_idx',
            ],
            'report_daily_marketing_packet_facts' => [
                'report_mkt_packets_company_day_team_idx',
                'report_mkt_packets_company_day_channel_idx',
                'report_mkt_packets_company_day_utm_campaign_idx',
                'report_mkt_packets_company_day_status_idx',
            ],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
                foreach ($indexes as $index) {
                    if ($this->indexExists($table, $index)) {
                        $blueprint->dropIndex($index);
                    }
                }
            });
        }

        if (Schema::hasTable('report_daily_product_facts') && Schema::hasColumn('report_daily_product_facts', 'parent_product_id')) {
            Schema::table('report_daily_product_facts', function (Blueprint $table): void {
                $table->dropColumn('parent_product_id');
            });
        }

        if (Schema::hasTable('report_daily_order_facts')) {
            Schema::table('report_daily_order_facts', function (Blueprint $table): void {
                foreach (['customer_type', 'duplicate_phone_status', 'shipping_method', 'operation_result', 'warehouse_care_status', 'printed_status', 'deposit_status'] as $column) {
                    if (Schema::hasColumn('report_daily_order_facts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function stringIfMissing(Blueprint $table, string $column, int $length, string $after): void
    {
        if (! Schema::hasColumn('report_daily_order_facts', $column)) {
            $table->string($column, $length)->default('')->after($after);
        }
    }

    /** @param list<string> $columns */
    private function indexIfMissing(Blueprint $table, string $name, array $columns): void
    {
        $tableName = $table->getTable();
        if ($this->indexExists($tableName, $name)) {
            return;
        }

        try {
            $table->index($columns, $name);
        } catch (Throwable) {
            // Some MySQL versions/environments may already have an equivalent index.
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        if (! method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            return false;
        }

        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
