<?php

namespace App\Services\Reporting;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Jobs\Reports\BuildDailyReportFactsJob;
use App\Models\MarketingSource;
use App\Models\Reporting\ReportDailyClosure;
use App\Models\Reporting\ReportDailyLeadFact;
use App\Models\Reporting\ReportDailyOrderFact;
use App\Models\Reporting\ReportDailyProductFact;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ReportFactReader
{
    /** @var array<string,bool> */
    private array $closedRangeCache = [];

    /** @var array<string,bool> */
    private array $queuedMissingCache = [];

    public function __construct(
        private readonly ReportScopeResolver $scopeResolver,
    ) {}

    public function supports(ReportFilterData $filter, ?User $user = null): bool
    {
        $shapeSupported = $this->unsupportedFilters($filter) === [];

        if (! $shapeSupported || ! $user) {
            return $shapeSupported;
        }

        $range = $this->historicalRange($filter);
        if (! $range['from'] || ! $range['to']) {
            return true;
        }

        $cacheKey = implode(':', [
            $user->company_id,
            $range['from']->toDateString(),
            $range['to']->toDateString(),
        ]);

        if (array_key_exists($cacheKey, $this->closedRangeCache)) {
            return $this->closedRangeCache[$cacheKey];
        }

        $expected = $range['from']->diffInDays($range['to']) + 1;
        $closedDates = ReportDailyClosure::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('metric_date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->where('status', 'closed')
            ->pluck('metric_date')
            ->map(fn ($value) => CarbonImmutable::parse($value, config('reporting.timezone'))->toDateString())
            ->all();

        if (count($closedDates) === $expected) {
            return $this->closedRangeCache[$cacheKey] = true;
        }

        $missingDates = $this->missingDates($range['from'], $range['to'], $closedDates);
        $this->queueMissingFacts($user->company_id, $missingDates);

        // Small gaps can still fall back to indexed live queries. Large gaps must not trigger
        // full raw-table scans for every user request; reports will use the available facts while
        // reports:aggregate-sql / queued dirty-date jobs rebuild the missing historical days.
        $maxLiveFallbackDays = max(0, (int) config('reporting.max_live_fallback_days', 2));
        if (count($missingDates) <= $maxLiveFallbackDays) {
            return $this->closedRangeCache[$cacheKey] = false;
        }

        return $this->closedRangeCache[$cacheKey] = true;
    }

    /**
     * Fact tables are dimensional summaries. Free-text/detail-only filters still fall back to
     * live indexed queries because they need row-level text matching or EXISTS predicates.
     *
     * @return list<string>
     */
    public function unsupportedFilters(ReportFilterData $filter): array
    {
        $unsupported = [];
        foreach ([
            'search' => $filter->search,
            'order_id' => $filter->orderId,
            'min_product_quantity' => $filter->minProductQuantity,
            'max_product_quantity' => $filter->maxProductQuantity,
            'tracking_alert' => $filter->trackingAlert,
            'care_status' => $filter->careStatus,
            'operation_activity_status' => $filter->operationActivityStatus,
            'hide_no_phone' => $filter->hideNoPhone ? true : null,
            'no_closing_date_limit' => $filter->noClosingDateLimit ? true : null,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $unsupported[] = $key;
            }
        }

        // Product filters are supported by product facts. Mixed order-level KPI pages still need
        // live/detail logic to avoid over-counting orders that contain multiple products.
        if ($filter->productId || $filter->parentProductId) {
            $unsupported[] = 'order_level_product_filter';
        }

        return $unsupported;
    }


    /** @param list<string> $closedDates */
    private function missingDates(CarbonImmutable $from, CarbonImmutable $to, array $closedDates): array
    {
        $closed = array_fill_keys($closedDates, true);
        $missing = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            if (! isset($closed[$date])) {
                $missing[] = $date;
            }
        }

        return $missing;
    }

    /** @param list<string> $dates */
    private function queueMissingFacts(int $companyId, array $dates): void
    {
        foreach ($dates as $date) {
            $key = $companyId.':'.$date;
            if (isset($this->queuedMissingCache[$key])) {
                continue;
            }

            $this->queuedMissingCache[$key] = true;
            BuildDailyReportFactsJob::dispatch($companyId, $date, true);
        }
    }

    /** @return Builder<ReportDailyOrderFact> */
    public function orders(User $user, ReportFilterData $filter): Builder
    {
        $query = ReportDailyOrderFact::query()
            ->where('company_id', $user->company_id)
            ->where('date_basis', $this->dateBasis($filter->dateType));

        $this->applyDateRange($query, $filter);
        $this->applyCommonDimensions($query, $user, $filter);
        $this->applyOperationalRoleScope($query, $user, $filter);

        return $query
            ->when($filter->deliveryStatus, fn (Builder $q) => $q->where('delivery_status', $filter->deliveryStatus))
            ->when($filter->reconciliationStatus, fn (Builder $q) => $q->where('reconciliation_status', $filter->reconciliationStatus))
            ->when($filter->warehouseId, fn (Builder $q) => $q->where('warehouse_id', $filter->warehouseId))
            ->when($filter->shippingProvider, fn (Builder $q) => $q->where('shipping_provider', $filter->shippingProvider))
            ->when($filter->shippingMethod, fn (Builder $q) => $q->where('shipping_method', $filter->shippingMethod))
            ->when($filter->operationStage, fn (Builder $q) => $q->where('operation_stage', $filter->operationStage))
            ->when($filter->operationResult, function (Builder $q) use ($filter): void {
                if ($filter->operationResult === 'no_answer') {
                    $q->whereIn('operation_result', ['no_answer_1', 'no_answer_2', 'no_answer_3', 'no_answer_4', 'no_answer_5', 'no_answer_6']);
                    return;
                }
                $q->where('operation_result', $filter->operationResult);
            })
            ->when($filter->customerType, fn (Builder $q) => $q->where('customer_type', $filter->customerType))
            ->when($filter->warehouseCareStatus, fn (Builder $q) => $q->where('warehouse_care_status', $filter->warehouseCareStatus))
            ->when($filter->printedStatus, fn (Builder $q) => $q->where('printed_status', $filter->printedStatus))
            ->when($filter->depositStatus, fn (Builder $q) => $q->where('deposit_status', $filter->depositStatus))
            ->when($filter->closingStatus, fn (Builder $q) => $q->where('closing_status', $filter->closingStatus));
    }

    /** @return Builder<ReportDailyLeadFact> — lead_count theo LeadContactMetrics (không gồm fail/dup/review). */
    public function countableLeads(User $user, ReportFilterData $filter): Builder
    {
        return $this->leads($user, $filter)->whereNotIn('status', [
            LeadIngestionStatus::Duplicate->value,
            LeadIngestionStatus::NeedsReview->value,
            LeadIngestionStatus::Failed->value,
        ]);
    }

    /** @return Builder<ReportDailyLeadFact> */
    public function leads(User $user, ReportFilterData $filter): Builder
    {
        $query = ReportDailyLeadFact::query()->where('company_id', $user->company_id);
        $this->applyDateRange($query, $filter);
        $this->applyCommonDimensions($query, $user, $filter);
        $this->applyOperationalRoleScope($query, $user, $filter);

        return $query
            ->when($filter->sourceType, fn (Builder $q) => $q->where('platform', $filter->sourceType))
            ->when($filter->warehouseId, fn (Builder $q) => $q->where('warehouse_id', $filter->warehouseId))
            ->when($filter->deliveryStatus, fn (Builder $q) => $q->where('delivery_status', $filter->deliveryStatus))
            ->when($filter->reconciliationStatus, fn (Builder $q) => $q->where('reconciliation_status', $filter->reconciliationStatus));
    }

    /** @return Builder<ReportDailyProductFact> */
    public function products(User $user, ReportFilterData $filter): Builder
    {
        $query = ReportDailyProductFact::query()
            ->where('company_id', $user->company_id)
            ->where('date_basis', $this->dateBasis($filter->dateType));

        $this->applyDateRange($query, $filter);
        $this->applyCommonDimensions($query, $user, $filter);
        $this->applyOperationalRoleScope($query, $user, $filter);

        return $query
            ->when($filter->productId, fn (Builder $q) => $q->where('product_id', $filter->productId))
            ->when($filter->parentProductId, fn (Builder $q) => $q->where('parent_product_id', $filter->parentProductId))
            ->when($filter->warehouseId, fn (Builder $q) => $q->where('warehouse_id', $filter->warehouseId))
            ->when($filter->deliveryStatus, fn (Builder $q) => $q->where('delivery_status', $filter->deliveryStatus))
            ->when($filter->reconciliationStatus, fn (Builder $q) => $q->where('reconciliation_status', $filter->reconciliationStatus));
    }

    /** @return array{from:?CarbonImmutable,to:?CarbonImmutable} */
    public function historicalRange(ReportFilterData $filter): array
    {
        if (! $filter->dateFrom || ! $filter->dateTo) {
            return ['from' => null, 'to' => null];
        }

        $timezone = config('reporting.timezone');
        $from = CarbonImmutable::parse($filter->dateFrom, $timezone)->startOfDay();
        $to = CarbonImmutable::parse($filter->dateTo, $timezone)->startOfDay();
        $yesterday = CarbonImmutable::now($timezone)->startOfDay()->subDay();

        if ($from->greaterThan($yesterday)) {
            return ['from' => null, 'to' => null];
        }

        return ['from' => $from, 'to' => $to->min($yesterday)];
    }

    public function historicalFilter(ReportFilterData $filter): ?ReportFilterData
    {
        $range = $this->historicalRange($filter);

        return $range['from'] && $range['to']
            ? $filter->withDateRange($range['from'], $range['to']->endOfDay())
            : null;
    }

    public function liveFilter(ReportFilterData $filter): ?ReportFilterData
    {
        if (! $filter->dateFrom || ! $filter->dateTo) {
            return $filter;
        }

        $timezone = config('reporting.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $to = CarbonImmutable::parse($filter->dateTo, $timezone)->endOfDay();

        if ($to->lessThan($today)) {
            return null;
        }

        $from = CarbonImmutable::parse($filter->dateFrom, $timezone)->startOfDay()->max($today);

        return $filter->withDateRange($from, $to);
    }

    public function dateBasis(DateType $dateType): string
    {
        return $dateType->value;
    }

    private function applyDateRange(Builder $query, ReportFilterData $filter): void
    {
        if ($filter->dateFrom && $filter->dateTo) {
            $query->whereBetween('metric_date', [
                $filter->dateFrom->toDateString(),
                $filter->dateTo->toDateString(),
            ]);
        }
    }

    private function applyCommonDimensions(Builder $query, User $user, ReportFilterData $filter): void
    {
        $query
            ->when($filter->marketingSourceId, fn (Builder $q) => $q->where('marketing_source_id', $filter->marketingSourceId))
            ->when($filter->teamId, fn (Builder $q) => $q->where('team_id', $filter->teamId))
            ->when($filter->saleId, fn (Builder $q) => $q->where('sale_user_id', $filter->saleId))
            ->when($filter->marketerId, fn (Builder $q) => $q->where('marketer_user_id', $filter->marketerId));

        if ($filter->teamLeaderId) {
            $teamIds = Team::query()->where('leader_user_id', $filter->teamLeaderId)->pluck('id');
            $query->whereIn('team_id', $teamIds->isNotEmpty() ? $teamIds : [0]);
        }

        if ($filter->marketingTeamId && Schema::hasColumn($query->getModel()->getTable(), 'marketer_user_id')) {
            $marketerIds = User::query()->where('team_id', $filter->marketingTeamId)->pluck('id');
            $query->whereIn('marketer_user_id', $marketerIds->isNotEmpty() ? $marketerIds : [0]);
        }

        if ($filter->marketingTeamLeaderId && Schema::hasColumn($query->getModel()->getTable(), 'marketer_user_id')) {
            $teamIds = Team::query()->where('leader_user_id', $filter->marketingTeamLeaderId)->pluck('id');
            $marketerIds = User::query()->whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('marketer_user_id', $marketerIds->isNotEmpty() ? $marketerIds : [0]);
        }

        if ($user->role === UserRole::Sales) {
            $allowed = $this->scopeResolver->allowedSaleIds($user);
            if ($allowed !== null) {
                $query->whereIn('sale_user_id', $allowed);
            }
        }

        if ($user->role === UserRole::Marketing) {
            $marketerIds = $this->scopeResolver->allowedMarketerIds($user);
            $sourceIds = MarketingSource::query()
                ->whereIn('marketer_user_id', $marketerIds)
                ->pluck('id');

            $query->where(function (Builder $scope) use ($marketerIds, $sourceIds): void {
                $scope->whereIn('marketer_user_id', $marketerIds)
                    ->orWhereIn('marketing_source_id', $sourceIds);
            });
        }
    }

    private function applyOperationalRoleScope(Builder $query, User $user, ReportFilterData $filter): void
    {
        if ($user->role === UserRole::Warehouse) {
            $query->whereIn('delivery_status', ['waiting_waybill', 'picking_up', 'delivering', 'failed', 'returned'])
                ->when($filter->warehouseId, fn (Builder $q) => $q->where('warehouse_id', $filter->warehouseId));
        }

        if ($user->role === UserRole::Accounting) {
            $query->where(function (Builder $scope): void {
                $scope->whereIn('delivery_status', ['delivered', 'paid', 'returned', 'failed'])
                    ->orWhereIn('reconciliation_status', ['pending', 'reconciled']);
            });
        }
    }
}
