<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('landing_upsell_hold_until')->nullable()->after('data_arrived_at');
            $table->boolean('landing_upsell_locked')->default(false)->after('landing_upsell_hold_until');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['landing_upsell_hold_until', 'landing_upsell_locked']);
        });
    }
};
