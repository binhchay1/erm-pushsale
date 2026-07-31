<?php

namespace Tests\Unit\Operations;

use App\Models\LeadIngestion;
use App\Models\Order;
use App\Services\Operations\OrderOperationPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class LandingMessageDisplayTest extends TestCase
{
    public function test_message_shows_address_and_status_send_on_two_lines(): void
    {
        $order = new Order([
            'shipping_address' => '12 Lê Lợi',
            'customer_note' => null,
        ]);
        $order->setRelation('leadPackets', new EloquentCollection([
            new LeadIngestion(['payload' => ['status_send' => 'Capture Form']]),
        ]));
        $order->setRelation('relatedLeadPackets', new EloquentCollection);

        $this->assertSame(
            "Địa chỉ=12 Lê Lợi\nCapture Form",
            OrderOperationPresenter::landingMessageDisplay($order),
        );

        $this->assertSame([
            'address_line' => 'Địa chỉ=12 Lê Lợi',
            'status_send' => 'Capture Form',
            'fallback' => '',
        ], OrderOperationPresenter::landingMessageParts($order));
    }

    public function test_duplicate_status_send_is_skipped(): void
    {
        $order = new Order([
            'shipping_address' => 'Capture Form',
            'customer_note' => null,
        ]);
        $order->setRelation('leadPackets', new EloquentCollection([
            new LeadIngestion(['payload' => ['status_send' => 'Capture Form']]),
            new LeadIngestion(['payload' => ['status_send' => 'Capture Form']]),
        ]));
        $order->setRelation('relatedLeadPackets', new EloquentCollection);

        $this->assertSame(
            'Địa chỉ=Capture Form',
            OrderOperationPresenter::landingMessageDisplay($order),
        );

        $parts = OrderOperationPresenter::landingMessageParts($order);
        $this->assertSame('Địa chỉ=Capture Form', $parts['address_line']);
        $this->assertNull($parts['status_send']);
    }

    public function test_status_send_only_when_address_empty(): void
    {
        $order = new Order([
            'shipping_address' => null,
            'customer_note' => null,
        ]);
        $order->setRelation('leadPackets', new EloquentCollection);
        $order->setRelation('relatedLeadPackets', new EloquentCollection([
            new LeadIngestion(['payload' => ['address' => null, 'status_send' => 'Capture Form']]),
        ]));

        $this->assertSame(
            'Capture Form',
            OrderOperationPresenter::landingMessageDisplay($order),
        );

        $parts = OrderOperationPresenter::landingMessageParts($order);
        $this->assertNull($parts['address_line']);
        $this->assertSame('Capture Form', $parts['status_send']);
    }

    public function test_address_only_has_no_status_line(): void
    {
        $order = new Order([
            'shipping_address' => 'Yên Phong',
            'customer_note' => null,
        ]);
        $order->setRelation('leadPackets', new EloquentCollection);
        $order->setRelation('relatedLeadPackets', new EloquentCollection);

        $parts = OrderOperationPresenter::landingMessageParts($order);
        $this->assertSame('Địa chỉ=Yên Phong', $parts['address_line']);
        $this->assertNull($parts['status_send']);
    }
}
