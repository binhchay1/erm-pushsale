<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenant;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tin proxy HTTPS (Nginx/ELB) để session cookie đúng scheme.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(prepend: [
            SetLocale::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => SetTenant::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function (Request $request) {
            return LoginController::homeFor($request->user());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Chỉ API / request expectsJson — KHÔNG ép JSON cho Inertia (form validate cần redirect + session errors)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Trang lỗi custom (Inertia) — đồng bộ giao diện SaleOps
        $exceptions->render(function (Throwable $e, Request $request) {
            // Để Laravel/Inertia xử lý lỗi validate (422 + errors trên form)
            if ($e instanceof ValidationException) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $status = 500;

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
            } elseif ($e instanceof TokenMismatchException) {
                $status = 419;
            }

            if (! in_array($status, [401, 403, 404, 419, 429, 500, 503], true)) {
                if ($status < 500 || config('app.debug')) {
                    return null;
                }
                $status = 500;
            }

            if (config('app.debug') && $status >= 500 && ! $e instanceof HttpExceptionInterface) {
                return null;
            }

            $user = $request->user();
            $homeUrl = $user ? LoginController::homeFor($user) : route('login');

            $message = null;
            if ($e instanceof HttpExceptionInterface && $status < 500 && $status !== 404) {
                $raw = $e->getMessage();
                // Bỏ thông báo kỹ thuật Laravel (route not found, v.v.)
                if ($raw && ! str_contains($raw, 'could not be found')) {
                    $message = $raw;
                }
            }

            return Inertia::render('Errors/ErrorPage', [
                'status' => $status,
                'message' => $message,
                'homeUrl' => $homeUrl,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
