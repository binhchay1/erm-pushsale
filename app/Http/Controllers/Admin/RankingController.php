<?php

namespace App\Http\Controllers\Admin;

use App\Data\MarketingRankingFilterData;
use App\Http\Controllers\Controller;
use App\Services\Reports\MarketingLeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __invoke(Request $request, MarketingLeaderboardService $service): Response
    {
        $filter = MarketingRankingFilterData::fromRequest($request);

        return Inertia::render('Admin/Marketing/Ranking', [
            'report' => $service->build($filter),
            'filters' => $filter->toInertia(),
            'filterOptions' => $service->options(),
            'filterRouteUrl' => '/admin/rankings',
            'activeMenuCode' => '2.2',
        ]);
    }
}
