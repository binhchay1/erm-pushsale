<?php

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dashboard.admin', function ($user) {
    return $user->role->value === User::ROLE_ADMIN;
});

Broadcast::channel('dashboard.sales', function ($user) {
    return $user->role->value === User::ROLE_SALES;
});

Broadcast::channel('dashboard.allocator', function ($user) {
    return in_array($user->role->value, [User::ROLE_ADMIN, User::ROLE_ALLOCATOR], true);
});

Broadcast::channel('dashboard.marketing', function ($user) {
    return in_array($user->role->value, [User::ROLE_ADMIN, User::ROLE_MARKETING], true);
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
    return (int) $user->company_id === (int) $companyId;
});
