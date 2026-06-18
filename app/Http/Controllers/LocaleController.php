<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'in:vi,en'],
        ])['locale'];

        session(['locale' => $locale]);
        App::setLocale($locale);

        if ($user = $request->user()) {
            $prefs = $user->ensurePreferences();
            $prefs->update(['locale' => $locale]);
            $user->unsetRelation('preferences');
        }

        return back(303);
    }
}
