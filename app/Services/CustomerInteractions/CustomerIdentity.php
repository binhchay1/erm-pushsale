<?php

namespace App\Services\CustomerInteractions;

use App\Models\Order;
use App\Support\VietnamesePhone;
use Illuminate\Validation\ValidationException;

final class CustomerIdentity
{
    public static function phoneKey(Order $order): string
    {
        $phone = VietnamesePhone::normalize($order->customer_phone);

        if ($phone) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';

        if ($digits === '') {
            throw ValidationException::withMessages([
                'customer_phone' => __('operations.customer_interactions.phone_required'),
            ]);
        }

        return mb_substr($digits, 0, 30);
    }
    public static function samePhone(?string $phone, string $phoneKey): bool
    {
        $normalized = VietnamesePhone::normalize($phone);

        if ($normalized !== null) {
            return $normalized === $phoneKey;
        }

        return (preg_replace('/\D+/', '', (string) $phone) ?: '') === $phoneKey;
    }

}
