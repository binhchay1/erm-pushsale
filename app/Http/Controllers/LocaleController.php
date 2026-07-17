<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'in:vi,en'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ])['locale'];

        session(['locale' => $locale]);
        App::setLocale($locale);

        if ($user = $request->user()) {
            $prefs = $user->ensurePreferences();
            $prefs->update(['locale' => $locale]);
            $user->unsetRelation('preferences');
        }

        $redirect = (string) $request->input('redirect', '');
        $response = $this->safeRedirect($redirect) !== null
            ? redirect($this->safeRedirect($redirect), 303)
            : back(303);

        return $response->withCookie(cookie()->forever('locale', $locale, '/', null, null, false, false, 'Lax'));
    }

    private function safeRedirect(string $redirect): ?string
    {
        $redirect = trim($redirect);

        if ($redirect === '') {
            return null;
        }

        if (! Str::startsWith($redirect, '/') || Str::startsWith($redirect, '//')) {
            return null;
        }

        // Không cho redirect ngược lại endpoint đổi ngôn ngữ để tránh vòng lặp.
        if (Str::startsWith($redirect, '/locale')) {
            return '/';
        }

        return $redirect;
    }
}
