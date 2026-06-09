<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_inventory_movements', function (Blueprint $table) {
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
        });
    }
};
