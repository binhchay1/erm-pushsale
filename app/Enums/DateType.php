<?php

namespace App\Enums;

enum DateType: string
{
    case DataArrival = 'data_arrival';
    case SaleReceived = 'sale_received_data';
    case CareUpdate = 'care_update';
    case Closing = 'closing_date';
    case Posting = 'posting_date';
    case NextOperation = 'next_operation_date';
    case DeliveryUpdate = 'delivery_update_date';
    case DesiredDelivery = 'desired_delivery_date';

    public function label(): string
    {
        return match ($this) {
            self::DataArrival => 'Ngày data về hệ thống',
            self::SaleReceived => 'Ngày sale nhận data',
            self::CareUpdate => 'Ngày sale tác nghiệp',
            self::Closing => 'Ngày sale chốt đơn',
            self::Posting => 'Ngày đăng đơn',
            self::NextOperation => 'Ngày sale tác nghiệp tiếp',
            self::DeliveryUpdate => 'Ngày cập nhật trạng thái giao hàng',
            self::DesiredDelivery => 'Ngày muốn nhận hàng',
        };
    }

    /**
     * Cột ngày trên orders — nguồn sự thật dùng chung filter / series / fact.
     * DeliveryUpdate = last_delivery_event_at (không dùng updated_at chung).
     */
    public function orderColumn(): string
    {
        return match ($this) {
            self::SaleReceived => 'assigned_at',
            self::CareUpdate => 'updated_at',
            self::Closing => 'closed_at',
            self::Posting => 'created_at',
            self::NextOperation => 'next_operation_at',
            self::DeliveryUpdate => 'last_delivery_event_at',
            self::DesiredDelivery => 'desired_delivery_at',
            default => 'data_arrived_at',
        };
    }
}
