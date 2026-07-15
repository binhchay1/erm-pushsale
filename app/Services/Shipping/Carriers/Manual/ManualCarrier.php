<?php

namespace App\Services\Shipping\Carriers\Manual;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use RuntimeException;

class ManualCarrier extends AbstractShippingCarrier
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    public function provider(): string { return 'manual'; }
    public function label(): string { return config('shipping_partners.providers.manual.label', 'Thủ công'); }
    public function isReady(): bool { return $this->credentials->isReady('manual'); }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException('Phương thức giao thủ công chưa được bật.');
        }

        $shipment = $this->pendingShipment($order);
        $tracking = $shipment->tracking_number ?: 'PS-'.($order->order_code ?: $order->id).'-'.now()->format('ymdHis');

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => $order->order_code,
            'tracking_number' => $tracking,
            'fee' => 0,
            'cod_amount' => (int) $order->amount_to_collect,
            'status_text' => 'Đã tạo phiếu giao thủ công',
            'response_payload' => ['mode' => 'manual'],
            'posted_at' => now(),
        ], DeliveryStatus::Posted);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        return ($shipment ?? $this->requireShipment($order))->fresh();
    }

    public function calculateFee(Order $order): array
    {
        return ['success' => true, 'data' => ['fee' => 0], 'display' => ['total' => 0]];
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        return $this->markCancelled($shipment ?? $this->requireShipment($order), $order, 'Đã hủy phiếu giao thủ công');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        return ['success' => false, 'message' => 'Phiếu giao thủ công dùng mẫu in đơn nội bộ.'];
    }

    public function testActions(): array { return []; }
    public function runTest(string $action): array { return ['success' => true, 'message' => 'Không cần kiểm tra API.']; }
}
