<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Webhook NetShip match theo customerCode (= partner_order_id) không kèm provider.
            $table->index('partner_order_id', 'shipments_partner_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_partner_order_id_index');
        });
    }
};
