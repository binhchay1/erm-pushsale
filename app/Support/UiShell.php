<?php

namespace App\Support;

use Illuminate\Http\Request;

final class UiShell
{
    public function isPublic(Request $request): bool
    {
        return $request->is('/')
            || $request->is('login')
            || $request->is('register')
            || $request->is('forgot-password')
            || $request->is('reset-password/*')
            || $request->is('verify-email*')
            || $request->is('confirm-password')
            || $request->is('two-factor-challenge')
            || $request->is('features')
            || $request->is('solutions')
            || $request->is('about')
            || $request->is('contact')
            || $request->is('sitemap.xml');
    }

    public function name(Request $request): string
    {
        return $this->isPublic($request) ? 'public' : 'pushsale';
    }
}
