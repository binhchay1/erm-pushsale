<?php

namespace App\Http\Controllers\Admin;

use App\Data\ReportFilterData;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Services\Shops\ShopOverviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopOverviewController extends Controller
{
    public function __invoke(Request $request, ShopOverviewService $overview): Response
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isAdmin() || $user->allows(PermissionArea::Reports, PermissionLevel::View)),
            403,
        );

        $filter = ReportFilterData::fromRequest($request, $user);

        return Inertia::render('Admin/Shops/Overview', [
            'overview' => $overview->compare($user, $filter),
            'filters' => $filter->toInertia(),
            'activeMenuCode' => '1.1.4',
        ]);
    }
}
