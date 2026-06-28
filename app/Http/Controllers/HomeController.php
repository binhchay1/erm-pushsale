<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect(LoginController::homeFor(auth()->user()));
        }

        return Inertia::render('Welcome', [
            'seo' => app(Seo::class)->page('home'),
        ]);
    }
}
