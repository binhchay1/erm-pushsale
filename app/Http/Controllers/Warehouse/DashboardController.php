<?php

namespace App\Http\Controllers\Warehouse;

use App\Data\ReportFilterData;
use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filter = ReportFilterData::fromRequest($request, $request->user());

        return Inertia::render('Warehouse/Dashboard', [
            'stats' => Inertia::defer(
                fn () => DashboardStatsService::warehouseSnapshot($request->user(), $filter),
            ),
            'filters' => $filter->toInertia(),
        ]);
    }
}
