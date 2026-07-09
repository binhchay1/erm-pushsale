<?php

namespace App\Services\CustomerInteractions;

use App\Models\Order;

/**
 * Builds non-guessable private broadcast channel names for customer chat.
 *
 * Channel names intentionally use HMAC tokens instead of raw phone numbers or
 * Pancake conversation ids. The frontend only receives these values after the
 * normal route permission checks pass.
 */
final class CustomerConversationChannel
{
    public static function internalForOrder(Order $order): string
    {
        $phone = CustomerIdentity::phoneKey($order);

        return self::internalForPhone((int) $order->company_id, $phone);
    }

    public static function internalForPhone(int $companyId, string $phone): string
    {
        return sprintf(
            'customer.internal.%d.%s',
            $companyId,
            self::token('internal', $companyId, $phone),
        );
    }

    public static function pancakeForConversation(int $companyId, string $conversationId): string
    {
        return sprintf(
            'customer.pancake.%d.%s',
            $companyId,
            self::token('pancake', $companyId, $conversationId),
        );
    }

    private static function token(string $scope, int $companyId, string $value): string
    {
        $secret = (string) config('app.key');
        if ($secret === '') {
            $secret = 'saleops-local-channel-key';
        }

        return substr(hash_hmac('sha256', $scope.'|'.$companyId.'|'.$value, $secret), 0, 40);
    }
}
