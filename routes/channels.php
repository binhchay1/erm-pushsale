<?php

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
