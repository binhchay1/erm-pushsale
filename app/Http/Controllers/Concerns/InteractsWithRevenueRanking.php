<?php

namespace App\Http\Controllers\Concerns;

use App\Data\RankingFilterData;
use App\Enums\RankingPeriod;
use App\Enums\UserRole;
use App\Services\FilterOptionsService;
use App\Services\Reports\RevenueRankingService;
use Illuminate\Http\Request;

trait InteractsWithRevenueRanking
{
    /**
     * @return array<string, mixed>
     */
    protected function revenueRankingPageProps(
        Request $request,
        RevenueRankingService $service,
        string $routeUrl,
        ?UserRole $roleScope,
        bool $showDepartmentTabs = true,
        ?int $highlightUserId = null,
    ): array {
        $filter = RankingFilterData::fromRequest($request);
        $departments = $roleScope !== null
            ? $service->buildForRole($roleScope, $filter)
            : $service->build($filter);

        $myRank = null;

        if ($highlightUserId !== null && count($departments) === 1) {
            $myRank = collect($departments[0]['items'] ?? [])
                ->firstWhere('id', $highlightUserId);
        }

        return [
            'routeUrl' => $routeUrl,
            'showDepartmentTabs' => $showDepartmentTabs,
            'highlightUserId' => $highlightUserId,
            'myRank' => $myRank,
            'filters' => $filter->toInertia(),
            'filterOptions' => app(FilterOptionsService::class)->forRankings($request->user()),
            'periods' => collect(RankingPeriod::cases())
                ->map(fn (RankingPeriod $p) => ['value' => $p->value, 'label' => $p->label()])
                ->all(),
            'departments' => $departments,
        ];
    }
}
