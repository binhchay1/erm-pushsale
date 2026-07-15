<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case WaitingWaybill = 'waiting_waybill';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Paid = 'paid';
    case Returned = 'returned';
    case Returning = 'returning';
    case CancelWaybill = 'cancel_waybill';
    case CannotDeliver = 'cannot_deliver';
    case DeliverNow = 'deliver_now';
    case DeliveryComplete = 'delivery_complete';
    case PartialDelivery = 'partial_delivery';
    case CancelClosing = 'cancel_closing';
    case PickingUp = 'picking_up';
    case CannotPickup = 'cannot_pickup';
    case Redelivery = 'redelivery';
    case Refund = 'refund';
    case Posted = 'posted';

    public function label(): string
    {
        return __('enums.delivery_status.'.$this->value);
    }

    /**
     * Trạng thái được tính là doanh thu (đã giao thành công / đã thu tiền).
     *
     * @return array<int, string>
     */
    public static function revenueEligible(): array
    {
        return [self::Delivered->value, self::Paid->value, self::DeliveryComplete->value, self::PartialDelivery->value];
    }

    /** @return array<string, self> */
    public static function ceoSummaryMap(): array
    {
        return [
            'waitingDelivery' => self::WaitingWaybill,
            'cancelWaybill' => self::CancelWaybill,
            'delivering' => self::Delivering,
            'delivered' => self::Delivered,
            'paid' => self::Paid,
            'returned' => self::Returned,
        ];
    }
}
