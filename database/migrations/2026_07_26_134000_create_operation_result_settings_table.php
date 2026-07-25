<?php

use App\Enums\OperationResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_result_settings')) {
            Schema::create('operation_result_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('value', 80);
                $table->string('label');
                $table->unsignedInteger('legacy_id')->nullable()->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('closes_order')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['company_id', 'value'], 'operation_result_settings_company_value_unique');
            });
        }

        $companies = Schema::hasTable('companies') ? DB::table('companies')->pluck('id')->all() : [];
        if ($companies === []) {
            return;
        }

        $defaults = collect(OperationResult::selectableOptions())
            ->values()
            ->map(fn (array $item, int $index): array => [
                'value' => (string) $item['value'],
                'label' => (string) $item['label'],
                'legacy_id' => 109117 + $index,
                'sort_order' => $index + 1,
                'closes_order' => (string) $item['value'] === OperationResult::ClosedSuccess->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        foreach ($companies as $companyId) {
            foreach ($defaults as $row) {
                DB::table('operation_result_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'value' => $row['value']],
                    ['company_id' => $companyId] + $row
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_result_settings');
    }
};
