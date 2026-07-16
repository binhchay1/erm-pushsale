<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\Reports\HourlyStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HourlyStatsController extends Controller
{
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __construct(
        private readonly HourlyStatsService $service,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $filter = $this->reportFilters($request);
        $snapshot = $this->maybeCachedReport(
            $request,
            'hourly-stats',
            $filter,
            fn () => $this->service->build($user, $filter),
        );
        $data = $snapshot['data'];

        $filterFields = ['date_from', 'date_to', 'product_id'];
        if ($user->role === UserRole::Admin) {
            $filterFields[] = 'sale_id';
            $filterFields[] = 'marketer_id';
        }

        $base = match ($user->role) {
            UserRole::Admin => '/admin/reports/hourly',
            UserRole::Marketing => '/marketing/reports/hourly',
            UserRole::Sales => '/sales/reports/hourly',
            default => '/reports/hourly',
        };

        return Inertia::render('Reports/HourlyStats', array_merge(
            $this->reportPageProps($request, ['filterFields' => $filterFields]),
            [
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'peak' => $data['peak'],
                'routeUrl' => $base,
                'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            ],
        ));
    }
}
