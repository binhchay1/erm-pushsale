<?php

namespace Tests\Feature\Pancake;

use App\Enums\IntegrationPlatform;
use App\Enums\UserRole;
use App\Jobs\Pancake\ProcessPancakeMessageWebhookJob;
use App\Models\IntegrationConnection;
use App\Models\Order;
use App\Models\PancakeCustomerMessage;
use App\Models\PancakeSyncRecord;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PancakeMessageWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_messaging_webhook_caches_message_when_order_matches_by_phone(): void
    {
        $company = \App\Models\Company::query()->first();
        $shop = Shop::query()->create([
            'company_id' => $company->id,
            'name' => 'Shop Test',
            'code' => 'ST-MSG',
            'is_default' => true,
            'is_active' => true,
        ]);
        $sale = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Sales,
        ]);

        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
        $connection->update(['is_enabled' => true]);

        $order = Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'order_code' => 'ORD-CHAT-1',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách chat',
            'customer_phone' => '0912345678',
            'closing_status' => 'open',
        ]);

        PancakeSyncRecord::query()->create([
            'company_id' => $company->id,
            'integration_connection_id' => $connection->id,
            'external_type' => PancakeSyncRecord::TYPE_ORDER,
            'external_id' => 'PK-CHAT-1',
            'order_id' => $order->id,
            'metadata' => [
                'page_id' => 'page-1',
                'conversation_id' => 'page1_customer99',
            ],
        ]);

        $payload = [
            'event_type' => 'messaging',
            'page_id' => 'page-1',
            'data' => [
                'conversation' => [
                    'id' => 'page1_customer99',
                    'type' => 'INBOX',
                    'from' => ['id' => 'customer99', 'name' => 'Khách chat'],
                ],
                'message' => [
                    'id' => 'msg-webhook-1',
                    'message' => 'Shop còn hàng không?',
                    'from' => ['id' => 'customer99', 'name' => 'Khách chat'],
                ],
            ],
        ];

        $this->postJson("/api/v1/pancake/messages/{$connection->webhook_token}", $payload)
            ->assertAccepted();

        $job = collect(Queue::pushed(ProcessPancakeMessageWebhookJob::class))->last();
        $this->assertNotNull($job);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('pancake_customer_messages', [
            'company_id' => $company->id,
            'order_id' => $order->id,
            'conversation_id' => 'page1_customer99',
            'external_message_id' => 'msg-webhook-1',
        ]);

        $message = PancakeCustomerMessage::query()->where('external_message_id', 'msg-webhook-1')->first();
        $this->assertSame('Shop còn hàng không?', $message?->message);
    }

    public function test_messaging_webhook_without_matching_order_does_not_create_messages(): void
    {
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
        $connection->update(['is_enabled' => true]);

        $payload = [
            'event_type' => 'messaging',
            'page_id' => 'page-orphan',
            'data' => [
                'conversation' => ['id' => 'page_orphan_user1', 'type' => 'INBOX'],
                'message' => [
                    'id' => 'msg-orphan-1',
                    'message' => 'Chưa có đơn',
                ],
            ],
        ];

        $this->postJson("/api/v1/pancake/messages/{$connection->webhook_token}", $payload)
            ->assertAccepted();

        $job = collect(Queue::pushed(ProcessPancakeMessageWebhookJob::class))->last();
        app()->call([$job, 'handle']);

        $this->assertDatabaseMissing('pancake_customer_messages', [
            'external_message_id' => 'msg-orphan-1',
        ]);
    }
}
