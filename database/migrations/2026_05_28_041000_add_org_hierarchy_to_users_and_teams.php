<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('teams')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role')->constrained('teams')->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_team_leader')->default(false)->after('manager_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropColumn('is_team_leader');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
