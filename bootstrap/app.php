<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            return \App\Http\Controllers\Auth\LoginController::homeFor($request->user());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson(),
            true
        );

        // Inertia: trả về trang lỗi có nội dung thay vì body trống khi 500
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->header('X-Inertia') || ! config('app.debug')) {
                return $response;
            }

            if ($response->getStatusCode() < 500) {
                return $response;
            }

            $message = $e->getMessage();
            $hint = str_contains($message, "doesn't exist")
                ? "\n\nGợi ý: chạy `php artisan migrate` và `php artisan db:seed`."
                : '';

            return response(
                '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>Lỗi server</title></head>'
                .'<body style="font-family:system-ui;padding:2rem;max-width:48rem;margin:0 auto">'
                .'<h1>Lỗi server (500)</h1>'
                .'<p><strong>'.htmlspecialchars(class_basename($e)).'</strong></p>'
                .'<pre style="background:#fef2f2;padding:1rem;overflow:auto;white-space:pre-wrap">'
                .htmlspecialchars($message.$hint)
                .'</pre>'
                .'<p><a href="'.htmlspecialchars(route('login')).'">← Về đăng nhập</a></p>'
                .'</body></html>',
                500,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });
    })->create();
