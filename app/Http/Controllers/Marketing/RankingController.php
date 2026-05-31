<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Concerns\InteractsWithRevenueRanking;
use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueRankingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    use InteractsWithRevenueRanking;

    public function __invoke(Request $request, RevenueRankingService $service): Response
    {
        $user = $request->user();

        return Inertia::render('Rankings/Index', $this->revenueRankingPageProps(
            $request,
            $service,
            route('marketing.rankings'),
            roleScope: $user->role,
            showDepartmentTabs: false,
            highlightUserId: $user->id,
        ));
    }
}
