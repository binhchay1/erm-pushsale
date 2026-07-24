<?php

namespace App\Http\Controllers\Admin\Pushsale\Pages;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class Page1_7_2Controller extends BasePushsalePageController
{
    protected string $pageCode = '1.7.2';

    public function approve(Request $request, int $user): RedirectResponse
    {
        $target = $this->findLoginAccessUser($request, $user);
        $permissions = is_array($target->permissions) ? $target->permissions : [];
        $permissions['login_blocked'] = false;
        $permissions['access_code'] = $permissions['access_code'] ?? substr(hash('sha256', $target->email.'|'.$target->id), 0, 20);

        $target->forceFill(['permissions' => $permissions])->save();

        return back()->with('success', 'Đã cho phép tài khoản đăng nhập.');
    }

    public function block(Request $request, int $user): RedirectResponse
    {
        $target = $this->findLoginAccessUser($request, $user);
        abort_if($target->isAdmin() && $target->id === $request->user()?->id, 422, 'Không được tự chặn tài khoản quản trị đang đăng nhập.');

        $permissions = is_array($target->permissions) ? $target->permissions : [];
        $permissions['login_blocked'] = true;
        $permissions['access_code'] = $permissions['access_code'] ?? substr(hash('sha256', $target->email.'|'.$target->id), 0, 20);

        $target->forceFill(['permissions' => $permissions])->save();

        return back()->with('success', 'Đã chặn tài khoản đăng nhập.');
    }

    private function findLoginAccessUser(Request $request, int $userId): User
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isPlatformAdmin(), 403);

        $query = User::query();
        if ($request->user()?->isPlatformAdmin()) {
            $query->withoutTenant();
        }

        return $query->findOrFail($userId);
    }
}
