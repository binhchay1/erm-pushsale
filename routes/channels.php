<?php

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Admin / platform admin có thể mở mọi workspace theo role → được listen dashboard.* tương ứng.
 * Role đúng nghiệp vụ vẫn chỉ vào đúng kênh của mình.
 */
$canListenDashboard = static function (User $user, string ...$roles): bool {
    if ($user->isPlatformAdmin() || $user->isAdmin()) {
        return true;
    }

    $value = $user->role instanceof UserRole
        ? $user->role->value
        : (string) $user->role;

    return in_array($value, $roles, true);
};

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dashboard.admin', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_ADMIN);
});

Broadcast::channel('dashboard.sales', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_SALES);
});

Broadcast::channel('dashboard.allocator', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_ALLOCATOR);
});

Broadcast::channel('dashboard.marketing', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_MARKETING);
});

Broadcast::channel('dashboard.warehouse', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_WAREHOUSE);
});

Broadcast::channel('dashboard.accounting', function (User $user) use ($canListenDashboard) {
    return $canListenDashboard($user, User::ROLE_ACCOUNTING);
});

Broadcast::channel('customer.internal.{companyId}.{conversationKey}', function ($user, $companyId, $conversationKey) {
    return (int) $user->company_id === (int) $companyId
        && $user->allows(PermissionArea::Customers, PermissionLevel::View);
});

Broadcast::channel('customer.pancake.{companyId}.{conversationKey}', function ($user, $companyId, $conversationKey) {
    return (int) $user->company_id === (int) $companyId
        && $user->allows(PermissionArea::CustomerChat, PermissionLevel::View);
});

Broadcast::channel('company.{companyId}.order-locks', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId
        || $user->isPlatformAdmin();
});
