<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->foreignId('marketer_user_id')
                ->nullable()
                ->after('product_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('contacts');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketer_user_id');
            $table->dropColumn('is_active');
        });
    }
};
