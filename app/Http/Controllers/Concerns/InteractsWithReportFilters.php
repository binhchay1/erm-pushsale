<?php

namespace App\Http\Controllers\Concerns;

use App\Data\ReportFilterData;
use App\Services\FilterOptionsService;
use Illuminate\Http\Request;

trait InteractsWithReportFilters
{
    protected function reportFilters(Request $request): ReportFilterData
    {
        return ReportFilterData::fromRequest($request, $request->user());
    }

    /** @return array<string, mixed> */
    protected function reportPageProps(Request $request, array $data = []): array
    {
        $filter = $this->reportFilters($request);

        return array_merge([
            'filters' => $filter->toInertia(),
            'filterOptions' => app(FilterOptionsService::class)->forReports(),
        ], $data);
    }
}
