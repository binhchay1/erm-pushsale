<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

final class RoleScopedRoutes
{
    public static function saleWorkspace(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return match ($user->role) {
            UserRole::Admin => '/admin/sales/workspace',
            UserRole::Sales => '/sales/workspace',
            default => null,
        };
    }

    public static function appendOrderId(?string $baseUrl, int $orderId): ?string
    {
        if (! $baseUrl) {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'order_id='.$orderId;
    }
}
