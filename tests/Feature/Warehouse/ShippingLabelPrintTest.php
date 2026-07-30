<?php

namespace Tests\Feature\Warehouse;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingLabelPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_fab_profiles_endpoint_returns_six_buttons(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/admin/warehouse/orders/print/profiles')
            ->assertOk()
            ->assertJsonCount(6, 'buttons')
            ->assertJsonPath('buttons.0.key', 'internal')
            ->assertJsonPath('buttons.5.key', 'spx');
    }

    public function test_internal_print_page_renders_selected_orders(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PS00184600806PS',
            'customer_name' => 'KH In',
            'customer_phone' => '0901234567',
            'receiver_name' => 'Nguoi nhan',
            'receiver_phone' => '0901234567',
            'shipping_provider' => 'manual',
            'shipping_method' => 'Thủ công',
            'shipping_geo' => ['province' => 'Bắc Ninh', 'district' => 'TP', 'ward' => 'P1', 'address' => '1 ABC'],
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 329000,
            'shipping_fee_collected' => 30000,
            'amount_to_collect' => 329000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/warehouse/orders/print/internal?ids='.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Warehouse/ShippingLabelPrint')
                ->where('profile.key', 'internal')
                ->where('counts.printable', 1)
                ->has('labels', 1)
                ->where('labels.0.order_code', $order->order_code)
            );
    }

    public function test_ghtk_print_filters_unmatched_providers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $manual = Order::query()->create([
            'order_code' => 'PSMANUAL001PS',
            'customer_name' => 'KH',
            'customer_phone' => '0901111111',
            'shipping_provider' => 'manual',
            'shipping_method' => 'Thủ công',
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);
        $ghtk = Order::query()->create([
            'order_code' => 'PSGHTK001PS',
            'customer_name' => 'KH',
            'customer_phone' => '0902222222',
            'shipping_provider' => 'ghtk',
            'shipping_method' => 'GHTK',
            'tracking_number' => 'GHTK123',
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 120000,
            'amount_to_collect' => 120000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/warehouse/orders/print/ghtk?ids='.$manual->id.','.$ghtk->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Warehouse/ShippingLabelPrint')
                ->where('profile.key', 'ghtk')
                ->where('counts.printable', 1)
                ->where('counts.unmatched', 1)
                ->where('labels.0.order_code', $ghtk->order_code)
            );
    }

    public function test_mark_printed_bulk(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = Order::query()->create([
            'order_code' => 'PSPRINT001PS',
            'customer_name' => 'KH',
            'customer_phone' => '0903333333',
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/print/mark-printed', ['ids' => [$order->id]])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertNotNull($order->fresh()->printed_at);
    }
}
