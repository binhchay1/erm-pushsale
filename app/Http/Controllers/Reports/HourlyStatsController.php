<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\Reports\HourlyStatsService;
use Illuminate\Http\Request;
use Carbon\CarbonInterface;
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

        $path = '/'.ltrim($request->path(), '/');
        $base = $path === '/ld/thong-ke' ? '/ld/thong-ke' : match ($user->role) {
            UserRole::Admin => '/admin/reports/hourly',
            UserRole::Marketing => '/marketing/reports/hourly',
            UserRole::Sales => '/sales/reports/hourly',
            default => '/reports/hourly',
        };

        // 8.1.1 / marketing hourly → marketer; sales hourly → sale.
        $filterFields = ['date_from', 'date_to', 'product_id'];
        $isSalesHourly = $user->role === UserRole::Sales
            || str_contains($path, '/sales/reports/hourly');
        if ($isSalesHourly) {
            $filterFields[] = 'sale_id';
        } else {
            $filterFields[] = 'marketer_id';
        }

        return Inertia::render('Reports/HourlyStats', array_merge(
            $this->reportPageProps($request, ['filterFields' => $filterFields]),
            [
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'peak' => $data['peak'],
                'dayLabel' => $this->dayLabel($filter->dateFrom, $filter->dateTo),
                'routeUrl' => $base,
                'activeMenuCode' => $path === '/ld/thong-ke' ? '8.1.1' : null,
                'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            ],
        ));
    }

    private function dayLabel(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        if (! $from || ! $to) {
            return 'Tổng';
        }

        if (! $from->isSameDay($to)) {
            return 'Tổng';
        }

        return match ((int) $to->dayOfWeekIso) {
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'CN',
            default => 'Tổng',
        };
    }

}
