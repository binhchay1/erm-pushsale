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

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // Session reflects the latest explicit choice (LocaleController writes here first).
        $sessionLocale = session('locale');
        if (in_array($sessionLocale, ['vi', 'en'], true)) {
            return $sessionLocale;
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
