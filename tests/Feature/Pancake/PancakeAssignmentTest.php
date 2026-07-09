<?php

namespace Tests\Feature\Pancake;

use App\Enums\IntegrationPlatform;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\IntegrationConnection;
use App\Models\PancakeSyncRecord;
use App\Models\PancakeUserMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PancakeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_assigns_to_authenticated_sales_user_and_ignores_spoofed_sale_id(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $otherSale = User::factory()->create(['role' => UserRole::Sales]);
        Sanctum::actingAs($sale);

        $this->postJson('/api/v1/pancake/extension/orders', [
            'pancake_order_id' => 'PK-SELF-1',
            'conversation_id' => 'conv-self-1',
            'customer_name' => 'Khách Pancake',
            'customer_phone' => '0912345678',
            'message' => 'Khách cần tư vấn.',
            'sale_user_id' => $otherSale->id,
            'items' => [[
                'product_name' => 'Sản phẩm A',
                'quantity' => 1,
                'unit_price' => 100000,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.assignment.mode', 'self')
            ->assertJsonPath('data.order.sale_user.id', $sale->id);
    }

    public function test_sales_supervisor_can_select_sale_in_same_team(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Sales, 'org_level' => OrgLevel::Supervisor]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'team_id' => $supervisor->team_id]);
        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/pancake/extension/orders', [
            'pancake_order_id' => 'PK-SELECT-1',
            'customer_name' => 'Khách chọn sale',
            'customer_phone' => '0912345680',
            'saleops' => [
                'assignment_mode' => 'selected_sale',
                'selected_sale_user_id' => $sale->id,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.assignment.mode', 'selected_sale')
            ->assertJsonPath('data.order.sale_user.id', $sale->id);
    }

    public function test_webhook_uses_pancake_user_mapping_when_actor_is_missing(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);

        PancakeUserMapping::query()->create([
            'company_id' => $connection->company_id,
            'integration_connection_id' => $connection->id,
            'pancake_user_key' => 'agent-001',
            'pancake_user_id' => 'agent-001',
            'internal_user_id' => $sale->id,
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/webhooks/pancake/{$connection->webhook_token}", [
            'id' => 'PK-WEBHOOK-1',
            'customer_name' => 'Khách webhook',
            'customer_phone' => '0912345681',
            'assignee' => ['id' => 'agent-001', 'name' => 'Agent Pancake'],
        ])->assertAccepted();

        $this->artisan('queue:work', [
            '--queue' => config('saleops.queues.pancake_orders', 'pancake-orders'),
            '--once' => true,
        ])->assertExitCode(0);

        $record = PancakeSyncRecord::query()->where('external_id', 'PK-WEBHOOK-1')->first();

        $this->assertNotNull($record);
        $this->assertSame('pancake_user_mapping', $record->metadata['assignment']['mode'] ?? null);
        $this->assertSame($sale->id, $record->order?->sale_user_id);
    }
}
