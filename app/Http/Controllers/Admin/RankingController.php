<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RankingPeriod;
use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueRankingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __invoke(Request $request, RevenueRankingService $service): Response
    {
        $period = RankingPeriod::fromRequest($request->query('period'));

        return Inertia::render('Admin/Rankings/Index', [
            'period' => $period->value,
            'periods' => collect(RankingPeriod::cases())
                ->map(fn (RankingPeriod $p) => ['value' => $p->value, 'label' => $p->label()])
                ->all(),
            'departments' => $service->build($period),
        ]);
    }
}
