<?php

namespace App\Http\Controllers\Admin\Pushsale\Pages;

use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Pushsale\AnnualBusinessPlanMetric;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Page7_1_2Controller extends BasePushsalePageController
{
    protected string $pageCode = '7.1.2';

    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorizePage($request);
        $year = $this->yearFromRequest($request);
        $months = $this->monthsFromRequest($request);
        $discountMode = (string) $request->query('discount_mode', 'after_discount');
        $payload = $this->buildPayload($year, $months, $discountMode);

        if ($request->boolean('export')) {
            return $this->exportYearlyPlan($year, $payload['rows']);
        }

        return Inertia::render('Pushsale/Pages/Page_7_1_2', [
            'schema' => [
                'code' => '7.1.2',
                'title' => 'Lập kế hoạch kinh doanh',
                'component' => 'Page_7_1_2',
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
                'toast' => $payload['has_planned_data'] ? null : 'Mời bạn thêm số liệu dự kiến trước khi xem!',
            ],
            'filters' => [
                'year' => $year,
                'months' => $months,
                'discount_mode' => $discountMode,
            ],
            'routeUrl' => '/'.$request->path(),
            'activeMenuCode' => $this->pageCode,
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
    private function buildPayload(int $year, array $months, string $discountMode): array
    {
        $definitions = AnnualBusinessPlanMetric::metricDefinitions();
        $plans = AnnualBusinessPlanMetric::query()
            ->where('year', $year)
            ->whereIn('month', range(1, 12))
            ->get()
            ->groupBy(fn (AnnualBusinessPlanMetric $metric): string => $metric->metric_code.'.'.$metric->month);
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
        foreach (range(1, 12) as $month) {
            $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $end = $start->endOfMonth();
            $orders = Order::query()
                ->with('items:id,order_id,quantity,unit_price')
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('data_arrived_at', [$start, $end])
                        ->orWhere(function ($fallback) use ($start, $end): void {
                            $fallback->whereNull('data_arrived_at')->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->get();

            $leads = LeadIngestion::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $contacts = max($leads, (int) $orders->sum(fn (Order $order): int => max(1, (int) $order->contact_count)));
            $closedOrders = $orders->filter(fn (Order $order): bool => $order->closed_at !== null || in_array((string) $order->closing_status, ['closed', 'confirmed', 'success'], true));
            if ($closedOrders->isEmpty() && $orders->isNotEmpty()) {
                $closedOrders = $orders;
            }

            $revenue = (float) $orders->sum(function (Order $order) use ($discountMode): int|float {
                $subtotal = (int) ($order->subtotal ?: ($order->total + $order->discount));
                if ($discountMode === 'before_discount') {
                    return $subtotal;
                }

                return (int) ($order->total ?: max(0, $subtotal - (int) $order->discount + (int) $order->shipping_fee_collected));
            });
            $productQty = (float) $orders->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
            $orderCount = max(1, $orders->count());
            $closedCount = $closedOrders->count();
            $productsPerOrder = $orders->isNotEmpty() ? $productQty / $orderCount : 0;
            $avgUnit = $productQty > 0 ? $revenue / $productQty : 0;
            $avgOrder = $orders->isNotEmpty() ? $revenue / $orderCount : 0;
            $marketingBudget = (float) $orders->sum('discount');
            $serviceCost = (float) $orders->sum(fn (Order $order): int => (int) $order->carrier_service_fee + (int) $order->carrier_return_fee + (int) $order->carrier_other_fee + (int) $order->cod_fee + (int) $order->shipping_support_fee + (int) $order->cod_support);
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
