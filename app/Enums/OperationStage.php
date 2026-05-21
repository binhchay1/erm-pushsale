<?php

namespace App\Enums;

enum OperationStage: string
{
    case NewCustomer = 'new_customer';
    case Call2 = 'call_2';
    case Call3 = 'call_3';
    case Call4 = 'call_4';
    case Call5 = 'call_5';
    case Call6 = 'call_6';
    case Care1 = 'care_1';
    case Care2 = 'care_2';
    case Care3 = 'care_3';
    case Skipped = 'skipped';
    case NoOperation = 'no_operation';

    public function label(): string
    {
        return match ($this) {
            self::NewCustomer => 'Khách mới',
            self::Call2 => 'Gọi lần 2',
            self::Call3 => 'Gọi lần 3',
            self::Call4 => 'Gọi lần 4',
            self::Call5 => 'Gọi lần 5',
            self::Call6 => 'Gọi lần 6',
            self::Care1 => 'Chăm sóc lần 1',
            self::Care2 => 'Chăm sóc lần 2',
            self::Care3 => 'Chăm sóc lần 3',
            self::Skipped => 'Bỏ qua',
            self::NoOperation => 'Chưa có TN',
        };
    }
}
