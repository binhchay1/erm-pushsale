<?php

namespace Tests\Feature\Warehouse;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WarehouseOrderExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_without_selection_uses_filters_and_setting_columns(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PSEXPORT001PS',
            'customer_name' => 'KH Export',
            'customer_phone' => '0901234567',
            'receiver_name' => 'Nguoi nhan',
            'receiver_phone' => '0901234567',
            'shipping_provider' => 'manual',
            'shipping_method' => 'Thủ công',
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 299000,
            'subtotal' => 338000,
            'discount' => 39000,
            'amount_to_collect' => 0,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Bột diệt cỏ 500gr',
            'quantity' => 2,
            'unit_price' => 169000,
            'item_type' => 'product',
        ]);

        $response = $this->actingAs($admin)
            ->post('/admin/warehouse/orders/bulk/export', [
                'type' => 'standard',
                'ids' => [],
                'filters' => [
                    'date_from' => now()->subDay()->toDateString(),
                    'date_to' => now()->toDateString(),
                    'date_type' => 'data_arrival',
                ],
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('content-type'));
        $body = $response->getContent();
        $this->assertStringContainsString('Mã đơn', $body);
        $this->assertStringContainsString('PSEXPORT001PS', $body);
        $this->assertStringContainsString('Bột diệt cỏ 500gr', $body);
        $this->assertStringContainsString('ĐH Tên SP', $body);
    }

    public function test_export_throttle_blocks_rapid_clicks(): void
    {
        config(['warehouse_excel_export.throttle_per_minute' => 1]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        RateLimiter::clear('warehouse-excel-export|'.$admin->id);

        Order::query()->create([
            'order_code' => 'PSEXPORT002PS',
            'customer_name' => 'KH',
            'customer_phone' => '0901111222',
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);

        $this->actingAs($admin)
            ->post('/admin/warehouse/orders/bulk/export', [
                'type' => 'shipping',
                'ids' => [],
                'filters' => [
                    'date_from' => now()->subDay()->toDateString(),
                    'date_to' => now()->toDateString(),
                ],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/bulk/export', [
                'type' => 'accounting',
                'ids' => [],
                'filters' => [
                    'date_from' => now()->subDay()->toDateString(),
                    'date_to' => now()->toDateString(),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['export']);
    }
}
