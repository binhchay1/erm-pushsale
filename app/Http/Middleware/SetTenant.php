<?php

namespace App\Http\Middleware;

use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    public function __construct(private readonly TenantManager $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

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
