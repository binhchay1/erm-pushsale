<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Orders\OrderClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderClosingPrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_without_warehouse_throws_validation(): void
    {
        [$admin, $order] = $this->seedOpenOrder(withWarehouse: false);

        $this->expectException(ValidationException::class);

        app(OrderClosingService::class)->close($order, $admin, [
            'confirm_insufficient_stock' => true,
        ]);
    }

    public function test_close_without_product_qty_throws_validation(): void
    {
        [$admin, $order] = $this->seedOpenOrder(withWarehouse: true, withItem: false);

        $this->expectException(ValidationException::class);

        app(OrderClosingService::class)->close($order, $admin, [
            'warehouse_id' => $order->warehouse_id,
            'confirm_insufficient_stock' => true,
        ]);
    }

    /** @return array{0: User, 1: Order} */
    private function seedOpenOrder(bool $withWarehouse, bool $withItem = true): array
    {
        $company = Company::query()->create([
            'name' => 'Co',
            'slug' => 'co-'.uniqid(),
            'status' => Company::STATUS_ACTIVE,
        ]);
        $shop = Shop::query()->create([
            'company_id' => $company->id,
            'name' => 'Main',
            'code' => 'main-'.uniqid(),
            'is_active' => true,
        ]);
        $admin = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@saleops.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_platform_admin' => true,
        ]);

        $warehouseId = null;
        if ($withWarehouse) {
            $warehouseId = Warehouse::query()->create([
                'company_id' => $company->id,
                'shop_id' => $shop->id,
                'name' => 'WH',
                'address' => 'HN',
                'sort_order' => 0,
            ])->id;
        }

        $order = Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouseId,
            'sale_user_id' => $admin->id,
            'customer_name' => 'KH',
            'customer_phone' => '0912345678',
            'shipping_address' => 'HN',
        ]);

        if ($withItem) {
            $product = Product::query()->create([
                'company_id' => $company->id,
                'shop_id' => $shop->id,
                'name' => 'SP',
                'sku' => 'SKU-'.uniqid(),
                'price' => 1000,
                'is_active' => true,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'unit_price' => 1000,
            ]);
        }

        return [$admin, $order->fresh('items')];
    }
}
