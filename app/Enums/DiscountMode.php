<?php

namespace App\Enums;

enum DiscountMode: string
{
    case BeforeDiscount = 'before_discount';
    case AfterDiscount = 'after_discount';

    public function label(): string
    {
        return __('enums.discount_mode.'.$this->value);
    }
}
