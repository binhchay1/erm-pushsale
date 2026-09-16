<?php

namespace App\Http\Controllers\Admin\Ceo;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Pushsale\AnnualBusinessPlanMetric;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class YearlyBusinessPlanController extends BasePushsalePageController
{
    protected string $pageCode = '7.1.2';

    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorizePage($request);
        $year = $this->yearFromRequest($request);
        $months = $this->monthsFromRequest($request);
        $discountMode = (string) $request->query('discount_mode', 'after_discount');
        $pageRuntimeError = null;

        try {
            $payload = $this->buildPayload($year, $months, $discountMode);
        } catch (Throwable $exception) {
            report($exception);
            $payload = $this->emptyPayload($months);
            $pageRuntimeError = (bool) config('app.debug')
                ? 'Không tải được kế hoạch năm: '.$exception->getMessage()
                : 'Không tải được số liệu thực tế. Vui lòng thử lại hoặc liên hệ quản trị hệ thống.';
        }

        if ($request->boolean('export')) {
            return $this->exportYearlyPlan($year, $payload['rows']);
        }

        return Inertia::render('Admin/Ceo/YearlyBusinessPlan', [
            'schema' => [
                'code' => '7.1.2',
                'title' => 'Lập kế hoạch kinh doanh',
                'component' => 'Admin/Ceo/YearlyBusinessPlan',
            ],
            'rows' => $payload['rows'],
            'chart' => $payload['chart'],
            'note' => [
                'metrics' => collect(AnnualBusinessPlanMetric::metricDefinitions())->map(fn (array $definition, string $code): array => [
                    'code' => $code,
                    'label' => $definition['label'],
                    'symbol' => $definition['symbol'],
                ])->values()->all(),
                'formulas' => AnnualBusinessPlanMetric::formulaRows(),
            ],
            'summary' => [
                'has_planned_data' => $payload['has_planned_data'],
                'toast' => $pageRuntimeError
                    ?: ($payload['has_planned_data'] ? null : 'Mời bạn thêm số liệu dự kiến trước khi xem!'),
                'toast_type' => $pageRuntimeError ? 'error' : 'warning',
            ],
            'filters' => [
                'year' => $year,
                'months' => $months,
                'discount_mode' => $discountMode,
            ],
            'routeUrl' => '/'.$request->path(),
            'activeMenuCode' => $this->pageCode,
            'pageRuntimeError' => $pageRuntimeError,
        ]);
    }

    public function storePlannedData(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['integer', 'min:1', 'max:12'],
            'contacts' => ['required', 'numeric', 'min:0'],
            'close_rate' => ['required', 'numeric', 'min:0'],
            'products_per_order' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'contact_price' => ['required', 'numeric', 'min:0'],
            'marketing_salary' => ['nullable', 'numeric', 'min:0'],
            'marketing_bonus' => ['nullable', 'numeric', 'min:0'],
            'sale_salary' => ['nullable', 'numeric', 'min:0'],
            'sale_bonus' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'cost_of_goods_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $definitions = AnnualBusinessPlanMetric::metricDefinitions();
            $values = AnnualBusinessPlanMetric::plannedValuesFromInput($validated);
            $months = collect($validated['months'])->map(fn ($month): int => (int) $month)->unique()->sort()->values();
            $saved = 0;

            DB::transaction(function () use ($months, $validated, $definitions, $values, $request, &$saved): void {
                foreach ($months as $month) {
                    foreach ($definitions as $code => $definition) {
                        AnnualBusinessPlanMetric::query()->updateOrCreate(
                            [
                                'year' => (int) $validated['year'],
                                'month' => $month,
                                'metric_code' => (string) $code,
                            ],
                            [
                                'metric_name' => $definition['label'],
                                'planned_value' => $values[(string) $code] ?? 0,
                                'updated_by_user_id' => $request->user()?->id,
                                'created_by_user_id' => $request->user()?->id,
                            ]
                        );
                        $saved++;
                    }
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $message = (bool) config('app.debug')
                ? $exception->getMessage()
                : 'Không lưu được dữ liệu kế hoạch. Vui lòng kiểm tra lại chỉ số và thử lại.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            throw ValidationException::withMessages(['year' => $message]);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "Đã lưu {$saved} chỉ số dự kiến."])
            : back()->with('success', "Đã lưu {$saved} chỉ số dự kiến.");
    }

    private function yearFromRequest(Request $request): int
    {
        $year = (int) $request->query('year', now()->year);

        return max(2020, min(2100, $year));
    }

    /** @return array<int, int> */
    private function monthsFromRequest(Request $request): array
    {
        $raw = $request->query('months', '');
        if (is_array($raw)) {
            $months = $raw;
        } else {
            $months = preg_split('/[,;\s]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $months = collect($months)->map(fn ($month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $months ?: range(1, 12);
    }

    /** @return array{rows:array<int,array<string,mixed>>,chart:array<string,mixed>,has_planned_data:bool} */
    private function emptyPayload(array $months): array
    {
        $definitions = AnnualBusinessPlanMetric::metricDefinitions();
        $rows = [];
        foreach ($definitions as $code => $definition) {
            $monthCells = [];
            foreach (range(1, 12) as $month) {
                $monthCells[$month] = ['planned' => 0.0, 'actual' => 0.0, 'ratio' => null];
            }
            $rows[] = [
                'code' => (string) $code,
                'name' => $definition['label'],
                'format' => $definition['format'],
                'total' => ['planned' => 0.0, 'actual' => 0.0, 'ratio' => null],
                'months' => $monthCells,
            ];
        }

        $selectedMonths = array_values(array_intersect(range(1, 12), $months)) ?: range(1, 12);

        return [
            'rows' => $rows,
            'chart' => [
                'categories' => array_map(fn (int $month): string => 'Tháng '.$month, $selectedMonths),
                'revenue_planned' => array_fill(0, count($selectedMonths), 0.0),
                'revenue_actual' => array_fill(0, count($selectedMonths), 0.0),
                'profit_planned' => array_fill(0, count($selectedMonths), 0.0),
                'profit_actual' => array_fill(0, count($selectedMonths), 0.0),
            ],
            'has_planned_data' => false,
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,chart:array<string,mixed>,has_planned_data:bool} */
    private function buildPayload(int $year, array $months, string $discountMode): array
    {
        $definitions = AnnualBusinessPlanMetric::metricDefinitions();
        $plans = Schema::hasTable('annual_business_plan_metrics')
            ? AnnualBusinessPlanMetric::query()
                ->where('year', $year)
                ->whereIn('month', range(1, 12))
                ->get()
                ->groupBy(fn (AnnualBusinessPlanMetric $metric): string => $metric->metric_code.'.'.$metric->month)
            : collect();
        $actual = $this->actualMetricMatrix($year, $discountMode);
        $hasPlanned = $plans->isNotEmpty();

        $rows = [];
        foreach ($definitions as $code => $definition) {
            $totalPlanned = 0.0;
            $totalActual = 0.0;
            $monthCells = [];
            foreach (range(1, 12) as $month) {
                $planned = (float) optional($plans->get($code.'.'.$month)?->first())->planned_value;
                $actualValue = (float) ($actual[$month][$code] ?? 0);
                $totalPlanned += $planned;
                $totalActual += $actualValue;
                $monthCells[$month] = [
                    'planned' => $planned,
                    'actual' => $actualValue,
                    'ratio' => $planned > 0 ? round($actualValue / $planned * 100, 2) : null,
                ];
            }

            $rows[] = [
                'code' => (string) $code,
                'name' => $definition['label'],
                'format' => $definition['format'],
                'total' => [
                    'planned' => $totalPlanned,
                    'actual' => $totalActual,
                    'ratio' => $totalPlanned > 0 ? round($totalActual / $totalPlanned * 100, 2) : null,
                ],
                'months' => $monthCells,
            ];
        }

        $selectedMonths = array_values(array_intersect(range(1, 12), $months));
        $chart = [
            'categories' => array_map(fn (int $month): string => 'Tháng '.$month, $selectedMonths),
            'revenue_planned' => array_map(fn (int $month): float => (float) optional($plans->get('1.'.$month)?->first())->planned_value, $selectedMonths),
            'revenue_actual' => array_map(fn (int $month): float => (float) ($actual[$month]['1'] ?? 0), $selectedMonths),
            'profit_planned' => array_map(fn (int $month): float => (float) optional($plans->get('18.'.$month)?->first())->planned_value, $selectedMonths),
            'profit_actual' => array_map(fn (int $month): float => (float) ($actual[$month]['18'] ?? 0), $selectedMonths),
        ];

        return ['rows' => $rows, 'chart' => $chart, 'has_planned_data' => $hasPlanned];
    }

    /** @return array<int, array<string, float>> */
    private function actualMetricMatrix(int $year, string $discountMode): array
    {
        $matrix = [];
        if (! Schema::hasTable('orders')) {
            foreach (range(1, 12) as $month) {
                $matrix[$month] = $this->emptyActualMonth();
            }

            return $matrix;
        }

        foreach (range(1, 12) as $month) {
            $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $end = $start->endOfMonth();

            $base = Order::query()
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('data_arrived_at', [$start, $end])
                        ->orWhere(function ($fallback) use ($start, $end): void {
                            $fallback->whereNull('data_arrived_at')->whereBetween('created_at', [$start, $end]);
                        });
                });

            $agg = (clone $base)
                ->selectRaw('COUNT(*) as order_count')
                ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(contact_count, 1), 1)), 0) as contact_sum')
                ->selectRaw('COALESCE(SUM(COALESCE(discount, 0)), 0) as discount_sum')
                ->selectRaw('COALESCE(SUM(COALESCE(carrier_service_fee, 0) + COALESCE(carrier_return_fee, 0) + COALESCE(carrier_other_fee, 0) + COALESCE(cod_fee, 0) + COALESCE(shipping_support_fee, 0) + COALESCE(cod_support, 0)), 0) as service_cost')
                ->selectRaw('COALESCE(SUM(CASE WHEN closed_at IS NOT NULL OR closing_status IN (\'closed\', \'confirmed\', \'success\') THEN 1 ELSE 0 END), 0) as closed_count')
                ->selectRaw($discountMode === 'before_discount'
                    ? 'COALESCE(SUM(COALESCE(NULLIF(subtotal, 0), total + discount)), 0) as revenue'
                    : 'COALESCE(SUM(COALESCE(NULLIF(total, 0), GREATEST(0, COALESCE(subtotal, 0) - COALESCE(discount, 0) + COALESCE(shipping_fee_collected, 0)))), 0) as revenue')
                ->first();

            $orderCount = (int) ($agg->order_count ?? 0);
            $closedCount = (int) ($agg->closed_count ?? 0);
            $revenue = (float) ($agg->revenue ?? 0);
            $marketingBudget = (float) ($agg->discount_sum ?? 0);
            $serviceCost = (float) ($agg->service_cost ?? 0);

            $leads = Schema::hasTable('lead_ingestions')
                ? LeadIngestion::query()->whereBetween('created_at', [$start, $end])->count()
                : 0;
            $contacts = max($leads, (int) ($agg->contact_sum ?? 0));

            $productQty = 0.0;
            if ($orderCount > 0 && Schema::hasTable('order_items')) {
                $productQty = (float) DB::table('order_items')
                    ->whereIn('order_id', (clone $base)->select('id'))
                    ->sum('quantity');
            }

            $safeOrderCount = max(1, $orderCount);
            $productsPerOrder = $orderCount > 0 ? $productQty / $safeOrderCount : 0;
            $avgUnit = $productQty > 0 ? $revenue / $productQty : 0;
            $avgOrder = $orderCount > 0 ? $revenue / $orderCount : 0;
            $costOfGoods = $revenue * 0.38;
            $cost = $marketingBudget + $serviceCost + $costOfGoods;
            $profit = $revenue - $cost;

            $matrix[$month] = [
                '1' => round($revenue, 2),
                '2' => (float) $closedCount,
                '3' => (float) $contacts,
                '4' => $contacts > 0 ? round($closedCount / $contacts * 100, 2) : 0.0,
                '5' => round($avgOrder, 2),
                '6' => round($productsPerOrder, 2),
                '7' => round($avgUnit, 2),
                '8' => round($cost, 2),
                '9' => round($marketingBudget, 2),
                '10' => $revenue > 0 ? round($marketingBudget / $revenue * 100, 2) : 0.0,
                '11' => $contacts > 0 ? round($marketingBudget / $contacts, 2) : 0.0,
                '12' => 0.0,
                '13' => 0.0,
                '14' => 0.0,
                '15' => 0.0,
                '16' => round($serviceCost, 2),
                '17' => $revenue > 0 ? round($costOfGoods / $revenue * 100, 2) : 0.0,
                '18' => round($profit, 2),
            ];
        }

        return $matrix;
    }

    /** @return array<string, float> */
    private function emptyActualMonth(): array
    {
        return array_fill_keys(array_map('strval', range(1, 18)), 0.0);
    }

    /** @param array<int, array<string,mixed>> $rows */
    private function exportYearlyPlan(int $year, array $rows): StreamedResponse
    {
        $filename = 'KeHoachKinhDoanhNam-'.$year.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tên', 'Tổng dự kiến', 'Tổng thực tế', 'Tỉ lệ']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['total']['planned'],
                    $row['total']['actual'],
                    $row['total']['ratio'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
