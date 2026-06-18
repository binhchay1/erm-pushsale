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
        return __('enums.operation_stage.'.$this->value);
    }
}
