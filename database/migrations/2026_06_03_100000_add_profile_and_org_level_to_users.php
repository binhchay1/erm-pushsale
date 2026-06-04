<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('avatar_path');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('org_level', 20)->nullable()->after('is_team_leader');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'phone', 'job_title', 'org_level']);
        });
    }
};
