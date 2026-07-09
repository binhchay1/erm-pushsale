<?php

namespace Tests\Feature\CustomerInteractions;

use App\Enums\ClosingStatus;
use App\Enums\OperationStage;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_sales_can_send_internal_customer_messages(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->makeOrder($sales);

        $this->actingAs($sales)
            ->postJson("/customers/orders/{$order->id}/messages", ['message' => 'Sale đã gọi, khách hẹn chiều.'])
            ->assertCreated()
            ->assertJsonPath('message.authorName', $sales->name)
            ->assertJsonPath('message.message', 'Sale đã gọi, khách hẹn chiều.');

        $this->actingAs($admin)
            ->postJson("/customers/orders/{$order->id}/messages", ['message' => 'Admin đã kiểm tra thông tin.'])
            ->assertCreated()
            ->assertJsonPath('message.authorName', $admin->name);

        $this->assertDatabaseCount('customer_internal_messages', 2);
    }

    public function test_marketing_default_customer_permission_is_read_only(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $order = $this->makeOrder($sales);

        CustomerInternalMessage::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'author_user_id' => $sales->id,
            'author_name' => $sales->name,
            'author_role' => UserRole::Sales->value,
            'customer_phone' => $order->customer_phone,
            'message' => 'Tin nhắn đã có.',
        ]);

        $this->actingAs($marketing)
            ->getJson("/customers/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonPath('canWrite', false)
            ->assertJsonPath('messages.0.message', 'Tin nhắn đã có.');

        $this->actingAs($marketing)
            ->postJson("/customers/orders/{$order->id}/messages", ['message' => 'Không được gửi'])
            ->assertForbidden();
    }

    public function test_messages_are_shared_between_orders_with_the_same_customer_phone(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $first = $this->makeOrder($sales, 'ORD-001', '0912345678');
        $second = $this->makeOrder($sales, 'ORD-002', '0912 345 678');

        $this->actingAs($sales)
            ->postJson("/customers/orders/{$first->id}/messages", ['message' => 'Trao đổi dùng chung theo số điện thoại.'])
            ->assertCreated();

        $this->actingAs($sales)
            ->getJson("/customers/orders/{$second->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Trao đổi dùng chung theo số điện thoại.');
    }

    public function test_updating_sale_operation_status_creates_detailed_history(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->makeOrder($sales);

        $this->actingAs($sales)
            ->post("/sales/orders/{$order->id}/operation-status", [
                'operation_result' => 'considering',
                'note' => 'Khách đang cân nhắc, gọi lại sau.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_operation_histories', [
            'order_id' => $order->id,
            'actor_user_id' => $sales->id,
            'action' => OrderOperationHistory::ACTION_STATUS_UPDATED,
            'operation_stage_before' => OperationStage::NewCustomer->value,
            'operation_stage_after' => OperationStage::Call2->value,
            'operation_result' => 'considering',
            'note' => 'Khách đang cân nhắc, gọi lại sau.',
        ]);

        $this->actingAs($sales)
            ->getJson("/customers/orders/{$order->id}/operation-history")
            ->assertOk()
            ->assertJsonPath('histories.0.action', OrderOperationHistory::ACTION_STATUS_UPDATED)
            ->assertJsonPath('histories.0.actorName', $sales->name)
            ->assertJsonPath('histories.0.note', 'Khách đang cân nhắc, gọi lại sau.');
    }

    public function test_history_endpoint_returns_current_snapshot_for_legacy_orders(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->makeOrder($sales);

        $this->actingAs($sales)
            ->getJson("/customers/orders/{$order->id}/operation-history")
            ->assertOk()
            ->assertJsonPath('histories.0.action', OrderOperationHistory::ACTION_INITIAL_SNAPSHOT)
            ->assertJsonPath('histories.0.synthetic', true);
    }


    public function test_warehouse_can_write_and_custom_customer_permission_controls_message_access(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = User::factory()->create(['role' => UserRole::Warehouse]);
        $marketingFull = User::factory()->create([
            'role' => UserRole::Marketing,
            'permissions' => [PermissionArea::Customers->value => PermissionLevel::Full->value],
        ]);
        $marketingNone = User::factory()->create([
            'role' => UserRole::Marketing,
            'permissions' => [PermissionArea::Customers->value => PermissionLevel::None->value],
        ]);
        $order = $this->makeOrder($sales);

        $this->actingAs($warehouse)
            ->postJson("/customers/orders/{$order->id}/messages", ['message' => 'Kho đã kiểm tra thông tin giao hàng.'])
            ->assertCreated();

        $this->actingAs($marketingFull)
            ->postJson("/customers/orders/{$order->id}/messages", ['message' => 'Marketing được cấp quyền tùy chỉnh.'])
            ->assertCreated();

        $this->actingAs($marketingNone)
            ->getJson("/customers/orders/{$order->id}/messages")
            ->assertForbidden();
    }


    public function test_pancake_customer_chat_uses_separate_custom_permission(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = User::factory()->create(['role' => UserRole::Warehouse]);
        $marketingFull = User::factory()->create([
            'role' => UserRole::Marketing,
            'permissions' => [PermissionArea::CustomerChat->value => PermissionLevel::Full->value],
        ]);
        $marketingNone = User::factory()->create([
            'role' => UserRole::Marketing,
            'permissions' => [PermissionArea::CustomerChat->value => PermissionLevel::None->value],
        ]);
        $order = $this->makeOrder($sales);

        $this->actingAs($warehouse)
            ->getJson("/customers/orders/{$order->id}/pancake-messages")
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('canWrite', false);

        $this->actingAs($warehouse)
            ->postJson("/customers/orders/{$order->id}/pancake-messages", ['message' => 'Kho không được gửi trực tiếp cho khách.'])
            ->assertForbidden();

        $this->actingAs($marketingNone)
            ->getJson("/customers/orders/{$order->id}/pancake-messages")
            ->assertForbidden();

        $this->actingAs($sales)
            ->postJson("/customers/orders/{$order->id}/pancake-messages", ['message' => 'Sale được quyền gửi nhưng đơn thiếu conversation.'])
            ->assertStatus(422);

        $this->actingAs($marketingFull)
            ->postJson("/customers/orders/{$order->id}/pancake-messages", ['message' => 'Marketing được cấp quyền tùy chỉnh.'])
            ->assertStatus(422);
    }

    public function test_purchase_history_returns_orders_grouped_by_normalized_customer_phone(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $first = $this->makeOrder($sales, 'ORD-HISTORY-001', '0912345678');
        $second = $this->makeOrder($sales, 'ORD-HISTORY-002', '+84 912 345 678');
        $this->makeOrder($sales, 'ORD-OTHER', '0987654321');

        $first->items()->create([
            'product_name' => 'Sản phẩm A',
            'quantity' => 2,
            'unit_price' => 150000,
            'discount_amount' => 0,
        ]);
        $second->items()->create([
            'product_name' => 'Sản phẩm B',
            'quantity' => 1,
            'unit_price' => 200000,
            'discount_amount' => 10000,
        ]);

        $this->actingAs($sales)
            ->getJson("/customers/orders/{$first->id}/purchase-history")
            ->assertOk()
            ->assertJsonPath('summary.orderCount', 2)
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.customerPhone', '+84 912 345 678')
            ->assertJsonPath('orders.1.customerPhone', '0912345678');
    }

    private function makeOrder(User $sales, string $code = 'ORD-CUSTOMER-001', string $phone = '0912345678'): Order
    {
        return Order::query()->create([
            'order_code' => $code,
            'sale_user_id' => $sales->id,
            'customer_name' => 'Nguyễn Văn Khách',
            'customer_phone' => $phone,
            'operation_stage' => OperationStage::NewCustomer->value,
            'closing_status' => ClosingStatus::Open->value,
            'data_arrived_at' => now(),
            'assigned_at' => now(),
        ]);
    }
}
