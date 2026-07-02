<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->foreignId('approved_by_user_id')->nullable()->after('is_approved')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->string('rejection_reason')->nullable()->after('rejected_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('manager_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
