<?php

namespace Database\Seeders\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait SeedsUsers
{
    protected function ensureUser(string $name, string $email, UserRole $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'role' => $role],
        );
        $user->ensurePreferences();

        return $user;
    }

    protected function ensureDemoStaff(UserRole $role, string $prefix, string $label, int $count): void
    {
        for ($n = 1; $n <= $count; $n++) {
            $seq = str_pad((string) $n, 2, '0', STR_PAD_LEFT);

            User::query()->firstOrCreate(
                ['email' => "{$prefix}{$seq}@saleops.local"],
                ['name' => "{$label} {$seq}", 'password' => 'password', 'role' => $role],
            );
        }
    }
}
