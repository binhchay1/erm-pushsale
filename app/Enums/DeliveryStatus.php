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
    case CancelClosing = 'cancel_closing';
    case PickingUp = 'picking_up';
    case CannotPickup = 'cannot_pickup';
    case Redelivery = 'redelivery';
    case Refund = 'refund';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::WaitingWaybill => 'Chờ vận đơn',
            self::Delivering => 'Đang giao hàng',
            self::Delivered => 'Đã giao hàng',
            self::Paid => 'Đã thanh toán',
            self::Returned => 'Đã hoàn',
            self::Returning => 'Đang hoàn',
            self::CancelWaybill => 'Hủy vận đơn',
            self::CannotDeliver => 'Không giao được',
            self::DeliverNow => 'Giao ngay',
            self::DeliveryComplete => 'Hoàn giao hàng',
            self::CancelClosing => 'Hủy đóng đơn',
            self::PickingUp => 'Đang lấy hàng',
            self::CannotPickup => 'Không lấy được hàng',
            self::Redelivery => 'Yêu cầu giao lại',
            self::Refund => 'Bồi hoàn',
            self::Posted => 'Đã đăng',
        };
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
