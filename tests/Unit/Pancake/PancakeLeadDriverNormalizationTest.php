<?php

namespace Tests\Unit\Pancake;

use App\Integrations\Pancake\PancakeLeadDriver;
use PHPUnit\Framework\TestCase;

class PancakeLeadDriverNormalizationTest extends TestCase
{
    public function test_extracts_nested_conversation_and_page_from_pos_order_shape(): void
    {
        $driver = new PancakeLeadDriver;

        $normalized = $driver->normalize([
            'id' => 'ORD-NESTED-1',
            'customer_phone' => '0912345678',
            'conversation' => [
                'id' => 'page123_sender456',
                'page_id' => 'page123',
            ],
        ]);

        $this->assertSame('page123_sender456', $normalized['pancake']['conversation_id']);
        $this->assertSame('page123', $normalized['pancake']['page_id']);
    }

    public function test_extracts_conversation_from_order_object(): void
    {
        $driver = new PancakeLeadDriver;

        $normalized = $driver->normalize([
            'order' => [
                'id' => 'PK-99',
                'conversation_id' => 'conv-from-order',
                'page_id' => 'page-99',
            ],
            'customer_phone' => '0987654321',
        ]);

        $this->assertSame('conv-from-order', $normalized['pancake']['conversation_id']);
        $this->assertSame('page-99', $normalized['pancake']['page_id']);
    }
}
