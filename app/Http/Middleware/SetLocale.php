<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if (! in_array($locale, ['vi', 'en'], true)) {
            $locale = config('app.locale', 'vi');
        }

        App::setLocale($locale);
        session(['locale' => $locale]);

        $response = $next($request);

        // Cookie giúp lần load đầu, route public và hard reload sau đổi ngôn ngữ
        // luôn đồng bộ, kể cả khi Inertia/session chưa kịp hydrate lại props.
        return $response->withCookie(cookie()->forever('locale', $locale, '/', null, null, false, false, 'Lax'));
    }

    private function resolveLocale(Request $request): string
    {
        // Ưu tiên lựa chọn rõ ràng trên URL/body để endpoint GET /locale hoạt động
        // không phụ thuộc CSRF hoặc trạng thái Inertia hiện tại.
        $requestLocale = $request->query('locale') ?: $request->input('locale');
        if (in_array($requestLocale, ['vi', 'en'], true)) {
            return $requestLocale;
        }

        // Session phản ánh lựa chọn mới nhất trong cùng trình duyệt.
        $sessionLocale = session('locale');
        if (in_array($sessionLocale, ['vi', 'en'], true)) {
            return $sessionLocale;
        }

        $cookieLocale = $request->cookie('locale');
        if (in_array($cookieLocale, ['vi', 'en'], true)) {
            return $cookieLocale;
        }

        if ($request->user()) {
            $pref = $request->user()->relationLoaded('preferences')
                ? $request->user()->preferences
                : $request->user()->preferences()->first();

            if ($pref?->locale && in_array($pref->locale, ['vi', 'en'], true)) {
                return $pref->locale;
            }
        }

        return (string) config('app.locale', 'vi');
    }
}
