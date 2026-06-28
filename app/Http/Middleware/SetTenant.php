<?php

namespace App\Http\Middleware;

use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    public function __construct(private readonly TenantManager $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // ===== [AUTH-DEBUG] START — gỡ sau khi tìm ra lỗi =====
        Log::debug('[AUTH-DEBUG] SetTenant', [
            'path' => $request->path(),
            'session_id' => $request->session()->getId(),
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'user_present' => $user ? true : false,
            'is_platform_admin' => $user?->isPlatformAdmin(),
            'company_id' => $user?->company_id,
            'company_loaded' => $user?->company ? true : false,
            'company_active' => $user?->company?->isActive(),
            'cookies_received' => array_keys($request->cookies->all()),
            'session_keys' => array_keys($request->session()->all()),
        ]);
        // ===== [AUTH-DEBUG] END =====

        if (! $user) {
            return $next($request);
        }

        // Super admin nền tảng: không gắn công ty → thấy toàn hệ thống.
        if ($user->isPlatformAdmin()) {
            $this->tenant->set(null);

            return $next($request);
        }

        $company = $user->company;

        if (! $company || ! $company->isActive()) {
            Log::debug('[AUTH-DEBUG] SetTenant → LOGOUT (company null/inactive)', [
                'company_null' => $company ? false : true,
                'company_active' => $company?->isActive(),
            ]);
            Auth::guard('web')->logout();
            $request->session()?->invalidate();
            $request->session()?->regenerateToken();

            if ($request->is('api/*') || $request->expectsJson()) {
                abort(403, __('messages.tenant.company_inactive'));
            }

            return redirect()->route('login')->with('error', __('messages.tenant.company_inactive'));
        }

        $this->tenant->set($company->id);

        return $next($request);
    }
}
