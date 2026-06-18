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
        return __('enums.date_type.'.$this->value);
    }
}
