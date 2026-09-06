<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop = đơn vị con trong Company. Backfill 1 shop mặc định / company,
 * gắn shop_id cho bảng vận hành, gán user vào shop mặc định.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SHOP_SCOPED_TABLES = [
        'orders',
        'lead_ingestions',
        'warehouses',
        'products',
        'marketing_sources',
        'landing_connections',
        'teams',
    ];

    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 80);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('shop_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('default_shop_id')->nullable()->after('company_id')->constrained('shops')->nullOnDelete();
        });

        foreach (self::SHOP_SCOPED_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'shop_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $after = Schema::hasColumn($tableName, 'company_id') ? 'company_id' : null;
                $column = $table->foreignId('shop_id')->nullable();
                if ($after) {
                    $column->after($after);
                }
                $column->constrained('shops')->restrictOnDelete();
            });
        }

        $this->backfillDefaultShops();
        // Giữ shop_id nullable ở schema: row legacy / job chưa có context vẫn ghi được,
        // app auto-fill qua BelongsToShop khi có ShopContext.
    }

    public function down(): void
    {
        foreach (self::SHOP_SCOPED_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'shop_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('shop_id');
            });
        }

        if (Schema::hasColumn('users', 'default_shop_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('default_shop_id');
            });
        }

        Schema::dropIfExists('shop_user');
        Schema::dropIfExists('shops');
    }

    private function backfillDefaultShops(): void
    {
        $now = now();

        $companies = DB::table('companies')->select(['id', 'name'])->orderBy('id')->get();

        foreach ($companies as $company) {
            $shopId = DB::table('shops')->insertGetId([
                'company_id' => $company->id,
                'name' => 'Cửa hàng chính',
                'code' => 'main',
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::SHOP_SCOPED_TABLES as $tableName) {
                if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'shop_id')) {
                    continue;
                }

                DB::table($tableName)
                    ->where('company_id', $company->id)
                    ->whereNull('shop_id')
                    ->update(['shop_id' => $shopId]);
            }

            $userIds = DB::table('users')
                ->where('company_id', $company->id)
                ->pluck('id');

            foreach ($userIds as $userId) {
                DB::table('shop_user')->insertOrIgnore([
                    'shop_id' => $shopId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('users')
                ->where('company_id', $company->id)
                ->whereNull('default_shop_id')
                ->update(['default_shop_id' => $shopId]);
        }
    }
};
