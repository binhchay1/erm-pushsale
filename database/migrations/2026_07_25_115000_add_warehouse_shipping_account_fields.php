<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        Schema::table('warehouses', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouses', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('code');
            }
            if (! Schema::hasColumn('warehouses', 'use_two_level_address')) {
                $table->boolean('use_two_level_address')->default(false)->after('address');
            }
            if (! Schema::hasColumn('warehouses', 'sender_registration_name')) {
                $table->string('sender_registration_name')->nullable()->after('manager_user_id');
            }
            if (! Schema::hasColumn('warehouses', 'sender_print_note')) {
                $table->text('sender_print_note')->nullable()->after('sender_registration_name');
            }
            if (! Schema::hasColumn('warehouses', 'default_delivery_provinces')) {
                $table->text('default_delivery_provinces')->nullable()->after('sender_print_note');
            }
            if (! Schema::hasColumn('warehouses', 'default_shipping_provider')) {
                $table->string('default_shipping_provider', 50)->nullable()->after('default_delivery_provinces');
            }
            if (! Schema::hasColumn('warehouses', 'default_shipping_service')) {
                $table->string('default_shipping_service', 80)->nullable()->after('default_shipping_provider');
            }
            if (! Schema::hasColumn('warehouses', 'shipping_account_settings')) {
                $table->json('shipping_account_settings')->nullable()->after('default_shipping_service');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        Schema::table('warehouses', function (Blueprint $table): void {
            foreach ([
                'shipping_account_settings',
                'default_shipping_service',
                'default_shipping_provider',
                'default_delivery_provinces',
                'sender_print_note',
                'sender_registration_name',
                'use_two_level_address',
                'sort_order',
            ] as $column) {
                if (Schema::hasColumn('warehouses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
