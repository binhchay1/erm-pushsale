<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SecurityAuditDemoSeeder extends Seeder
{
    public function run(): void
    {
        ActivityLog::query()
            ->whereIn('action', [
                ActivityLogger::AUTH_LOGIN_SUCCESS,
                ActivityLogger::AUTH_LOGIN_FAILED,
                ActivityLogger::AUTH_LOGIN_BLOCKED,
                ActivityLogger::AUTH_LOGOUT,
            ])
            ->delete();

        $users = User::query()
            // CASE thay cho FIELD() để seeder chạy được cả trên sqlite (test) lẫn mysql.
            ->orderByRaw("case role when 'admin' then 0 when 'sales' then 1 when 'marketing' then 2 when 'warehouse' then 3 when 'accounting' then 4 when 'allocator' then 5 else 6 end")
            ->orderBy('id')
            ->limit(24)
            ->get()
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $index => $user) {
            $permissions = is_array($user->permissions) ? $user->permissions : [];
            $permissions['access_code'] = $permissions['access_code'] ?? strtoupper(Str::random(4)).'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
            // Chỉ khóa một vài tài khoản nhân viên để màn 1.7.2 có đủ trạng thái, không khóa admin chính.
            $permissions['login_blocked'] = $user->role !== UserRole::Admin && $index % 11 === 0;

            $user->forceFill(['permissions' => $permissions])->save();

            $this->createAuthLog(
                $user,
                ActivityLogger::AUTH_LOGIN_SUCCESS,
                'success',
                now()->subMinutes(18 + $index * 17),
                'Chrome '.(120 + ($index % 8)).' / Windows 10',
                '10.0.'.(($index % 6) + 1).'.'.(20 + $index),
            );

            if ($index % 5 === 0) {
                $this->createAuthLog(
                    $user,
                    ActivityLogger::AUTH_LOGIN_FAILED,
                    'invalid_credentials',
                    now()->subHours(6 + $index),
                    'Chrome '.(118 + ($index % 6)).' / Windows 10',
                    '10.0.'.(($index % 6) + 1).'.'.(70 + $index),
                );
            }

            if ($index % 7 === 0) {
                $this->createAuthLog(
                    $user,
                    ActivityLogger::AUTH_LOGOUT,
                    'logout',
                    now()->subHours(2 + $index),
                    'Chrome '.(119 + ($index % 5)).' / Windows 10',
                    '10.0.'.(($index % 6) + 1).'.'.(90 + $index),
                );
            }
        }

        $this->command?->info('Đã tạo dữ liệu demo thật cho 1.7.1 Lịch sử đăng nhập và 1.7.2 Quản lý đăng nhập.');
    }

    private function createAuthLog(User $user, string $action, string $reason, \DateTimeInterface $createdAt, string $userAgent, string $ipAddress): void
    {
        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'subject_label' => $user->email,
            'properties' => [
                'email' => $user->email,
                'company' => $user->company?->name,
                'company_id' => $user->company_id,
                'role' => $user->role?->value,
                'status' => $reason === 'success' ? 'success' : ($reason === 'logout' ? 'logout' : 'failed'),
                'reason' => $reason,
                'access_code' => substr(hash('sha256', $user->email.'|'.$createdAt->format(DATE_ATOM)), 0, 20),
                '_request' => [
                    'method' => $action === ActivityLogger::AUTH_LOGOUT ? 'POST' : 'POST',
                    'path' => $action === ActivityLogger::AUTH_LOGOUT ? 'logout' : 'login',
                    'route_name' => $action === ActivityLogger::AUTH_LOGOUT ? 'logout' : 'login',
                    'referer' => '/login',
                ],
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => $createdAt,
        ]);
    }
}
