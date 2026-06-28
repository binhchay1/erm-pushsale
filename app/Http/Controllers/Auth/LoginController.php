<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        // ===== [AUTH-DEBUG] START — gỡ sau khi tìm ra lỗi =====
        Log::debug('[AUTH-DEBUG] store() bắt đầu', [
            'session_id_before' => $request->session()->getId(),
            'session_driver' => config('session.driver'),
            'cookie_name' => config('session.cookie'),
            'has_email' => $request->filled('email'),
            'has_password' => $request->filled('password'),
            'is_secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'x_forwarded_proto' => $request->header('x-forwarded-proto'),
            'cookies_received' => array_keys($request->cookies->all()),
        ]);
        // ===== [AUTH-DEBUG] END =====

        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::debug('[AUTH-DEBUG] validate FAIL', ['errors' => $e->errors()]);
            throw $e->redirectTo(route('login'));
        }

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        $attempt = Auth::attempt($credentials, $remember);

        Log::debug('[AUTH-DEBUG] Auth::attempt', [
            'attempt_result' => $attempt,
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
        ]);

        if (! $attempt) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('messages.auth.invalid_credentials'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        Log::debug('[AUTH-DEBUG] user sau attempt', [
            'user_id' => $user?->id,
            'is_platform_admin' => $user?->isPlatformAdmin(),
            'company_id' => $user?->company_id,
            'company_loaded' => $user?->company ? true : false,
            'company_active' => $user?->company?->isActive(),
        ]);

        if (! $user->isPlatformAdmin() && (! $user->company || ! $user->company->isActive())) {
            Log::debug('[AUTH-DEBUG] company check FAIL → logout');
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('messages.tenant.company_inactive'),
            ]);
        }

        $sessionIdBeforeRegen = $request->session()->getId();
        $request->session()->regenerate();
        $sessionIdAfterRegen = $request->session()->getId();

        $target = $this->homeFor($user);

        Log::debug('[AUTH-DEBUG] regenerate + redirect', [
            'session_id_before_regen' => $sessionIdBeforeRegen,
            'session_id_after_regen' => $sessionIdAfterRegen,
            'auth_check_after_regen' => Auth::check(),
            'auth_id_after_regen' => Auth::id(),
            'redirect_target' => $target,
            'session_keys' => array_keys($request->session()->all()),
        ]);

        return redirect()->intended($target);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function homeFor(User $user): string
    {
        if ($user->isPlatformAdmin()) {
            return route('platform.companies.index');
        }

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
