<?php

namespace App\Services\Reports;

use App\Data\MarketingDashboardFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DateType;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\MarketingSourceDailyMetric;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\LeadContactMetrics;
use App\Support\OrderRevenueClassifier;
use App\Services\Marketing\MarketingBudgetService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PushsaleMarketingDashboardService
{
    private ?MarketingDashboardFilterData $activeFilter = null;

    public function __construct(
        private readonly MarketingBudgetService $budgetService,
    ) {}

    /** @return array<string, mixed> */
    public function build(MarketingDashboardFilterData $filter): array
    {
        $this->activeFilter = $filter;
        $sources = $this->sourceQuery($filter)
            ->with([
                'product:id,parent_id,name',
                'children.product:id,parent_id,name',
                'marketer:id,name,email,team_id,manager_user_id',
            ])
            ->get();

        $allSourceIds = $sources
            ->flatMap(fn (MarketingSource $source) => collect([$source->id])->merge($source->children->pluck('id')))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $ingestions = $this->ingestionQuery($filter, $allSourceIds)->get();
        $orders = $this->orderQuery($filter, $allSourceIds)->get();
        $dailyMetrics = $this->dailyMetricQuery($filter, $allSourceIds)->get();

        $orderUtm = $this->orderUtmMap($orders);
        $groups = $sources->map(function (MarketingSource $source) use ($ingestions, $orders, $dailyMetrics, $orderUtm): array {
            $familyIds = collect([$source->id])
                ->merge($source->children->pluck('id'))
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $root = $this->makeRow(
                source: $source,
                sourceIds: $familyIds,
                ingestions: $ingestions,
                orders: $orders,
                dailyMetrics: $dailyMetrics,
                orderUtm: $orderUtm,
                level: 1,
                utmSource: null,
                utmCampaign: null,
            );

            $utmPairs = $this->utmPairs($familyIds, $ingestions, $dailyMetrics, $orderUtm, $orders);
            $children = collect();

            foreach ($utmPairs->groupBy('utm_source') as $utmSource => $pairs) {
                $utmSourceValue = (string) $utmSource;
                $sourceRow = $this->makeRow(
                    source: $source,
                    sourceIds: $familyIds,
                    ingestions: $ingestions,
                    orders: $orders,
                    dailyMetrics: $dailyMetrics,
                    orderUtm: $orderUtm,
                    level: 2,
                    utmSource: $utmSourceValue,
                    utmCampaign: null,
                );
                $sourceRow['rowKey'] = 's'.$source->id.'-u'.md5($utmSourceValue);
                $sourceRow['parentKey'] = 's'.$source->id;
                $sourceRow['hasChildren'] = $pairs->pluck('utm_campaign')->filter(fn ($v) => $v !== '')->unique()->isNotEmpty();
                $children->push($sourceRow);

                foreach ($pairs->pluck('utm_campaign')->unique()->filter(fn ($value) => (string) $value !== '') as $utmCampaign) {
                    $campaignRow = $this->makeRow(
                        source: $source,
                        sourceIds: $familyIds,
                        ingestions: $ingestions,
                        orders: $orders,
                        dailyMetrics: $dailyMetrics,
                        orderUtm: $orderUtm,
                        level: 3,
                        utmSource: $utmSourceValue,
                        utmCampaign: (string) $utmCampaign,
                    );
                    $campaignRow['rowKey'] = 's'.$source->id.'-u'.md5($utmSourceValue).'-c'.md5((string) $utmCampaign);
                    $campaignRow['parentKey'] = 's'.$source->id.'-u'.md5($utmSourceValue);
                    $campaignRow['hasChildren'] = false;
                    $children->push($campaignRow);
                }
            }

            $root['rowKey'] = 's'.$source->id;
            $root['parentKey'] = null;
            $root['hasChildren'] = $children->isNotEmpty();

            return ['root' => $root, 'children' => $children->values()->all()];
        });

        if ($filter->contactMode === 'has') {
            $groups = $groups->filter(fn (array $group): bool => (int) $group['root']['contacts'] > 0);
        } elseif ($filter->contactMode === 'none') {
            $groups = $groups->filter(fn (array $group): bool => (int) $group['root']['contacts'] === 0);
        }

        $groups = $this->sortGroups($groups->values(), $filter->sortBy);
        $total = $groups->count();
        $lastPage = max(1, (int) ceil($total / $filter->perPage));
        $page = min($filter->page, $lastPage);
        $pageGroups = $groups->slice(($page - 1) * $filter->perPage, $filter->perPage)->values();

        $filterTotal = $this->aggregate($groups->pluck('root'));
        $pageTotal = $this->aggregate($pageGroups->pluck('root'));
        $rows = $pageGroups->flatMap(fn (array $group) => collect([$group['root']])->merge($group['children']))->values();

        return [
            'rows' => $rows->all(),
            'filterTotal' => $filterTotal,
            'pageTotal' => $pageTotal,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $filter->perPage,
                'total' => $total,
                'from' => $total === 0 ? 0 : (($page - 1) * $filter->perPage) + 1,
                'to' => min($page * $filter->perPage, $total),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function options(?User $user): array
    {
        $marketingTeams = Team::query()
            ->where('type', TeamType::Marketing->value)
            ->orderBy('name')
            ->get(['id', 'name', 'leader_user_id']);

        $leaders = User::query()
            ->where('role', UserRole::Marketing->value)
            ->where(function (Builder $query) use ($marketingTeams): void {
                $query->where('is_team_leader', true)
                    ->orWhereIn('id', $marketingTeams->pluck('leader_user_id')->filter());
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $marketers = User::query()
            ->where('role', UserRole::Marketing->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team_id', 'manager_user_id']);

        $products = Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'parent_id', 'name']);

        return [
            'teamLeaders' => $leaders->map(fn (User $item) => $this->userOption($item))->values(),
            'teams' => $marketingTeams->map(fn (Team $item) => ['id' => $item->id, 'name' => $item->name, 'leader_user_id' => $item->leader_user_id])->values(),
            'marketingUsers' => $marketers->map(fn (User $item) => array_merge($this->userOption($item), [
                'team_id' => $item->team_id,
                'manager_user_id' => $item->manager_user_id,
            ]))->values(),
            'parentProducts' => $products->whereNull('parent_id')->map(fn (Product $item) => ['id' => $item->id, 'name' => $item->name])->values(),
            'products' => $products->map(fn (Product $item) => ['id' => $item->id, 'name' => $item->name, 'parent_id' => $item->parent_id])->values(),
            'sources' => MarketingSource::query()->orderBy('name')->get(['id', 'name'])->map(fn (MarketingSource $item) => ['id' => $item->id, 'name' => $item->name])->values(),
            'adChannels' => MarketingSource::query()->whereNotNull('ad_channel')->where('ad_channel', '<>', '')
                ->distinct()->orderBy('ad_channel')->pluck('ad_channel')->map(fn (string $value) => ['value' => $value, 'label' => $value])->values(),
            'dateTypes' => [
                ['value' => DateType::DataArrival->value, 'label' => '--Chuẩn Pushsale--'],
                ['value' => DateType::Closing->value, 'label' => 'Ngày sale chốt đơn'],
                ['value' => DateType::CareUpdate->value, 'label' => 'Ngày sale tác nghiệp'],
                ['value' => DateType::SaleReceived->value, 'label' => 'Ngày sale nhận data'],
                ['value' => DateType::Posting->value, 'label' => 'Ngày đăng đơn'],
            ],
            'operationScopes' => [
                ['value' => 'required', 'label' => 'Tác nghiệp cần'],
                ['value' => 'next', 'label' => 'Tác nghiệp tiếp'],
            ],
            'customerTypes' => [
                ['value' => 'new', 'label' => 'Khách mới'],
                ['value' => 'returning', 'label' => 'Khách cũ'],
            ],
            'contactModes' => [
                ['value' => 'has', 'label' => 'Có Contact (Hoặc chốt đơn)'],
                ['value' => 'none', 'label' => 'Không có contact về'],
            ],
            'sourceTypes' => [
                ['value' => 'facebook', 'label' => 'Nguồn Facebook'],
                ['value' => 'landing', 'label' => 'Nguồn nguồn dữ liệu'],
                ['value' => 'website', 'label' => 'Nguồn Website'],
            ],
            'sortOptions' => [
                ['value' => 'contacts', 'label' => 'Số contact'],
                ['value' => 'closing_rate', 'label' => 'Tỷ lệ chốt'],
                ['value' => 'revenue', 'label' => 'Doanh số'],
                ['value' => 'budget_revenue', 'label' => 'Ngân sách trên doanh số'],
            ],
            'revenueModes' => [
                ['value' => 'total', 'label' => '1.Doanh số tổng'],
                ['value' => 'confirmed', 'label' => '2.Doanh số xác nhận'],
                ['value' => 'temporary', 'label' => '3.Doanh số tạm tính'],
                ['value' => 'discount', 'label' => '4.Tiền CK DS số tạm tính'],
                ['value' => 'cancelled', 'label' => '5.Doanh số hủy'],
                ['value' => 'returning', 'label' => '6.Doanh số đang hoàn'],
                ['value' => 'returned', 'label' => '7.Doanh số đã hoàn'],
                ['value' => 'shipping', 'label' => '8.Doanh số đang chuyển'],
                ['value' => 'delivered', 'label' => '9.Doanh số giao thành công'],
                ['value' => 'actual', 'label' => '10.Doanh số thực tế'],
                ['value' => 'reconciling', 'label' => '11.Doanh số chờ đối soát'],
                ['value' => 'partial', 'label' => '12.Doanh số giao hàng một phần'],
            ],
            'canEditDailyMetrics' => $user?->isAdmin() || $user?->role === UserRole::Marketing,
        ];
    }

    /** @return array<string, mixed> */
    public function chartData(MarketingDashboardFilterData $filter, MarketingSource $source, ?string $utmSource, ?string $utmCampaign): array
    {
        $from = $filter->dateFrom->copy();
        $to = $filter->dateTo->copy();
        if ($from->diffInDays($to) > 30) {
            $from = $to->copy()->subDays(30)->startOfDay();
        }

        $sourceIds = collect([$source->id])->merge($source->children()->pluck('id'))->map(fn ($id) => (int) $id)->values();
        $chartFilter = new MarketingDashboardFilterData(
            dateFrom: $from,
            dateTo: $to,
            dateType: $filter->dateType,
            teamLeaderId: $filter->teamLeaderId,
            teamId: $filter->teamId,
            marketerId: $filter->marketerId,
            operationScope: $filter->operationScope,
            customerType: $filter->customerType,
            contactMode: $filter->contactMode,
            sourceType: $filter->sourceType,
            adChannel: $filter->adChannel,
            parentProductId: $filter->parentProductId,
            productId: $filter->productId,
            utmKeyword: $filter->utmKeyword,
            sourceKeyword: $filter->sourceKeyword,
            sortBy: $filter->sortBy,
            revenueMode: $filter->revenueMode,
            advancedUtm: $filter->advancedUtm,
        );

        $metrics = $this->dailyMetricQuery($chartFilter, $sourceIds)->get();
        $ingestions = $this->ingestionQuery($chartFilter, $sourceIds)->get();
        $orders = $this->orderQuery($chartFilter, $sourceIds)->get();
        $orderUtm = $this->orderUtmMap($orders);

        $days = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->map(function (Carbon $day) use ($metrics, $ingestions, $orders, $orderUtm, $utmSource, $utmCampaign): array {
                $metricDay = $metrics->filter(fn (MarketingSourceDailyMetric $item): bool => $item->metric_date->isSameDay($day))
                    ->filter(fn (MarketingSourceDailyMetric $item): bool => $this->utmMatches($item->utm_source, $item->utm_campaign, $utmSource, $utmCampaign));
                $ingestionDay = $ingestions->filter(fn (LeadIngestion $item): bool => $item->created_at?->isSameDay($day) ?? false)
                    ->filter(fn (LeadIngestion $item): bool => $this->utmMatches((string) $item->utm_source, (string) $item->utm_campaign, $utmSource, $utmCampaign));
                $orderDay = $orders->filter(fn (Order $item): bool => ($item->closed_at ?? $item->data_arrived_at ?? $item->created_at)?->isSameDay($day) ?? false)
                    ->filter(fn (Order $item): bool => $this->utmMatches(
                        (string) ($orderUtm[$item->id]['utm_source'] ?? ''),
                        (string) ($orderUtm[$item->id]['utm_campaign'] ?? ''),
                        $utmSource,
                        $utmCampaign,
                    ));

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->format('d/m'),
                    'budget' => (int) $metricDay->sum('budget'),
                    'clicks' => (int) $metricDay->sum('clicks'),
                    'contacts' => $ingestionDay->count(),
                    'revenue' => (int) $orderDay->sum(fn (Order $order): int => $order->effectiveRevenue()),
                ];
            })->values();

        return [
            'title' => 'Dữ liệu landing '.count($days).' ngày gần đây',
            'source' => ['id' => $source->id, 'name' => $source->name],
            'utm_source' => $utmSource ?: '',
            'utm_campaign' => $utmCampaign ?: '',
            'days' => $days,
        ];
    }

    /** @return array<string, mixed> */
    public function dailyMetricRows(MarketingSource $source, Carbon $from, Carbon $to, ?string $utmSource, ?string $utmCampaign): array
    {
        if ($from->diffInDays($to) > 31) {
            $to = $from->copy()->addDays(31)->endOfDay();
        }

        $utmSource = trim((string) $utmSource);
        $utmCampaign = trim((string) $utmCampaign);
        $existing = MarketingSourceDailyMetric::query()
            ->where('marketing_source_id', $source->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->where('utm_source', $utmSource)
            ->where('utm_campaign', $utmCampaign)
            ->with('updater:id,name')
            ->get()
            ->keyBy(fn (MarketingSourceDailyMetric $item) => $item->metric_date->toDateString());

        $rows = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->map(function (Carbon $day) use ($existing, $source, $utmSource, $utmCampaign): array {
                /** @var MarketingSourceDailyMetric|null $metric */
                $metric = $existing->get($day->toDateString());
                $budget = (int) ($metric?->budget ?? 0);
                $clicks = (int) ($metric?->clicks ?? 0);

                return [
                    'id' => $metric?->id,
                    'marketing_source_id' => $source->id,
                    'source_name' => $source->name,
                    'product_name' => $source->product?->name ?? '—',
                    'ad_channel' => $source->ad_channel ?: '—',
                    'utm_source' => $utmSource,
                    'utm_campaign' => $utmCampaign,
                    'metric_date' => $day->toDateString(),
                    'display_date' => $day->format('d/m/Y'),
                    'budget' => $budget,
                    'clicks' => $clicks,
                    'status' => $metric === null ? 'missing' : (($budget > 0 && $clicks > 0) ? 'ready' : 'zero'),
                    'updated_at' => $metric?->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'updated_by' => $metric?->updater?->name,
                ];
            })->values();

        return [
            'source' => ['id' => $source->id, 'name' => $source->name],
            'rows' => $rows,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    public function saveDailyMetrics(User $actor, MarketingSource $source, array $rows): int
    {
        return DB::transaction(function () use ($actor, $source, $rows): int {
            $saved = 0;
            $dates = collect();
            $totalBudget = 0;
            $totalClicks = 0;
            $utmSources = collect();
            $utmCampaigns = collect();

            foreach ($rows as $row) {
                $date = Carbon::parse((string) ($row['metric_date'] ?? ''))->toDateString();
                $utmSource = trim((string) ($row['utm_source'] ?? ''));
                $utmCampaign = trim((string) ($row['utm_campaign'] ?? ''));
                $budget = max(0, (int) ($row['budget'] ?? 0));
                $clicks = max(0, (int) ($row['clicks'] ?? 0));

                $metric = MarketingSourceDailyMetric::query()->firstOrNew([
                    'marketing_source_id' => $source->id,
                    'metric_date' => $date,
                    'utm_source' => $utmSource,
                    'utm_campaign' => $utmCampaign,
                ]);
                $metric->budget = $budget;
                $metric->clicks = $clicks;
                $metric->updated_by_user_id = $actor->id;
                if (! $metric->exists) {
                    $metric->created_by_user_id = $actor->id;
                }
                $metric->save();

                $dates->push($date);
                $utmSources->push($utmSource);
                $utmCampaigns->push($utmCampaign);
                $totalBudget += $budget;
                $totalClicks += $clicks;
                $saved++;
            }

            ActivityLogger::log(
                ActivityLogger::MARKETING_DAILY_METRICS_UPDATED,
                $source,
                [
                    'metric_rows' => $saved,
                    'date_from' => $dates->min(),
                    'date_to' => $dates->max(),
                    'total_budget' => $totalBudget,
                    'total_clicks' => $totalClicks,
                    'utm_source' => $utmSources->filter()->unique()->count() === 1 ? $utmSources->filter()->first() : null,
                    'utm_campaign' => $utmCampaigns->filter()->unique()->count() === 1 ? $utmCampaigns->filter()->first() : null,
                ],
                $source->name,
                $actor,
            );

            return $saved;
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function exportRows(MarketingDashboardFilterData $filter): Collection
    {
        $rows = collect();
        $page = 1;

        do {
            $exportFilter = new MarketingDashboardFilterData(...array_merge(get_object_vars($filter), [
                'page' => $page,
                'perPage' => 100,
            ]));
            $report = $this->build($exportFilter);
            $rows = $rows->concat($report['rows']);
            $page++;
        } while ($page <= (int) ($report['pagination']['last_page'] ?? 1));

        return $rows->values();
    }

    /** @return Builder<MarketingSource> */
    private function sourceQuery(MarketingDashboardFilterData $filter): Builder
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->when($filter->marketerId, fn (Builder $q, int $id) => $q->where('marketer_user_id', $id))
            ->when($filter->teamId, fn (Builder $q, int $id) => $q->whereHas('marketer', fn (Builder $u) => $u->where('team_id', $id)))
            ->when($filter->teamLeaderId, function (Builder $q, int $id): void {
                $q->whereHas('marketer', function (Builder $u) use ($id): void {
                    $u->where('manager_user_id', $id)
                        ->orWhere('id', $id)
                        ->orWhereHas('team', fn (Builder $team) => $team->where('leader_user_id', $id));
                });
            })
            ->when($filter->productId, function (Builder $q, int $id): void {
                $q->where(function (Builder $source) use ($id): void {
                    $source->where('product_id', $id)
                        ->orWhereHas('children', fn (Builder $child) => $child->where('product_id', $id));
                });
            })
            ->when($filter->parentProductId, function (Builder $q, int $id): void {
                $q->where(function (Builder $source) use ($id): void {
                    $source->whereHas('product', fn (Builder $product) => $product->where('id', $id)->orWhere('parent_id', $id))
                        ->orWhereHas('children.product', fn (Builder $product) => $product->where('id', $id)->orWhere('parent_id', $id));
                });
            })
            ->when($filter->adChannel, function (Builder $q, string $value): void {
                $q->where(function (Builder $source) use ($value): void {
                    $source->where('ad_channel', $value)
                        ->orWhereHas('children', fn (Builder $child) => $child->where('ad_channel', $value));
                });
            })
            ->when($filter->sourceKeyword, function (Builder $q, string $value): void {
                $q->where(function (Builder $source) use ($value): void {
                    $source->where('name', 'like', '%'.$value.'%')
                        ->orWhereHas('children', fn (Builder $child) => $child->where('name', 'like', '%'.$value.'%'));
                });
            })
            ->when($filter->utmKeyword, function (Builder $q, string $value): void {
                $q->where(function (Builder $utm) use ($value): void {
                    $utm->where('utm_source', 'like', '%'.$value.'%')
                        ->orWhere('utm_campaign', 'like', '%'.$value.'%')
                        ->orWhereHas('children', fn (Builder $child) => $child->where('utm_source', 'like', '%'.$value.'%')->orWhere('utm_campaign', 'like', '%'.$value.'%'));
                });
            })
            ->when($filter->sourceType, function (Builder $q, string $type): void {
                match ($type) {
                    'facebook' => $q->where('ad_channel', 'like', '%Facebook%'),
                    'website' => $q->where('ad_channel', 'like', '%Website%'),
                    'landing' => $q->where(function (Builder $source): void {
                        $source->whereNull('ad_channel')->orWhere('ad_channel', 'not like', '%Website%');
                    }),
                    default => null,
                };
            })
            ->orderBy('name');
    }

    /** @param Collection<int, int> $sourceIds */
    private function ingestionQuery(MarketingDashboardFilterData $filter, Collection $sourceIds): Builder
    {
        $query = LeadContactMetrics::applyCountableScope(LeadIngestion::query())
            ->with('order:id,is_returning_customer,operation_stage,next_operation_at,assigned_at,closed_at,updated_at,created_at')
            ->whereIn('marketing_source_id', $sourceIds);

        $this->applyIngestionDate($query, $filter);

        if ($filter->customerType === 'new') {
            $query->whereHas('order', fn (Builder $q) => $q->where('is_returning_customer', false));
        } elseif ($filter->customerType === 'returning') {
            $query->whereHas('order', fn (Builder $q) => $q->where('is_returning_customer', true));
        }

        if ($filter->operationScope === 'next') {
            $query->whereHas('order', fn (Builder $q) => $q->whereNotNull('next_operation_at'));
        } elseif ($filter->operationScope === 'required') {
            $query->whereHas('order', fn (Builder $q) => $q->whereNotNull('operation_stage'));
        }

        return $query;
    }

    /** @param Collection<int, int> $sourceIds */
    private function orderQuery(MarketingDashboardFilterData $filter, Collection $sourceIds): Builder
    {
        $query = Order::query()
            ->with([
                'items:id,order_id,product_id,product_name,quantity,unit_price',
                'marketingSource:id,utm_source,utm_campaign',
                'leadPackets' => function (Builder $q): void {
                    LeadContactMetrics::applyCountableScope($q);
                    $q->oldest('id')->select(['id', 'order_id', 'marketing_source_id', 'utm_source', 'utm_campaign', 'payload', 'counts_as_lead', 'status']);
                },
            ])
            ->whereIn('marketing_source_id', $sourceIds);

        if ($filter->revenueMode === 'cancelled') {
            $query->where(function (Builder $cancelled): void {
                $cancelled->where('closing_status', ClosingStatus::Cancelled->value)
                    ->orWhereIn('delivery_status', ['cancel_waybill', 'cancel_closing', 'cancelled', 'canceled']);
            });
        } else {
            $query->where(function (Builder $closed): void {
                $closed->whereNotNull('closed_at')->orWhere('closing_status', ClosingStatus::Closed->value);
            });
        }

        if ($filter->revenueMode !== 'cancelled') {
            OrderRevenueClassifier::applyMode($query, $filter->revenueMode);
        }

        $this->applyOrderDate($query, $filter);

        if ($filter->customerType === 'new') {
            $query->where('is_returning_customer', false);
        } elseif ($filter->customerType === 'returning') {
            $query->where('is_returning_customer', true);
        }

        if ($filter->operationScope === 'next') {
            $query->whereNotNull('next_operation_at');
        } elseif ($filter->operationScope === 'required') {
            $query->whereNotNull('operation_stage');
        }

        if ($filter->productId) {
            $query->whereHas('items', fn (Builder $items) => $items->where('product_id', $filter->productId));
        } elseif ($filter->parentProductId) {
            $productIds = Product::query()->where('id', $filter->parentProductId)->orWhere('parent_id', $filter->parentProductId)->pluck('id');
            $query->whereHas('items', fn (Builder $items) => $items->whereIn('product_id', $productIds));
        }

        return $query;
    }

    /** @param Collection<int, int> $sourceIds */
    private function dailyMetricQuery(MarketingDashboardFilterData $filter, Collection $sourceIds): Builder
    {
        return MarketingSourceDailyMetric::query()
            ->whereIn('marketing_source_id', $sourceIds)
            ->whereBetween('metric_date', [$filter->dateFrom->toDateString(), $filter->dateTo->toDateString()]);
    }

    /** @param Builder<LeadIngestion> $query */
    private function applyIngestionDate(Builder $query, MarketingDashboardFilterData $filter): void
    {
        match ($filter->dateType) {
            DateType::Closing => $query->whereHas('order', fn (Builder $q) => $q->whereBetween('closed_at', [$filter->dateFrom, $filter->dateTo])),
            DateType::SaleReceived => $query->whereHas('order', fn (Builder $q) => $q->whereBetween('assigned_at', [$filter->dateFrom, $filter->dateTo])),
            DateType::CareUpdate => $query->whereHas('order', fn (Builder $q) => $q->whereBetween('updated_at', [$filter->dateFrom, $filter->dateTo])),
            DateType::Posting => $query->whereHas('order', fn (Builder $q) => $q->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo])),
            default => $query->whereBetween('lead_ingestions.created_at', [$filter->dateFrom, $filter->dateTo]),
        };
    }

    /** @param Builder<Order> $query */
    private function applyOrderDate(Builder $query, MarketingDashboardFilterData $filter): void
    {
        $column = match ($filter->dateType) {
            DateType::Closing => 'closed_at',
            DateType::SaleReceived => 'assigned_at',
            DateType::CareUpdate => 'updated_at',
            DateType::Posting => 'created_at',
            default => 'data_arrived_at',
        };

        $query->whereBetween($column, [$filter->dateFrom, $filter->dateTo]);
    }

    /** @param Collection<int, Order> $orders @return array<int, array{utm_source: string, utm_campaign: string}> */
    private function orderUtmMap(Collection $orders): array
    {
        return $orders->mapWithKeys(function (Order $order): array {
            $packet = $order->leadPackets->first();

            return [$order->id => [
                'utm_source' => trim((string) ($packet?->utm_source ?? $order->marketingSource?->utm_source ?? '')),
                'utm_campaign' => trim((string) ($packet?->utm_campaign ?? $order->marketingSource?->utm_campaign ?? '')),
            ]];
        })->all();
    }

    /**
     * @param Collection<int, int> $sourceIds
     * @param Collection<int, LeadIngestion> $ingestions
     * @param Collection<int, MarketingSourceDailyMetric> $metrics
     * @param array<int, array{utm_source: string, utm_campaign: string}> $orderUtm
     * @param Collection<int, Order> $orders
     * @return Collection<int, array{utm_source: string, utm_campaign: string}>
     */
    private function utmPairs(Collection $sourceIds, Collection $ingestions, Collection $metrics, array $orderUtm, Collection $orders): Collection
    {
        $pairs = collect();
        $ingestions->whereIn('marketing_source_id', $sourceIds)->each(function (LeadIngestion $item) use ($pairs): void {
            $pairs->push(['utm_source' => trim((string) $item->utm_source), 'utm_campaign' => trim((string) $item->utm_campaign)]);
        });
        $metrics->whereIn('marketing_source_id', $sourceIds)->each(function (MarketingSourceDailyMetric $item) use ($pairs): void {
            $pairs->push(['utm_source' => trim((string) $item->utm_source), 'utm_campaign' => trim((string) $item->utm_campaign)]);
        });
        $orders->whereIn('marketing_source_id', $sourceIds)->each(function (Order $item) use ($pairs, $orderUtm): void {
            $pairs->push($orderUtm[$item->id] ?? ['utm_source' => '', 'utm_campaign' => '']);
        });

        return $pairs->unique(fn (array $item): string => $item['utm_source'].'|'.$item['utm_campaign'])->values();
    }

    /**
     * @param Collection<int, int> $sourceIds
     * @param Collection<int, LeadIngestion> $ingestions
     * @param Collection<int, Order> $orders
     * @param Collection<int, MarketingSourceDailyMetric> $dailyMetrics
     * @param array<int, array{utm_source: string, utm_campaign: string}> $orderUtm
     * @return array<string, mixed>
     */
    private function makeRow(
        MarketingSource $source,
        Collection $sourceIds,
        Collection $ingestions,
        Collection $orders,
        Collection $dailyMetrics,
        array $orderUtm,
        int $level,
        ?string $utmSource,
        ?string $utmCampaign,
    ): array {
        $ingestionSet = $ingestions->whereIn('marketing_source_id', $sourceIds)
            ->filter(fn (LeadIngestion $item): bool => $this->utmMatches((string) $item->utm_source, (string) $item->utm_campaign, $utmSource, $utmCampaign));
        $orderSet = $orders->whereIn('marketing_source_id', $sourceIds)
            ->filter(fn (Order $item): bool => $this->utmMatches(
                (string) ($orderUtm[$item->id]['utm_source'] ?? ''),
                (string) ($orderUtm[$item->id]['utm_campaign'] ?? ''),
                $utmSource,
                $utmCampaign,
            ));
        $metricSet = $dailyMetrics->whereIn('marketing_source_id', $sourceIds)
            ->filter(fn (MarketingSourceDailyMetric $item): bool => $this->utmMatches($item->utm_source, $item->utm_campaign, $utmSource, $utmCampaign));

        $contacts = $ingestionSet->count();
        $budget = (int) $metricSet->sum('budget');
        $clicks = (int) $metricSet->sum('clicks');
        if ($level === 1) {
            // Luồng mới ghép thực chi/kế hoạch theo từng nguồn và từng ngày.
            // Nguồn cũ chưa có landing_connection vẫn dùng cột tương thích.
            $effective = $this->activeFilter
                ? $this->budgetService->effectiveForSourceIds(
                    $sourceIds,
                    $this->activeFilter->dateFrom,
                    $this->activeFilter->dateTo,
                )
                : ['amount' => 0];
            $connectionBudget = (int) ($effective['amount'] ?? 0);
            if ($connectionBudget > 0 || $metricSet->isNotEmpty()) {
                $budget = $connectionBudget;
            } elseif ($budget === 0) {
                $budget = (int) $source->budget + (int) $source->children->sum('budget');
            }
            if ($metricSet->isEmpty()) {
                $clicks = (int) $source->interactions + (int) $source->children->sum('interactions');
            }
        }
        $closedOrderSet = $orderSet->filter(fn (Order $order): bool => $order->closed_at !== null);
        $closedOrders = $closedOrderSet->count();
        $productQuantity = (int) $closedOrderSet->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
        $grossRevenue = (int) $closedOrderSet->sum(fn (Order $order): int => max((int) $order->subtotal, $order->items->sum(fn ($item) => (int) $item->quantity * (int) $item->unit_price)));
        $netRevenue = (int) $closedOrderSet->sum(fn (Order $order): int => $order->effectiveRevenue());
        $utmMedium = $level >= 3 ? $this->summarizePayloadUtm($ingestionSet, 'utm_medium') : '';
        $utmTerm = $level >= 3 ? $this->summarizePayloadUtm($ingestionSet, 'utm_term') : '';
        $utmContent = $level >= 3 ? $this->summarizePayloadUtm($ingestionSet, 'utm_content') : '';

        return [
            'id' => $source->id,
            'sourceId' => $source->id,
            'level' => $level,
            'sourceName' => $level === 1 ? $source->name : ($level === 2 ? $source->name : ''),
            'marketerName' => $source->marketer?->name,
            'productName' => $level === 1 ? ($source->product?->name ?? '—') : '',
            'adChannel' => $source->ad_channel ?: '—',
            'utmSource' => $utmSource ?? '',
            'utmCampaign' => $utmCampaign ?? '',
            'utmMedium' => $utmMedium,
            'utmTerm' => $utmTerm,
            'utmContent' => $utmContent,
            'budget' => $budget,
            'interactions' => $clicks,
            'contacts' => $contacts,
            'contactRate' => $clicks > 0 ? round($contacts / $clicks * 100, 2) : null,
            'costPerContact' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closedOrders' => $closedOrders,
            'closingRate' => $contacts > 0 ? round($closedOrders / $contacts * 100, 2) : 0,
            'productQuantity' => $productQuantity,
            'avgProductPerOrder' => $closedOrders > 0 ? round($productQuantity / $closedOrders, 2) : 0,
            'totalRevenue' => $grossRevenue,
            'revenueAfterDiscount' => $netRevenue,
            'budgetRevenueRatio' => $grossRevenue > 0 ? round($budget / $grossRevenue * 100, 2) : 0,
            'budgetNetRevenueRatio' => $netRevenue > 0 ? round($budget / $netRevenue * 100, 2) : 0,
        ];
    }

    /** @param Collection<int, LeadIngestion> $ingestions */
    private function summarizePayloadUtm(Collection $ingestions, string $key): string
    {
        $values = $ingestions
            ->map(function (LeadIngestion $lead) use ($key): string {
                $payload = is_array($lead->payload) ? $lead->payload : [];
                $value = data_get($payload, $key)
                    ?? data_get($payload, 'utm.'.$key)
                    ?? data_get($payload, 'tracking.'.$key)
                    ?? data_get($payload, 'query.'.$key)
                    ?? data_get($payload, 'form.'.$key);

                return trim(is_scalar($value) ? (string) $value : '');
            })
            ->filter()
            ->unique()
            ->values();

        if ($values->count() <= 1) {
            return (string) ($values->first() ?? '');
        }

        return $values->take(2)->implode(', ').($values->count() > 2 ? ' +'.($values->count() - 2) : '');
    }

    private function utmMatches(string $rowSource, string $rowCampaign, ?string $filterSource, ?string $filterCampaign): bool
    {
        $rowSource = trim($rowSource);
        $rowCampaign = trim($rowCampaign);

        if ($filterSource !== null && $rowSource !== trim($filterSource)) {
            return false;
        }
        if ($filterCampaign !== null && $rowCampaign !== trim($filterCampaign)) {
            return false;
        }

        return true;
    }

    /** @param Collection<int, array<string, mixed>> $groups @return Collection<int, array<string, mixed>> */
    private function sortGroups(Collection $groups, ?string $sortBy): Collection
    {
        $sorted = match ($sortBy) {
            'closing_rate' => $groups->sortByDesc(fn (array $group) => $group['root']['closingRate']),
            'revenue' => $groups->sortByDesc(fn (array $group) => $group['root']['revenueAfterDiscount']),
            'budget_revenue' => $groups->sortByDesc(fn (array $group) => $group['root']['budgetRevenueRatio']),
            default => $groups->sortByDesc(fn (array $group) => $group['root']['contacts']),
        };

        return $sorted->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows @return array<string, int|float|null> */
    private function aggregate(Collection $rows): array
    {
        $budget = (int) $rows->sum('budget');
        $clicks = (int) $rows->sum('interactions');
        $contacts = (int) $rows->sum('contacts');
        $closed = (int) $rows->sum('closedOrders');
        $products = (int) $rows->sum('productQuantity');
        $gross = (int) $rows->sum('totalRevenue');
        $net = (int) $rows->sum('revenueAfterDiscount');

        return [
            'budget' => $budget,
            'interactions' => $clicks,
            'contacts' => $contacts,
            'contactRate' => $clicks > 0 ? round($contacts / $clicks * 100, 2) : null,
            'costPerContact' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closedOrders' => $closed,
            'closingRate' => $contacts > 0 ? round($closed / $contacts * 100, 2) : 0,
            'productQuantity' => $products,
            'avgProductPerOrder' => $closed > 0 ? round($products / $closed, 2) : 0,
            'totalRevenue' => $gross,
            'revenueAfterDiscount' => $net,
            'budgetRevenueRatio' => $gross > 0 ? round($budget / $gross * 100, 2) : 0,
            'budgetNetRevenueRatio' => $net > 0 ? round($budget / $net * 100, 2) : 0,
        ];
    }

    /** @return array{id: int, name: string, label: string} */
    private function userOption(User $user): array
    {
        $username = strstr((string) $user->email, '@', true) ?: $user->email;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'label' => trim($user->name.' ('.$username.')'),
        ];
    }
}
