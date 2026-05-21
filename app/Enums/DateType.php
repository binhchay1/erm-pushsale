<?php

namespace App\Enums;

enum DateType: string
{
    case DataArrival = 'data_arrival';
    case SaleReceived = 'sale_received_data';
    case Closing = 'closing_date';
    case CareUpdate = 'care_update';

    public function label(): string
    {
        return match ($this) {
            self::DataArrival => 'Ngày data về',
            self::SaleReceived => 'Ngày sale nhận data',
            self::Closing => 'Ngày chốt đơn',
            self::CareUpdate => 'Ngày care cập nhật',
        };
    }
}
