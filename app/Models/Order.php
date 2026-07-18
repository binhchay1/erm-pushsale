<?php

namespace App\Models;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_code', 'sale_user_id', 'marketer_user_id', 'team_id', 'marketing_source_id', 'landing_connection_id', 'landing_connection_source_id',
        'warehouse_id', 'product_id', 'customer_name', 'customer_phone', 'phone_carrier',
        'customer_note', 'sale_operation_note', 'shipping_address', 'shipping_address_2', 'receiver_name', 'receiver_phone', 'shipping_notes', 'accounting_notes',
        'internal_recon_note', 'shipping_geo', 'data_arrived_at', 'landing_upsell_hold_until', 'landing_upsell_locked',
        'assigned_at', 'closed_at', 'inventory_deducted_at',
        'desired_delivery_at', 'next_operation_at', 'operation_stage', 'operation_result', 'closing_status',
        'delivery_status', 'warehouse_care_status', 'warehouse_care_note', 'warehouse_care_user_id', 'printed_at', 'return_reason', 'return_restocked_at',
        'shipping_method', 'shipping_provider', 'carrier_name', 'tracking_number',
        'reconciliation_status', 'is_returning_customer', 'is_duplicate_phone', 'phone_lock_conflict', 'phone_lock_note',
        'subtotal', 'discount', 'vat', 'shipping_fee_collected', 'total', 'deposit',
        'amount_to_collect', 'settled_cod_amount', 'settlement_matched_at', 'last_delivery_event_at',
        'carrier_service_fee', 'carrier_return_fee', 'carrier_other_fee', 'carrier_compensation_amount', 'shipping_support_fee',
        'cod_fee', 'cod_support', 'contact_count',
    ];

    protected function casts(): array
    {
        return [
            'data_arrived_at' => 'datetime',
            'landing_upsell_hold_until' => 'datetime',
            'landing_upsell_locked' => 'boolean',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
            'inventory_deducted_at' => 'datetime',
            'return_restocked_at' => 'datetime',
            'printed_at' => 'datetime',
            'last_delivery_event_at' => 'datetime',
            'desired_delivery_at' => 'datetime',
            'next_operation_at' => 'datetime',
            'settlement_matched_at' => 'datetime',
            'is_returning_customer' => 'boolean',
            'is_duplicate_phone' => 'boolean',
            'phone_lock_conflict' => 'boolean',
            'shipping_geo' => 'array',
        ];
    }

    /**
     * Địa chỉ giao dùng cho vận chuyển / xuất dữ liệu:
     * ưu tiên địa chỉ sale đã xác nhận (shipping_address_2), nếu trống dùng địa chỉ gốc từ landing.
     */
    public function effectiveShippingAddress(): ?string
    {
        $confirmed = trim((string) ($this->shipping_address_2 ?? ''));

        return $confirmed !== '' ? $confirmed : $this->shipping_address;
    }

    /** Người nhận hàng thực tế: người nhận riêng nếu có, ngược lại là khách hàng. */
    public function effectiveReceiverName(): ?string
    {
        $name = trim((string) ($this->receiver_name ?? ''));

        return $name !== '' ? $name : $this->customer_name;
    }

    public function effectiveReceiverPhone(): ?string
    {
        $phone = trim((string) ($this->receiver_phone ?? ''));

        return $phone !== '' ? $phone : $this->customer_phone;
    }

    /** Đơn landing vẫn đang chờ upsale trang cảm ơn (đã chia số cho sale). */
    public function isAwaitingLandingUpsell(): bool
    {
        return ! $this->isLandingUpsellLocked()
            && $this->landing_upsell_hold_until !== null
            && $this->landing_upsell_hold_until->isFuture();
    }

    public function isLandingUpsellLocked(): bool
    {
        return (bool) $this->landing_upsell_locked;
    }

    public function saleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_user_id');
    }

    public function marketerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function landingConnection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class)->withTrashed();
    }

    public function landingConnectionSource(): BelongsTo
    {
        return $this->belongsTo(LandingConnectionSource::class)->withTrashed();
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function warehouseCareUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_care_user_id');
    }

    public function shippingStatusEvents(): HasMany
    {
        return $this->hasMany(ShippingStatusEvent::class);
    }

    public function returnReceipt(): HasOne
    {
        return $this->hasOne(WarehouseReturnReceipt::class);
    }

    public function settlementLines(): HasMany
    {
        return $this->hasMany(CarrierSettlementLine::class);
    }

    public function shippingApiLogs(): HasMany
    {
        return $this->hasMany(ShippingApiLog::class);
    }


    public function operationHistories(): HasMany
    {
        return $this->hasMany(OrderOperationHistory::class);
    }

    public function internalMessages(): HasMany
    {
        return $this->hasMany(CustomerInternalMessage::class);
    }

    public function pancakeCustomerMessages(): HasMany
    {
        return $this->hasMany(PancakeCustomerMessage::class);
    }

    /** Mọi packet landing đã gộp trực tiếp vào đơn. */
    public function leadPackets(): HasMany
    {
        return $this->hasMany(LeadIngestion::class, 'order_id');
    }

    /** Packet đến muộn/orphan được liên kết tới đơn để vận hành xử lý tay. */
    public function relatedLeadPackets(): HasMany
    {
        return $this->hasMany(LeadIngestion::class, 'related_order_id');
    }

    /** Packet upsell đang chờ admin/allocator quyết định. */
    public function pendingSupplementPackets(): HasMany
    {
        return $this->relatedLeadPackets()
            ->where('counts_as_lead', false)
            ->where('requires_review', true)
            ->whereNull('reviewed_at');
    }

    /**
     * Packet đã tạo ra chính đơn bổ sung này. Dùng để phân biệt một đơn upsell
     * tách hợp lệ với lỗi trùng đơn ngoài ý muốn trên mọi workspace.
     */
    public function supplementalOriginPacket(): HasOne
    {
        return $this->hasOne(LeadIngestion::class, 'order_id')
            ->where('counts_as_lead', false)
            ->whereNotNull('related_order_id')
            ->latestOfMany();
    }

    /**
     * Doanh thu gộp cấp đơn = GIÁ TRỊ CUỐI của đơn (đã gồm combo & trừ mọi chiết khấu).
     *
     * `total` đã là giá trị cuối (xem LeadOrderFactory::syncTotals). Chỉ khi đơn cũ
     * chưa có `total` mới fallback về subtotal − discount để không tính trùng chiết khấu.
     */
    public function effectiveRevenue(): int
    {
        if ((int) $this->total > 0) {
            return (int) $this->total;
        }

        return (int) max(0, (int) $this->subtotal - (int) $this->discount);
    }

    public function shippingCost(): int
    {
        return (int) max(0,
            $this->carrier_service_fee
            + $this->carrier_return_fee
            + $this->carrier_other_fee
            + $this->cod_fee
            + $this->shipping_support_fee
            + $this->cod_support
            - $this->carrier_compensation_amount
        );
    }

    /** Doanh thu ròng cấp đơn (sau phí VC, chưa trừ ngân sách campaign). */
    public function netRevenue(): int
    {
        return (int) max(0, $this->effectiveRevenue() - $this->shippingCost());
    }

    public function scopeApplyReportFilter(Builder $query, ReportFilterData $filter): Builder
    {
        $column = match ($filter->dateType) {
            DateType::SaleReceived => 'assigned_at',
            DateType::CareUpdate, DateType::DeliveryUpdate => 'updated_at',
            DateType::Closing => 'closed_at',
            DateType::Posting => 'created_at',
            DateType::NextOperation => 'next_operation_at',
            DateType::DesiredDelivery => 'desired_delivery_at',
            default => 'data_arrived_at',
        };

        if ($filter->orderId) {
            $query->whereKey($filter->orderId);
        }

        if (! $filter->orderId && $filter->dateFrom && $filter->dateTo && ! $filter->noClosingDateLimit) {
            $query->whereBetween($column, [$filter->dateFrom, $filter->dateTo]);
        }

        if ($filter->marketingSourceId) {
            $query->where('marketing_source_id', $filter->marketingSourceId);
        }

        if ($filter->deliveryStatus) {
            $query->where('delivery_status', $filter->deliveryStatus);
        }

        if ($filter->reconciliationStatus) {
            $query->where('reconciliation_status', $filter->reconciliationStatus);
        }

        if ($filter->productId) {
            $query->where(function (Builder $productQuery) use ($filter): void {
                $productQuery->where('product_id', $filter->productId)
                    ->orWhereHas('items', fn (Builder $items) => $items->where('product_id', $filter->productId));
            });
        }

        if ($filter->parentProductId) {
            $query->whereHas('product', fn ($q) => $q->where('parent_id', $filter->parentProductId));
        }

        if ($filter->teamLeaderId) {
            $query->whereHas('team', fn (Builder $team) => $team->where('leader_user_id', $filter->teamLeaderId));
        }

        if ($filter->teamId) {
            $query->where('team_id', $filter->teamId);
        }

        if ($filter->saleId) {
            $query->where('sale_user_id', $filter->saleId);
        }

        if ($filter->marketerId) {
            $query->where('marketer_user_id', $filter->marketerId);
        }

        if ($filter->marketingTeamLeaderId) {
            $query->whereHas('marketerUser.team', fn (Builder $team) => $team->where('leader_user_id', $filter->marketingTeamLeaderId));
        }

        if ($filter->marketingTeamId) {
            $query->whereHas('marketerUser', fn (Builder $marketer) => $marketer->where('team_id', $filter->marketingTeamId));
        }

        if ($filter->warehouseId) {
            $query->where('warehouse_id', $filter->warehouseId);
        }

        if ($filter->shippingMethod) {
            $query->where('shipping_method', $filter->shippingMethod);
        }

        if ($filter->shippingProvider) {
            $query->where('shipping_provider', $filter->shippingProvider);
        }

        if ($filter->warehouseCareStatus) {
            $query->where('warehouse_care_status', $filter->warehouseCareStatus);
        }

        if ($filter->printedStatus === 'printed') {
            $query->whereNotNull('printed_at');
        } elseif ($filter->printedStatus === 'not_printed') {
            $query->whereNull('printed_at');
        }

        if ($filter->depositStatus === 'with_deposit') {
            $query->where('deposit', '>', 0);
        } elseif ($filter->depositStatus === 'without_deposit') {
            $query->where('deposit', '<=', 0);
        }

        if ($filter->minProductQuantity !== null) {
            $query->whereRaw(
                '(SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items WHERE order_items.order_id = orders.id) >= ?',
                [$filter->minProductQuantity],
            );
        }

        if ($filter->maxProductQuantity !== null) {
            $query->whereRaw(
                '(SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items WHERE order_items.order_id = orders.id) <= ?',
                [$filter->maxProductQuantity],
            );
        }

        if ($filter->trackingAlert === 'missing') {
            $query->whereNull('tracking_number');
        } elseif ($filter->trackingAlert === 'has_error') {
            $query->whereHas('shipments', fn (Builder $shipment) => $shipment->whereNotNull('error_message'));
        } elseif ($filter->trackingAlert === 'stale') {
            $query->whereNotNull('tracking_number')
                ->where(function (Builder $stale): void {
                    $stale->whereNull('last_delivery_event_at')
                        ->orWhere('last_delivery_event_at', '<', now()->subHours(24));
                });
        }

        if ($filter->operationActivityStatus === 'not_operated') {
            $query->whereNull('operation_result')
                ->whereDoesntHave('operationHistories', fn (Builder $history) => $history->whereIn('action', [
                    OrderOperationHistory::ACTION_CALL,
                    OrderOperationHistory::ACTION_STATUS_UPDATED,
                    OrderOperationHistory::ACTION_ORDER_CLOSED,
                ]));
        } elseif ($filter->operationActivityStatus === 'operated') {
            $query->where(function (Builder $activity): void {
                $activity->whereNotNull('operation_result')
                    ->orWhereHas('operationHistories', fn (Builder $history) => $history->whereIn('action', [
                        OrderOperationHistory::ACTION_CALL,
                        OrderOperationHistory::ACTION_STATUS_UPDATED,
                        OrderOperationHistory::ACTION_ORDER_CLOSED,
                    ]));
            });
        }

        if ($filter->careStatus) {
            $query->where(function (Builder $care) use ($filter): void {
                match ($filter->careStatus) {
                    'waiting' => $care->whereNotNull('closed_at')
                        ->whereIn('operation_stage', ['care_1', 'care_2', 'care_3'])
                        ->whereNotIn('delivery_status', ['delivered', 'paid', 'delivery_complete', 'returned', 'refund']),
                    'deliver_now' => $care->where('delivery_status', 'deliver_now'),
                    'waiting_delivery' => $care->whereIn('delivery_status', ['waiting_waybill', 'posted', 'picking_up', 'delivering', 'redelivery']),
                    'postponed' => $care->whereNotNull('desired_delivery_at')
                        ->where('desired_delivery_at', '>', now())
                        ->whereNotIn('delivery_status', ['delivered', 'paid', 'delivery_complete']),
                    'saved' => $care->where('delivery_status', 'redelivery'),
                    'complaint' => $care->where(function (Builder $complaint): void {
                        $complaint->whereNotNull('return_reason')
                            ->orWhereIn('delivery_status', ['cannot_deliver', 'returned', 'returning']);
                    }),
                    'complaint_done' => $care->whereNotNull('return_reason')
                        ->whereIn('delivery_status', ['delivered', 'paid', 'delivery_complete', 'refund']),
                    default => $care,
                };
            });
        }

        if ($filter->operationStage && $filter->operationStage !== 'all') {
            $query->where('operation_stage', $filter->operationStage);
        }

        if ($filter->operationResult) {
            if ($filter->operationResult === 'no_answer') {
                $query->whereIn('operation_result', ['no_answer_1', 'no_answer_2', 'no_answer_3', 'no_answer_4', 'no_answer_5', 'no_answer_6']);
            } else {
                $query->where('operation_result', $filter->operationResult);
            }
        }

        if ($filter->closingStatus) {
            if ($filter->closingStatus === 'open') {
                $query->where(function (Builder $q) {
                    $q->whereNull('closing_status')
                        ->orWhere('closing_status', 'open');
                })->whereNull('closed_at');
            } else {
                $query->where('closing_status', $filter->closingStatus);
            }
        }

        if ($filter->search) {
            $term = '%'.$filter->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('order_code', 'like', $term);
            });
        }

        if ($filter->hideNoPhone) {
            $query->whereNotNull('customer_phone')->where('customer_phone', '!=', '');
        }

        return $query;
    }
}
