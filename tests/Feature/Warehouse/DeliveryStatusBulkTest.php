<?php

namespace Tests\Feature\Warehouse;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DeliveryStatusBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspect_and_update_delivery_status_by_codes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PS00184600806PS',
            'customer_name' => 'KH',
            'customer_phone' => '0901234567',
            'delivery_status' => DeliveryStatus::Posted->value,
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/delivery-status-bulk/inspect', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
            ])
            ->assertOk()
            ->assertJsonPath('found', 1);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/delivery-status-bulk/update', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
                'delivery_status' => DeliveryStatus::Delivering->value,
                'note' => 'Đang giao',
            ])
            ->assertOk()
            ->assertJsonPath('success_count', 1);

        $this->assertSame(DeliveryStatus::Delivering->value, $order->fresh()->delivery_status);
    }

    public function test_excel_upload_and_apply(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PS00184176124PS',
            'customer_name' => 'KH',
            'customer_phone' => '0901234568',
            'delivery_status' => DeliveryStatus::Posted->value,
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 120000,
            'amount_to_collect' => 120000,
        ]);

        $csv = "Mã đơn,Mã giao vận,Trạng thái cập nhật,Ghi chú\n{$order->order_code},,delivered,OK\n";
        $file = UploadedFile::fake()->createWithContent('ttgh.csv', $csv);

        $upload = $this->actingAs($admin)
            ->post('/admin/warehouse/orders/delivery-status-bulk/upload', [
                'file' => $file,
                'is_ghtk' => 0,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json();

        $batchId = $upload['batch']['id'];
        $this->assertSame(1, $upload['counts']['total']);

        $this->actingAs($admin)
            ->postJson("/admin/warehouse/orders/delivery-status-bulk/batches/{$batchId}/apply")
            ->assertOk()
            ->assertJsonPath('counts.success', 1);

        $this->assertSame(DeliveryStatus::Delivered->value, $order->fresh()->delivery_status);
    }

    public function test_blocks_jumping_from_pre_shipment_to_post_shipment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PS00180000001PS',
            'customer_name' => 'KH',
            'customer_phone' => '0901234569',
            'delivery_status' => DeliveryStatus::WaitingWaybill->value,
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/delivery-status-bulk/update', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
                'delivery_status' => DeliveryStatus::Delivered->value,
            ])
            ->assertOk()
            ->assertJsonPath('success_count', 0)
            ->assertJsonPath('failed_count', 1);
    }
}
