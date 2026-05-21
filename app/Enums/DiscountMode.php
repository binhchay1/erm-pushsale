<?php

namespace App\Enums;

enum DiscountMode: string
{
    case BeforeDiscount = 'before_discount';
    case AfterDiscount = 'after_discount';

    public function label(): string
    {
        return match ($this) {
            self::BeforeDiscount => 'Trước chiết khấu',
            self::AfterDiscount => 'Sau chiết khấu',
        };
    }
}
