<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Operations\OrderInteractionLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderInteractionLockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_user_cannot_acquire_active_lock(): void
    {
        $a = User::factory()->create(['role' => UserRole::Warehouse, 'name' => 'Kho A']);
        $b = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale B']);
        $order = Order::query()->create([
            'order_code' => 'ORD-LOCK-1',
            'customer_name' => 'Customer',
            'customer_phone' => '0900000001',
            'delivery_status' => 'waiting_waybill',
            'data_arrived_at' => now(),
            'sale_user_id' => $b->id,
        ]);

        $service = app(OrderInteractionLockService::class);
        $first = $service->acquire($order, $a, 'desired_delivery');
        $this->assertNotEmpty($first['token']);

        try {
            $service->acquire($order, $b, 'desired_delivery');
            $this->fail('Expected HttpException 423');
        } catch (HttpException $exception) {
            $this->assertSame(423, $exception->getStatusCode());
            $this->assertStringContainsString('Kho A', $exception->getMessage());
        }
    }

    public function test_same_user_can_takeover_lock(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse, 'name' => 'Kho A']);
        $order = Order::query()->create([
            'order_code' => 'ORD-LOCK-2',
            'customer_name' => 'Customer',
            'customer_phone' => '0900000002',
            'delivery_status' => 'waiting_waybill',
            'data_arrived_at' => now(),
        ]);

        $service = app(OrderInteractionLockService::class);
        $first = $service->acquire($order, $user, 'care');
        $second = $service->acquire($order, $user, 'care');
        $this->assertSame($first['token'], $second['token']);
    }

    public function test_release_allows_other_user(): void
    {
        $a = User::factory()->create(['role' => UserRole::Warehouse]);
        $b = User::factory()->create(['role' => UserRole::Accounting]);
        $order = Order::query()->create([
            'order_code' => 'ORD-LOCK-3',
            'customer_name' => 'Customer',
            'customer_phone' => '0900000003',
            'delivery_status' => 'waiting_waybill',
            'data_arrived_at' => now(),
        ]);

        $service = app(OrderInteractionLockService::class);
        $lock = $service->acquire($order, $a, 'delivery');
        $service->release($order, $a, $lock['token']);
        $next = $service->acquire($order, $b, 'delivery');
        $this->assertNotEmpty($next['token']);
    }
}
