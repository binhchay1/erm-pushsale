<?php

namespace App\Http\Controllers\Admin;

use App\Data\MarketingRankingFilterData;
use App\Http\Controllers\Controller;
use App\Services\Reports\MarketingLeaderboardService;
use App\Services\Reporting\ReportSnapshotStore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __invoke(Request $request, MarketingLeaderboardService $service): Response
    {
        $filter = MarketingRankingFilterData::fromRequest($request);

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'admin-marketing-ranking',
            $request->user(),
            $filter->toInertia(),
            $filter->dateFrom,
            $filter->dateTo,
            'data_arrival',
            fn () => $service->build($filter),
            $request->boolean('refresh'),
        );

        return Inertia::render('Admin/Marketing/Ranking', [
            'report' => $snapshot['data'],
            'filters' => $filter->toInertia(),
            'filterOptions' => $service->options(),
            'filterRouteUrl' => '/admin/rankings',
            'activeMenuCode' => '2.2',
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
        ]);
    }
}
