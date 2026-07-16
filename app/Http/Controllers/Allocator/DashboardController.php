<?php

namespace App\Http\Controllers\Allocator;

use App\Data\ReportFilterData;
use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use App\Services\Reports\ReportSnapshotCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filter = ReportFilterData::fromRequest($request, $request->user());

        return Inertia::render('Allocator/Dashboard', [
            'stats' => Inertia::defer(
                fn () => app(ReportSnapshotCache::class)->remember(
                    'allocator-dashboard',
                    $request->user(),
                    $filter,
                    fn () => DashboardStatsService::allocatorSnapshot($request->user(), $filter),
                    $request->boolean('refresh'),
                )['data'],
            ),
            'filters' => $filter->toInertia(),
        ]);
    }
}
