<?php

namespace Tests\Unit\Pancake;

use App\Services\Pancake\PancakeCustomerChatService;
use App\Services\Pancake\PancakeConnectionResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PancakeMessagePayloadParserTest extends TestCase
{
    public function test_message_rows_extracts_nested_pages_webhook_message(): void
    {
        $service = new PancakeCustomerChatService(new PancakeConnectionResolver);
        $method = new ReflectionMethod(PancakeCustomerChatService::class, 'messageRows');
        $method->setAccessible(true);

        $rows = $method->invoke($service, [
            'event_type' => 'messaging',
            'page_id' => 'page-1',
            'data' => [
                'conversation' => ['id' => 'page1_user1'],
                'message' => [
                    'id' => 'mid-1',
                    'message' => 'Xin chào shop',
                ],
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('mid-1', $rows[0]['id']);
        $this->assertSame('Xin chào shop', $rows[0]['message']);
    }
}
