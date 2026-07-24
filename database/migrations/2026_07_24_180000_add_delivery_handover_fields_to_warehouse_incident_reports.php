<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_incident_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_incident_reports', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('carrier');
            }
            if (! Schema::hasColumn('warehouse_incident_reports', 'receiver_name')) {
                $table->string('receiver_name')->nullable()->after('sender_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_incident_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_incident_reports', 'receiver_name')) {
                $table->dropColumn('receiver_name');
            }
            if (Schema::hasColumn('warehouse_incident_reports', 'sender_name')) {
                $table->dropColumn('sender_name');
            }
        });
    }
};
