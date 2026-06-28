<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function features(): Response
    {
        return Inertia::render('Marketing/Features', [
            'seo' => app(Seo::class)->page('features'),
        ]);
    }

    public function solutions(): Response
    {
        return Inertia::render('Marketing/Solutions', [
            'seo' => app(Seo::class)->page('solutions'),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Marketing/About', [
            'seo' => app(Seo::class)->page('about'),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Marketing/Contact', [
            'seo' => app(Seo::class)->page('contact'),
        ]);
    }
}
