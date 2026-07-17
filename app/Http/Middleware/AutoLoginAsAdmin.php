<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginAsAdmin
{
    /**
     * Temporary staging/test helper.
     *
     * When ERM_AUTO_ADMIN_LOGIN=true, a guest web request is automatically logged in
     * as a company admin so QA can browse the entire ERM without the login screen.
     * Keep this disabled in production after testing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabledFor($request)) {
            return $next($request);
        }

        if (! $request->user()) {
            $admin = $this->resolveAdminUser();

            if ($admin) {
                Auth::guard('web')->login($admin);
                $request->setUserResolver(static fn () => $admin);

                if ($request->hasSession() && ! $request->session()->isStarted()) {
                    $request->session()->start();
                }

                if ($request->hasSession()) {
                    $request->session()->put('auto_admin_login', true);
                }
            }
        }

        $user = $request->user();
        if ($user && $request->isMethod('GET') && $this->shouldRedirectToAdmin($request)) {
            return redirect()->to(LoginController::homeFor($user));
        }

        return $next($request);
    }

    private function enabledFor(Request $request): bool
    {
        if (! (bool) config('security.auto_admin_login.enabled', false)) {
            return false;
        }

        if ($request->is('api/*')) {
            return false;
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (string $host) => trim(mb_strtolower($host)),
            (array) config('security.auto_admin_login.allowed_hosts', []),
        )));

        if ($allowedHosts === []) {
            return true;
        }

        return in_array(mb_strtolower($request->getHost()), $allowedHosts, true);
    }

    private function shouldRedirectToAdmin(Request $request): bool
    {
        return $request->is('/')
            || $request->is('login')
            || $request->is('login/*');
    }

    private function resolveAdminUser(): ?User
    {
        $query = User::withoutTenant()->with('company:id,name,status,expires_at,slug');

        $userId = config('security.auto_admin_login.user_id');
        if ($userId) {
            $user = (clone $query)->whereKey($userId)->first();
            if ($user) {
                return $user;
            }
        }

        $email = trim((string) config('security.auto_admin_login.email', ''));
        if ($email !== '') {
            $user = (clone $query)->where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        $companyAdmin = (clone $query)
            ->where('role', User::ROLE_ADMIN)
            ->whereHas('company', static function ($company): void {
                $company->where('status', 'active')
                    ->where(static function ($expires): void {
                        $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->first();

        if ($companyAdmin) {
            return $companyAdmin;
        }

        return (clone $query)
            ->where('role', User::ROLE_ADMIN)
            ->where(static function ($user): void {
                $user->where(static function ($platform): void {
                    $platform->where('is_platform_admin', true)->whereNull('company_id');
                })->orWhereHas('company', static function ($company): void {
                    $company->where('status', 'active')
                        ->where(static function ($expires): void {
                            $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->orderByDesc('is_platform_admin')
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->first();
    }
}
