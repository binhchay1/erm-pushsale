<?php

namespace App\Services\Reporting;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Reporting\ReportDailyClosure;
use App\Models\Reporting\ReportDailyLeadFact;
use App\Models\Reporting\ReportDailyOrderFact;
use App\Models\Reporting\ReportDailyProductFact;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReportFactReader
{
    /** @var array<string,bool> */
    private array $closedRangeCache = [];

    public function __construct(
        private readonly ReportScopeResolver $scopeResolver,
    ) {}

    public function supports(ReportFilterData $filter, ?User $user = null): bool
    {
        $shapeSupported = $filter->search === null
            && $filter->parentProductId === null
            && $filter->productId === null
            && $filter->minProductQuantity === null
            && $filter->maxProductQuantity === null
            && $filter->careStatus === null
            && $filter->operationActivityStatus === null
            && $filter->trackingAlert === null
            && $filter->printedStatus === null
            && $filter->depositStatus === null
            && ! $filter->noClosingDateLimit;

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
        $closed = ReportDailyClosure::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('metric_date', [$range['from']->toDateString(), $range['to']->toDateString()])
            ->where('status', 'closed')
            ->count();

        return $this->closedRangeCache[$cacheKey] = ($closed === $expected);
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
            ->when($filter->operationStage, fn (Builder $q) => $q->where('operation_stage', $filter->operationStage))
            ->when($filter->closingStatus, fn (Builder $q) => $q->where('closing_status', $filter->closingStatus));
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

        if ($user->role === UserRole::Sales) {
            $query->whereIn('sale_user_id', $this->scopeResolver->allowedSaleIds($user));
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
