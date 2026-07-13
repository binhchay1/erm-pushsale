<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('company_subscription_histories', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('payment_code')->nullable()->index();
            $table->string('contract_type')->nullable()->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('paid_at')->nullable()->index();
            $table->unsignedSmallInteger('duration_months')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('work_shifts', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name');
            $table->time('from_hour')->nullable();
            $table->time('to_hour')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        $this->create('lead_distribution_rules', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name');
            $table->string('number_type')->default('new');
            $table->string('recipient_type')->default('sales');
            $table->string('allocation_method')->default('round_robin');
            $table->json('product_ids')->nullable();
            $table->json('sale_user_ids')->nullable();
            $table->json('care_user_ids')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('report_access_rules', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('team_ids')->nullable();
            $table->string('team_type')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        $this->create('care_distribution_rules', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('care_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quota')->default(0);
            $table->boolean('receive_data')->default(true)->index();
            $table->json('sale_team_ids')->nullable();
            $table->foreignId('warehouse_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'care_user_id']);
        });

        $this->create('operation_categories', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_start')->default(false);
            $table->boolean('is_pool')->default(false);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        $this->create('operation_workflows', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('from_operation_category_id')->nullable()->constrained('operation_categories')->nullOnDelete();
            $table->string('condition_type')->nullable();
            $table->string('operation_result')->nullable();
            $table->foreignId('to_operation_category_id')->nullable()->constrained('operation_categories')->nullOnDelete();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('discount_cod_rules', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->unsignedBigInteger('order_from')->default(0)->index();
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->string('calculation_type')->default('fixed');
            $table->unsignedBigInteger('cod_from')->nullable();
            $table->unsignedBigInteger('cod_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('phone_blacklists', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('phone', 32)->index();
            $table->text('reason')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('creation_type')->default('manual');
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'phone']);
        });

        $this->create('seeding_phone_numbers', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('phone', 32)->index();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'phone']);
        });

        $this->create('customer_care_campaigns', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name');
            $table->json('customer_condition')->nullable();
            $table->unsignedInteger('repeat_days')->default(0);
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('warehouse_vouchers', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('type')->default('inbound')->index();
            $table->date('document_date')->nullable()->index();
            $table->string('partner')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        $this->create('warehouse_voucher_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('document_quantity')->default(0);
            $table->integer('quantity')->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->string('batch_code')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('location_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $this->create('warehouse_incident_reports', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->date('document_date')->nullable()->index();
            $table->string('carrier')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('product_count')->default(0);
            $table->string('status')->default('draft')->index();
            $table->text('note')->nullable();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('expense_groups', function (Blueprint $table): void {
            $table->id(); $this->tenant($table); $table->string('name');
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        $this->create('expense_categories', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('expense_group_id')->nullable()->constrained('expense_groups')->nullOnDelete();
            $table->string('name');
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'expense_group_id', 'name']);
        });
        $this->create('expense_units', function (Blueprint $table): void {
            $table->id(); $this->tenant($table); $table->string('name');
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        $this->create('expenses', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name');
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->foreignId('expense_group_id')->nullable()->constrained('expense_groups')->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('expense_unit_id')->nullable()->constrained('expense_units')->nullOnDelete();
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->decimal('quantity', 14, 2)->default(1);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('invoice_number')->nullable()->index();
            $table->text('note')->nullable();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('electronic_invoice_jobs', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('code_type')->nullable()->index();
            $table->string('process_type')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->text('note')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('completed')->default(false)->index();
            $table->string('batch_id')->nullable()->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
        });

        $this->create('product_categories', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->string('name'); $table->unsignedInteger('sort_order')->default(0); $table->boolean('is_active')->default(true);
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        $this->create('product_attributes', function (Blueprint $table): void {
            $table->id(); $this->tenant($table); $table->string('name'); $table->boolean('is_active')->default(true);
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        $this->create('product_attribute_values', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->unsignedInteger('sort_order')->default(0);
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'product_attribute_id', 'name'], 'pav_company_attribute_name_unique');
        });
        $this->create('product_category_product', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_category_id']);
        });
        $this->create('product_attribute_value_product', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_attribute_value_id'], 'pav_product_primary');
        });
        $this->create('product_combo_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('combo_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->timestamps();
            $table->unique(['combo_product_id', 'component_product_id']);
        });

        $this->create('monthly_kpi_plans', function (Blueprint $table): void {
            $table->id(); $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index(); $table->unsignedTinyInteger('month')->index();
            $table->string('kpi_name')->default('KPI tháng');
            $table->unsignedBigInteger('budget')->default(0); $table->unsignedInteger('clicks_target')->default(0);
            $table->unsignedInteger('contacts_target')->default(0); $table->unsignedBigInteger('revenue_target')->default(0);
            $table->unsignedInteger('new_contacts_target')->default(0); $table->unsignedInteger('new_closed_target')->default(0);
            $table->unsignedInteger('old_contacts_target')->default(0); $table->unsignedInteger('old_closed_target')->default(0);
            $table->decimal('bonus_percent', 8, 2)->default(0); $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedInteger('working_days')->default(26); $table->unsignedInteger('actual_days')->default(0);
            $table->boolean('locked')->default(false)->index();
            $this->audit($table); $table->softDeletes(); $table->timestamps();
            $table->unique(['company_id', 'user_id', 'year', 'month'], 'monthly_kpi_company_user_period_unique');
        });

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'unit')) $table->string('unit')->nullable()->after('sku');
                if (! Schema::hasColumn('products', 'cost_price')) $table->unsignedBigInteger('cost_price')->default(0)->after('unit_price');
                if (! Schema::hasColumn('products', 'vat_percent')) $table->decimal('vat_percent', 5, 2)->default(0)->after('cost_price');
                if (! Schema::hasColumn('products', 'vat_code')) $table->string('vat_code')->nullable()->after('vat_percent');
                if (! Schema::hasColumn('products', 'weight_grams')) $table->unsignedInteger('weight_grams')->default(0)->after('vat_code');
                if (! Schema::hasColumn('products', 'available_marketing')) $table->boolean('available_marketing')->default(true)->after('is_active');
                if (! Schema::hasColumn('products', 'available_sale')) $table->boolean('available_sale')->default(true)->after('available_marketing');
                if (! Schema::hasColumn('products', 'available_care')) $table->boolean('available_care')->default(true)->after('available_sale');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'product_combo_items', 'product_attribute_value_product', 'product_category_product', 'monthly_kpi_plans',
            'product_attribute_values', 'product_attributes', 'product_categories', 'electronic_invoice_jobs',
            'expenses', 'expense_units', 'expense_categories', 'expense_groups', 'warehouse_incident_reports',
            'warehouse_voucher_lines', 'warehouse_vouchers', 'customer_care_campaigns', 'seeding_phone_numbers',
            'phone_blacklists', 'discount_cod_rules', 'operation_workflows', 'operation_categories',
            'care_distribution_rules', 'report_access_rules', 'lead_distribution_rules', 'work_shifts',
            'company_subscription_histories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function create(string $name, callable $callback): void
    {
        if (! Schema::hasTable($name)) Schema::create($name, $callback);
    }

    private function tenant(Blueprint $table): void
    {
        $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    }

    private function audit(Blueprint $table): void
    {
        $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    }
};
