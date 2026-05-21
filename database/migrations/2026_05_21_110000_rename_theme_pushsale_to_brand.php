<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_preferences')
            ->where('theme', 'pushsale')
            ->update(['theme' => 'brand']);
    }

    public function down(): void
    {
        DB::table('user_preferences')
            ->where('theme', 'brand')
            ->update(['theme' => 'pushsale']);
    }
};
