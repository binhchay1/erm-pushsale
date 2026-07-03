<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Bộ quyền mặc định của phòng ban: { area: view|full }. Dùng để auto-tick khi gán nhân viên.
            $table->json('permissions')->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
