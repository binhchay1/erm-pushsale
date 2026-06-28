<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gán toàn bộ dữ liệu hiện có (trước khi có multi-tenant) vào 1 công ty mặc định,
 * để các tài khoản cũ vẫn đăng nhập & dùng được mà không cần seed lại.
 * Cài mới (DB rỗng) sẽ bỏ qua — seeder sẽ tự tạo công ty demo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'teams',
        'orders',
        'order_items',
        'lead_ingestions',
        'marketing_sources',
        'products',
        'warehouses',
        'warehouse_inventories',
        'warehouse_inventory_movements',
        'integration_connections',
        'shipping_partner_connections',
        'shipments',
        'failed_partner_orders',
        'user_notifications',
        'shipping_webhook_events',
        'shipping_api_logs',
    ];

    public function up(): void
    {
        $hasLegacyUsers = DB::table('users')
            ->whereNull('company_id')
            ->where('is_platform_admin', false)
            ->exists();

        if (! $hasLegacyUsers) {
            return;
        }

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'ERM SaleOps (Nội bộ)',
            'slug' => 'internal',
            'status' => 'active',
            'plan' => 'internal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
            }
        }

        DB::table('users')
            ->whereNull('company_id')
            ->where('is_platform_admin', false)
            ->update(['company_id' => $companyId]);

        $owner = DB::table('users')
            ->where('company_id', $companyId)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update(['is_owner' => true]);
            DB::table('companies')->where('id', $companyId)->update(['owner_user_id' => $owner->id]);
        }
    }

    public function down(): void
    {
        // Không revert dữ liệu backfill.
    }
};
