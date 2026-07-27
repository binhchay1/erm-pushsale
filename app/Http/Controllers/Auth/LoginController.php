<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'demoAccounts' => \App\Support\DemoAccounts::displayGroups(),
            'demoPassword' => \App\Support\DemoAccounts::PASSWORD,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('login'));
        }

        $candidate = User::withoutTenant()
            ->with('company:id,name,status,expires_at')
            ->where('email', $credentials['email'])
            ->first();

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGIN_FAILED, $candidate, $credentials['email'], 'throttled');
            throw ValidationException::withMessages([
                'email' => __('messages.auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);
            $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGIN_FAILED, $candidate, $credentials['email'], 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => __('messages.auth.invalid_credentials'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if (! $user->isPlatformAdmin() && (bool) data_get($user->permissions, 'login_blocked', false)) {
            $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGIN_BLOCKED, $user, $user->email, 'login_blocked');
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Tài khoản này chưa được phê duyệt hoặc đang bị chặn đăng nhập.',
            ]);
        }

        if (! $user->isPlatformAdmin() && (! $user->company || ! $user->company->isActive())) {
            $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGIN_BLOCKED, $user, $user->email, 'company_inactive');
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('messages.tenant.company_inactive'),
            ]);
        }

        $request->session()->regenerate();
        $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGIN_SUCCESS, $user, $user->email, 'success');

        return redirect()
            ->to($this->homeFor($user))
            ->withoutCookie('erm_skip_auto_login');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->logLoginAttempt($request, ActivityLogger::AUTH_LOGOUT, $user, $user?->email ?? '', 'logout');
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Staging auto-login must not immediately re-auth after an explicit logout.
        return redirect()
            ->route('login')
            ->withCookie(cookie('erm_skip_auto_login', '1', 60 * 12));
    }

    private function logLoginAttempt(Request $request, string $action, ?User $user, string $email, string $reason): void
    {
        try {
            ActivityLogger::log(
                $action,
                null,
                [
                    'email' => $email,
                    'company' => $user?->company?->name,
                    'company_id' => $user?->company_id,
                    'role' => $user?->role?->value,
                    'status' => $reason === 'success' ? 'success' : ($reason === 'logout' ? 'logout' : 'failed'),
                    'reason' => $reason,
                    'access_code' => substr(hash('sha256', $request->session()->getId()), 0, 20),
                ],
                $email,
                $user,
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public static function homeFor(User $user): string
    {
        return match ($user->role->value) {
            User::ROLE_ADMIN => route('admin.dashboard'),
            User::ROLE_MARKETING => route('marketing.dashboard'),
            User::ROLE_WAREHOUSE => route('warehouse.dashboard'),
            User::ROLE_ALLOCATOR => route('allocator.dashboard'),
            User::ROLE_ACCOUNTING => route('accounting.dashboard'),
            default => route('sales.dashboard'),
        };
    }
}
