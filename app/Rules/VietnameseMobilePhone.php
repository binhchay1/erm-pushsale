<?php

namespace App\Rules;

use App\Support\VietnamesePhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VietnameseMobilePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! VietnamesePhone::isValid(is_scalar($value) ? (string) $value : null)) {
            $fail(__('messages.lead_intake.invalid_phone'));
        }
    }
}
