<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_connections')) {
            return;
        }

        Schema::table('landing_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('landing_connections', 'budget_type')) {
                $table->string('budget_type', 16)->default('total')->after('allocation_method');
            }
            if (! Schema::hasColumn('landing_connections', 'budget_amount')) {
                $table->unsignedBigInteger('budget_amount')->default(0)->after('budget_type');
            }
            if (! Schema::hasColumn('landing_connections', 'budget_start_date')) {
                $table->date('budget_start_date')->nullable()->after('budget_amount');
            }
            if (! Schema::hasColumn('landing_connections', 'budget_end_date')) {
                $table->date('budget_end_date')->nullable()->after('budget_start_date');
            }
        });

        if (! $this->indexExists('landing_connections_company_budget_period_idx')) {
            Schema::table('landing_connections', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'budget_start_date', 'budget_end_date'],
                    'landing_connections_company_budget_period_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('landing_connections')) {
            return;
        }

        Schema::table('landing_connections', function (Blueprint $table): void {
            if ($this->indexExists('landing_connections_company_budget_period_idx')) {
                $table->dropIndex('landing_connections_company_budget_period_idx');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('landing_connections', 'budget_type') ? 'budget_type' : null,
                Schema::hasColumn('landing_connections', 'budget_amount') ? 'budget_amount' : null,
                Schema::hasColumn('landing_connections', 'budget_start_date') ? 'budget_start_date' : null,
                Schema::hasColumn('landing_connections', 'budget_end_date') ? 'budget_end_date' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function indexExists(string $name): bool
    {
        try {
            return collect(Schema::getIndexes('landing_connections'))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
        } catch (Throwable) {
            // Schema::getIndexes không có trên một số driver cũ. Việc tạo index sẽ
            // được thử ở lần migrate đầu tiên; rollback vẫn an toàn nhờ nhánh false.
            return false;
        }
    }
};
