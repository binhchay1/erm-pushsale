<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * marketing_sources.contacts là counter phục vụ card/list nhanh. Source
         * of truth vẫn là lead_ingestions.counts_as_lead. Reset và dựng lại để
         * loại toàn bộ packet upsell/follow-up từng bị cộng nhầm trong dữ liệu cũ.
         */
        DB::table('marketing_sources')->update(['contacts' => 0]);

        DB::table('lead_ingestions')
            ->selectRaw('marketing_source_id, COUNT(*) AS aggregate')
            ->whereNotNull('marketing_source_id')
            ->where('counts_as_lead', true)
            ->whereNotIn('status', ['duplicate', 'needs_review', 'failed'])
            ->groupBy('marketing_source_id')
            ->orderBy('marketing_source_id')
            ->cursor()
            ->each(function (object $row): void {
                DB::table('marketing_sources')
                    ->where('id', $row->marketing_source_id)
                    ->update(['contacts' => (int) $row->aggregate]);
            });
    }

    public function down(): void
    {
        // Không thể khôi phục counter sai trước đây một cách đáng tin cậy.
    }
};
