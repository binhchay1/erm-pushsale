<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            // Bật theo dõi phiên Landing bằng JS: gộp đơn + giữ số tới khi khách xong phiên.
            $table->boolean('js_tracking_enabled')->default(false)->after('lead_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropColumn('js_tracking_enabled');
        });
    }
};
