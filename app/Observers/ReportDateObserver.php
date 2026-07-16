<?php

namespace App\Observers;

use App\Models\CarrierSettlementLine;
use App\Models\InboundEvent;
use App\Models\LandingConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSourceDailyMetric;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\ShippingStatusEvent;
use App\Models\ShippingWebhookEvent;
use App\Models\WarehouseInventoryMovement;
use App\Models\WarehouseReturnReceipt;
use App\Models\WarehouseReturnReceiptLine;
use App\Services\Reporting\ReportDateDirtyTracker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class ReportDateObserver
{
    public function saved(Model $model): void
    {
        $this->markModel($model, 'saved');
    }

    public function deleted(Model $model): void
    {
        $this->markModel($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->markModel($model, 'restored');
    }

    private function markModel(Model $model, string $event): void
    {
        $tracker = app(ReportDateDirtyTracker::class);
        $companyId = (int) ($model->getAttribute('company_id') ?: $model->getOriginal('company_id'));
        if (! $companyId) {
            return;
        }

        $sourceType = class_basename($model);
        $sourceId = $model->getKey() ? (int) $model->getKey() : null;
        $reason = strtolower($sourceType).'.'.$event;

        $archiveDefinition = config('reporting.archive.sources.'.$model->getTable());
        if (is_array($archiveDefinition) && isset($archiveDefinition['date_column'])) {
            $dateColumn = (string) $archiveDefinition['date_column'];
            $tracker->markArchiveMonthStale(
                $companyId,
                $model->getTable(),
                $model->getAttribute($dateColumn) ?: $model->getOriginal($dateColumn),
                $reason,
            );
        }

        if ($model instanceof Order) {
            $this->markOrderDates($tracker, $model, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof LeadIngestion || $model instanceof InboundEvent) {
            $tracker->mark($companyId, $model->created_at ?: $model->getOriginal('created_at'), $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof MarketingSourceDailyMetric) {
            $date = $model->metric_date ?: $model->getOriginal('metric_date');
            if ($date) {
                $day = CarbonImmutable::parse($date, config('reporting.timezone'))->toDateString();
                $tracker->invalidateSnapshotsRange($companyId, $day, $day);
                $tracker->bumpCompanyRevision($companyId);
            }
            return;
        }

        if ($model instanceof WarehouseInventoryMovement) {
            $tracker->mark($companyId, $model->created_at ?: $model->getOriginal('created_at'), $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof ShippingStatusEvent) {
            $tracker->mark($companyId, $model->occurred_at ?: $model->created_at, $reason, $sourceType, $sourceId);
            $this->markRelatedOrder($tracker, $model->order_id, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof ShippingWebhookEvent) {
            $tracker->mark($companyId, $model->occurred_at ?: $model->received_at ?: $model->created_at, $reason, $sourceType, $sourceId);
            $this->markRelatedOrder($tracker, $model->order_id, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof CarrierSettlementLine) {
            $tracker->mark($companyId, $model->settled_at ?: $model->created_at, $reason, $sourceType, $sourceId);
            $this->markRelatedOrder($tracker, $model->order_id, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof Shipment) {
            $tracker->markMany(
                $companyId,
                collect(['submitted_at', 'posted_at', 'picked_up_at', 'delivered_at', 'returning_at', 'returned_at', 'cod_remitted_at', 'last_event_at', 'created_at', 'updated_at'])
                    ->map(fn (string $field) => $model->getAttribute($field) ?: $model->getOriginal($field)),
                $reason,
                $sourceType,
                $sourceId,
            );
            $this->markRelatedOrder($tracker, $model->order_id, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof OrderItem) {
            $this->markRelatedOrder($tracker, $model->order_id, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof WarehouseReturnReceipt || $model instanceof WarehouseReturnReceiptLine) {
            $tracker->mark($companyId, $model->created_at ?: $model->updated_at, $reason, $sourceType, $sourceId);
            $orderId = $model instanceof WarehouseReturnReceipt ? $model->order_id : $model->receipt?->order_id;
            $this->markRelatedOrder($tracker, $orderId, $companyId, $reason, $sourceType, $sourceId);
            return;
        }

        if ($model instanceof LandingConnection) {
            $this->markLandingBudgetDates($tracker, $model, $companyId, $reason, $sourceType, $sourceId);
        }
    }

    private function markRelatedOrder(
        ReportDateDirtyTracker $tracker,
        int|string|null $orderId,
        int $companyId,
        string $reason,
        string $sourceType,
        ?int $sourceId,
    ): void {
        if (! $orderId) {
            return;
        }

        $order = Order::query()->withoutGlobalScopes()->where('company_id', $companyId)->find($orderId);
        if ($order) {
            $this->markOrderDates($tracker, $order, $companyId, $reason, $sourceType, $sourceId);
        }
    }

    private function markOrderDates(
        ReportDateDirtyTracker $tracker,
        Order $order,
        int $companyId,
        string $reason,
        string $sourceType,
        ?int $sourceId,
    ): void {
        $dates = [];
        foreach ([
            'data_arrived_at', 'assigned_at', 'closed_at', 'created_at', 'updated_at',
            'next_operation_at', 'last_delivery_event_at', 'desired_delivery_at',
        ] as $field) {
            $dates[] = $order->getAttribute($field);
            $original = $order->getOriginal($field);
            if ($original && (string) $original !== (string) $order->getAttribute($field)) {
                $dates[] = $original;
            }
        }

        $tracker->markMany($companyId, $dates, $reason, $sourceType, $sourceId);
    }

    private function markLandingBudgetDates(
        ReportDateDirtyTracker $tracker,
        LandingConnection $connection,
        int $companyId,
        string $reason,
        string $sourceType,
        ?int $sourceId,
    ): void {
        $from = $connection->budget_start_date ?: $connection->created_at ?: today();
        $to = $connection->budget_end_date ?: $from;
        $start = CarbonImmutable::parse($from, config('reporting.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($to, config('reporting.timezone'))->startOfDay();

        if ($start->diffInDays($end) > 730) {
            $end = $start->addDays(730);
        }

        $tracker->invalidateSnapshotsRange(
            $companyId,
            $start->toDateString(),
            $end->toDateString(),
        );
        $tracker->bumpCompanyRevision($companyId);
    }
}
