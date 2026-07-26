<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

use App\Data\MarketingRankingFilterData;
use App\Services\Reports\SalesLeaderboardService;
use App\Services\Reporting\ReportSnapshotStore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SalesRankingPageController extends BasePushsalePageController
{
    protected string $pageCode = '4.3';

    public function index(Request $request): Response
    {
        $this->authorizePage($request);

        $service = app(SalesLeaderboardService::class);
        $filter = MarketingRankingFilterData::fromRequest($request);

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'sales-ranking',
            $request->user(),
            $filter->toInertia(),
            $filter->dateFrom,
            $filter->dateTo,
            'sales_ranking',
            fn () => $service->build($filter),
            $request->boolean('refresh'),
        );

        return Inertia::render('Admin/Marketing/Ranking', [
            'report' => $snapshot['data'],
            'filters' => $filter->toInertia(),
            'filterOptions' => $service->options(),
            'filterRouteUrl' => '/admin/sales/rankings',
            'activeMenuCode' => '4.3',
            'pageTitle' => 'Bảng xếp hạng Sales',
            'reportCache' => [
                'cachedAt' => $snapshot['cachedAt'],
                'fromCache' => $snapshot['fromCache'],
                'storage' => $snapshot['storage'],
                'isFinal' => $snapshot['isFinal'],
            ],
        ]);
    }
}
