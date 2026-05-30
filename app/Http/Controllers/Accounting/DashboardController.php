<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Accounting/Dashboard', [
            'stats' => DashboardStatsService::accountingSnapshot(),
        ]);
    }
}
