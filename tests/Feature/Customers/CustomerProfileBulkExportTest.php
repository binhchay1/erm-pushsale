<?php

namespace Tests\Feature\Customers;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileBulkExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $sale, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'company_id' => $sale->company_id,
            'order_code' => 'DH-EXPORT-1',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Nguyen Van A',
            'customer_phone' => '0901234567',
            'total' => 169000,
            'subtotal' => 169000,
            'discount' => 0,
            'assigned_at' => now(),
            'operation_stage' => 'call_2',
            'operation_result' => 'busy',
        ], $overrides));
    }

    public function test_export_selected_orders_as_csv_via_marketing_path(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'company_id' => $admin->company_id]);
        $order = $this->makeOrder($sale);

        $response = $this->actingAs($admin)->post('/admin/marketing/customers/export', [
            'variant' => 1,
            'ids' => [$order->id],
        ], [
            'Accept' => 'text/csv,application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('DH-EXPORT-1', $content);
        $this->assertStringContainsString('Nguyen Van A', $content);
    }

    public function test_export_requires_selected_ids(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/admin/marketing/customers/export', [
                'variant' => 1,
                'ids' => [],
            ])
            ->assertStatus(422);
    }

    public function test_recall_clears_sale_assignment_and_writes_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'company_id' => $admin->company_id]);
        $order = $this->makeOrder($sale);

        $this->actingAs($admin)
            ->postJson('/admin/marketing/customers/bulk/recall', [
                'ids' => [$order->id],
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Đã thu hồi 1 hồ sơ.']);

        $order->refresh();
        $this->assertNull($order->sale_user_id);
        $this->assertNull($order->assigned_at);
        $this->assertSame('new_customer', $order->operation_stage);
        $this->assertNull($order->operation_result);

        $this->assertDatabaseHas('order_operation_histories', [
            'order_id' => $order->id,
            'actor_user_id' => $admin->id,
            'action' => 'customer_recalled',
        ]);
    }

    public function test_queue_reallocation_unassigns_sale(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'company_id' => $admin->company_id]);
        $order = $this->makeOrder($sale);

        $this->actingAs($admin)
            ->postJson('/admin/marketing/customers/bulk/queue-reallocation', [
                'ids' => [$order->id],
            ])
            ->assertOk();

        $order->refresh();
        $this->assertNull($order->sale_user_id);
        $this->assertNull($order->team_id);
        $this->assertNull($order->assigned_at);
        $this->assertDatabaseHas('order_operation_histories', [
            'order_id' => $order->id,
            'action' => 'customer_reallocation_queued',
        ]);
    }
}
