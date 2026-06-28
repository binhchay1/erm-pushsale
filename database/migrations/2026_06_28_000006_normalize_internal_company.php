<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->whereIn('slug', ['default', 'cong-ty-demo'])
            ->update([
                'slug' => 'internal',
                'name' => 'ERM SaleOps (Nội bộ)',
                'plan' => 'internal',
            ]);
    }

    public function down(): void
    {
        //
    }
};
