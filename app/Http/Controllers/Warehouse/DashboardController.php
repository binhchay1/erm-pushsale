<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Warehouse/Dashboard', [
            'stats' => DashboardStatsService::warehouseSnapshot(),
        ]);
    }
}
