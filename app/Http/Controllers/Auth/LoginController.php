<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('messages.auth.invalid_credentials'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if (! $user->isPlatformAdmin() && (! $user->company || ! $user->company->isActive())) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('messages.tenant.company_inactive'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($user));
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
