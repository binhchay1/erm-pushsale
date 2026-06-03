<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::query()->create([
            'order_code' => 'ORD-DEL-1',
            'customer_name' => 'Test',
            'customer_phone' => '0900000001',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_admin_can_delete_product_without_children(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::query()->create([
            'name' => 'Test SP',
            'sku' => 'TEST-01',
            'unit_price' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_delete_warehouse_with_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $warehouse = Warehouse::query()->create(['name' => 'Kho test']);
        Order::query()->create([
            'order_code' => 'ORD-WH-1',
            'warehouse_id' => $warehouse->id,
            'customer_name' => 'Test',
            'customer_phone' => '0900000002',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.warehouses.destroy', $warehouse))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_sales_cannot_delete_order(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $order = Order::query()->create([
            'order_code' => 'ORD-DEL-2',
            'customer_name' => 'Test',
            'customer_phone' => '0900000003',
        ]);

        $this->actingAs($sales)
            ->delete(route('admin.orders.destroy', $order))
            ->assertForbidden();
    }
}
