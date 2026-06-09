<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Shipment;

/**
 * Quy tắc hiển thị nút thao tác vận đơn theo trạng thái shipment + đơn hàng.
 */
class ShipmentActionResolver
{
    /** Trạng thái giao hàng đã kết thúc — không hủy vận đơn / tạo mới trên cùng luồng. */
    private const DELIVERY_TERMINAL = [
        'delivered',
        'paid',
        'returned',
        'returning',
        'refund',
        'cancel_waybill',
        'cancel_closing',
        'cannot_deliver',
    ];

    /**
     * @return array{
     *     canCreate: bool,
     *     canSync: bool,
     *     canCalculateFee: bool,
     *     canPrintLabel: bool,
     *     canCancel: bool
     * }
     */
    public function forShipment(?Shipment $shipment, ?Order $order = null): array
    {
        $state = $shipment?->state;
        $hasTracking = filled($shipment?->tracking_number);
        $isSubmitted = $state === Shipment::STATE_SUBMITTED;
        $isRetryable = in_array($state, [Shipment::STATE_FAILED, Shipment::STATE_CANCELLED], true);
        $deliveryTerminal = in_array((string) ($order?->delivery_status ?? ''), self::DELIVERY_TERMINAL, true);

        $hasActiveWaybill = $hasTracking && $isSubmitted;

        return [
            // Chưa có mã vận đơn, hoặc lần trước thất bại / đã hủy → cho tạo lại
            'canCreate' => ! $hasTracking || $isRetryable,
            // Có vận đơn đang hoạt động → đồng bộ lộ trình từ hãng VC
            'canSync' => $hasActiveWaybill,
            // Chỉ tính phí trước khi có vận đơn hoạt động
            'canCalculateFee' => ! $hasActiveWaybill,
            // Đã có mã vận đơn thành công → in nhãn
            'canPrintLabel' => $hasActiveWaybill,
            // Hủy chỉ khi vận đơn còn hoạt động và đơn chưa kết thúc giao hàng
            'canCancel' => $hasActiveWaybill && ! $deliveryTerminal,
        ];
    }
}
