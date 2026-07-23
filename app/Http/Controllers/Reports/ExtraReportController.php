<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Enums\DateType;
use App\Enums\DiscountMode;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\ReportSnapshotCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtraReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __construct(
        private readonly ExtraReportService $reports,
    ) {}

    public function __invoke(Request $request, string $report): Response|StreamedResponse|HttpResponse
    {
        $user = $request->user();

        abort_unless($this->reports->exists($report), 404);
        abort_unless($this->reports->canView($user, $report), 403);

        $this->validateReportRequest($request, $report);

        $filter = $this->reportFilters($request);
        $cached = $this->maybeCachedReport(
            $request,
            $report,
            $filter,
            fn () => $this->reports->build($report, $user, $filter),
            ReportSnapshotCache::isHeavyExtra($report),
        );
        $data = $cached['data'];

        $exportRows = $data['rows'];
        if ($data['totals']) {
            $exportRows[] = array_merge($data['totals'], ['_is_total' => true]);
        }

        if ($exported = $this->maybeExportReport(
            $request,
            $exportRows,
            $data['columns'],
            'bao-cao-'.$report,
            [
                'title' => $data['meta']['title'],
                'subtitle' => $data['meta']['description'] ?? '',
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        return Inertia::render('Reports/ExtraReport', array_merge(
            $this->reportPageProps($request),
            [
                'meta' => $data['meta'],
                'columns' => $data['columns'],
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'extra' => $data['extra'] ?? [],
                'activeMenuCode' => $this->activeMenuCode($user->role, $report, $request),
                'reportNav' => $this->reports->availableFor($user),
                'routeUrl' => $this->routeUrl($request, $user, $report),
                'filterFields' => $data['meta']['filterFields'],
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ],
        ));
    }

    private function routeUrl(Request $request, mixed $user, string $report): string
    {
        $path = '/'.ltrim($request->path(), '/');

        return match ($path) {
            '/ld/thong-ke/bao-cao-cong-viec-mkt', '/ld/thong-ke/bao-cao-up-sale', '/bao-cao/bao-cao-doanh-so-chi-tiet-marketing' => $path,
            default => $this->reports->basePathFor($user).'/'.$report,
        };
    }

    /** Validate các tham số lọc động trước khi chuyển vào ReportFilterData. */
    private function validateReportRequest(Request $request, string $report): void
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date_type' => ['nullable', Rule::in(array_merge(array_map(static fn (DateType $case): string => $case->value, DateType::cases()), ['-1', '1', '2', '3', '4', '5', '6']))],
            'discount_mode' => ['nullable', Rule::in(array_map(static fn (DiscountMode $case): string => $case->value, DiscountMode::cases()))],
            'customer_type' => ['nullable', Rule::in(['new', 'old', '0', '1'])],
            'delivery_status' => ['nullable', 'string', 'max:80'],
            'reconciliation_status' => ['nullable', Rule::in(['pending', 'reconciled'])],
            'parent_product_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'team_leader_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'sale_id' => ['nullable', 'integer'],
            'marketing_team_leader_id' => ['nullable', 'integer'],
            'marketing_team_id' => ['nullable', 'integer'],
            'marketer_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'marketing_source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
            'operation_stage' => ['nullable', 'string', 'max:80'],
            'operation_result' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100, 200, 500, 1000, 999999])],
            'no_closing_date_limit' => ['nullable', 'boolean'],
            'export' => ['nullable', Rule::in(['xls', 'excel', 'csv'])],
            'menu' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function activeMenuCode(UserRole $role, string $report, Request $request): ?string
    {
        $path = '/'.ltrim($request->path(), '/');
        $menu = trim((string) $request->query('menu', ''));

        if ($path === '/ld/thong-ke/bao-cao-cong-viec-mkt') {
            return in_array($menu, ['2.8.2', '2.7.5'], true) ? $menu : '2.7.5';
        }

        if ($path === '/ld/thong-ke/bao-cao-up-sale') {
            return in_array($menu, ['2.8.3', '8.1.3', '6.3.12'], true) ? $menu : '8.1.3';
        }

        if ($path === '/bao-cao/bao-cao-doanh-so-chi-tiet-marketing') {
            return in_array($menu, ['8.1.2', '2.7.1', '6.3.11'], true) ? $menu : '8.1.2';
        }

        return match ($role) {
            UserRole::Admin => match ($report) {
                'sale-4' => '4.5.1',
                'sale-2' => '4.5.2',
                'sale-1' => '4.5.3',
                'sale-3' => '4.5.4',
                'warehouse-sales-summary' => '4.5.5',
                'warehouse-sales-v2' => '4.5.6',
                'sale-5' => '4.5.8',
                'kho-2' => '4.5.9',
                'product-conversion' => '6.3.9',
                'marketing-1' => '2.7.1',
                'marketing-sales-summary' => '2.7.2',
                'marketing-sales-v2' => '2.7.3',
                'marketing-3' => '2.7.5',
                'marketing-4' => '8.1.3',
                default => null,
            },
            UserRole::Sales => match ($report) {
                'sale-4' => '4.5.1',
                'sale-2' => '4.5.2',
                'sale-1' => '4.5.3',
                'sale-3' => '4.5.4',
                'warehouse-sales-summary' => '4.5.5',
                'warehouse-sales-v2' => '4.5.6',
                'sale-5' => '4.5.8',
                'kho-2' => '4.5.9',
                'product-conversion' => '4.5.10',
                default => null,
            },
            UserRole::Marketing => match ($report) {
                'marketing-1' => '2.7.1',
                'marketing-sales-summary' => '2.7.2',
                'marketing-sales-v2' => '2.7.3',
                'marketing-3' => '2.7.5',
                'kho-2' => '2.7.6',
                'product-conversion', 'marketing-2' => '2.7.7',
                'marketing-4' => '8.1.3',
                default => null,
            },
            UserRole::Warehouse => match ($report) {
                'kho-2' => '5.5.3',
                'warehouse-sales-summary' => '5.5.9',
                'warehouse-sales-v2' => '5.5.10',
                default => null,
            },
            UserRole::Accounting => match ($report) {
                'warehouse-sales-summary' => '6.3.2',
                'warehouse-sales-v2' => '6.3.3',
                'sale-3' => '6.3.6',
                'kho-2' => '6.3.8',
                'product-conversion' => '6.3.9',
                'sale-2' => '6.3.10',
                'marketing-1' => '6.3.11',
                'marketing-4' => '6.3.12',
                default => null,
            },
            default => null,
        };
    }
}
