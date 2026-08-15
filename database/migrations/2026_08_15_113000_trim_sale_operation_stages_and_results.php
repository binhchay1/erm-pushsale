<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback sale: bỏ Chăm sóc lần 1–3 khỏi quy trình active;
 * rút kết quả tác nghiệp về 9 option; giữ row lịch sử.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_categories')) {
            DB::table('operation_categories')
                ->where(function ($query): void {
                    $query->where('name', 'like', '%Chăm sóc%')
                        ->orWhere('name', 'like', '%Cham soc%')
                        ->orWhere('name', 'like', '%chăm sóc%');
                })
                ->update(['is_active' => false]);

            DB::table('operation_categories')
                ->where(function ($query): void {
                    $query->where('is_start', true)
                        ->orWhere('name', 'like', '%Gọi lần 1%')
                        ->orWhere('name', 'like', '%Goi lan 1%');
                })
                ->update(['name' => 'Khách mới']);
        }

        if (Schema::hasTable('operation_result_settings')) {
            $active = [
                'closed_success',
                'no_answer_auto',
                'busy',
                'callback_scheduled',
                'duplicate_number',
                'wrong_number',
                'subscriber_unavailable',
                'considering',
                'no_need',
            ];

            DB::table('operation_result_settings')
                ->whereNotIn('value', $active)
                ->update(['is_active' => false]);

            DB::table('operation_result_settings')
                ->whereIn('value', $active)
                ->update(['is_active' => true]);
        }

        if (Schema::hasTable('operation_workflows') && Schema::hasTable('operation_categories')) {
            $careIds = DB::table('operation_categories')
                ->where(function ($query): void {
                    $query->where('name', 'like', '%Chăm sóc%')
                        ->orWhere('name', 'like', '%Cham soc%')
                        ->orWhere('name', 'like', '%chăm sóc%');
                })
                ->pluck('id');

            if ($careIds->isNotEmpty()) {
                DB::table('operation_workflows')
                    ->where(function ($query) use ($careIds): void {
                        $query->whereIn('to_operation_category_id', $careIds->all())
                            ->orWhereIn('from_operation_category_id', $careIds->all());
                    })
                    ->update(['is_active' => false]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible trim of active UX options; historical rows remain.
    }
};
