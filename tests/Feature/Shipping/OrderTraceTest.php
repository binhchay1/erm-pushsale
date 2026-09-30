<?php

namespace Tests\Feature\Shipping;

use App\Enums\UserRole;
use App\Jobs\Orders\RecordOrderTraceJob;
use App\Models\Order;
use App\Models\OrderTrace;
use App\Models\User;
use App\Services\Orders\OrderTracePublisher;
use App\Services\Orders\OrderTraceSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_only_queues_until_the_trace_worker_runs(): void
    {
        $order = $this->order();
        app(OrderTracePublisher::class)->publish([
            'stage' => 'landing_received',
            'source' => 'landing',
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'phone' => '0901111222',
            'status_code' => 'processed',
            'summary' => 'Trace KH · processed',
            'dedupe_key' => 'landing:1:processed',
        ]);

        $this->assertSame(0, OrderTrace::query()->count());
        Queue::assertPushed(RecordOrderTraceJob::class);

        Queue::pushed(RecordOrderTraceJob::class)->each(fn (RecordOrderTraceJob $job) => $job->handle());

        $this->assertSame(1, OrderTrace::query()->count());
        Queue::pushed(RecordOrderTraceJob::class)->each(fn (RecordOrderTraceJob $job) => $job->handle());
        $this->assertSame(1, OrderTrace::query()->count());
    }

    public function test_search_shows_landing_netship_and_webhook_for_one_order(): void
    {
        $order = $this->order();
        $publisher = app(OrderTracePublisher::class);
        $publisher->publish([
            'stage' => 'landing_received',
            'source' => 'landing',
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'phone' => '0901111222',
            'dedupe_key' => 'landing:trace',
        ]);
        $publisher->publish([
            'stage' => 'gateway_response',
            'source' => 'netship',
            'action' => 'create_order',
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'external_code' => 'GYRDTRACE',
            'gateway_order_id' => '3469999',
            'dedupe_key' => 'gw:trace',
        ]);
        $publisher->publish([
            'stage' => 'webhook_applied',
            'source' => 'netship',
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'gateway_order_id' => '3469999',
            'status_code' => '2',
            'summary' => 'Đang giao hàng · matched',
            'dedupe_key' => 'wh:trace',
        ]);

        Queue::pushed(RecordOrderTraceJob::class)->each(fn (RecordOrderTraceJob $job) => $job->handle());

        $byPhone = app(OrderTraceSearch::class)->search('0901111222');
        $this->assertSame(
            ['landing_received', 'gateway_response', 'webhook_applied'],
            array_column($byPhone['events'], 'stage'),
        );

        $byBill = app(OrderTraceSearch::class)->search('GYRDTRACE');
        $this->assertCount(3, $byBill['events']);
        $this->assertSame('PS-TRACE-1', $byBill['orders'][0]['orderCode']);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->get('/admin/shipping/order-traces?q=3469999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Shipping/OrderTraces')
                ->has('events', 3)
                ->where('q', '3469999'));
    }

    private function order(): Order
    {
        return Order::query()->create([
            'order_code' => 'PS-TRACE-1',
            'customer_name' => 'Trace KH',
            'customer_phone' => '0901111222',
            'tracking_number' => 'GYRDTRACE',
            'total' => 10_000,
        ]);
    }
}
