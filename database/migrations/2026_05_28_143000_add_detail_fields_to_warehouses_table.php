<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
            $table->string('address')->nullable()->after('phone');
            $table->foreignId('manager_user_id')->nullable()->after('address')->constrained('users')->nullOnDelete();
            $table->string('vtp_code', 80)->nullable()->after('manager_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropColumn(['phone', 'address', 'vtp_code']);
        });
    }
};
