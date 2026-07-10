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
        'order_code', 'sale_user_id', 'marketer_user_id', 'team_id', 'marketing_source_id',
        'warehouse_id', 'product_id', 'customer_name', 'customer_phone', 'phone_carrier',
        'customer_note',         'shipping_address', 'shipping_address_2', 'receiver_name', 'receiver_phone', 'shipping_notes', 'accounting_notes',
        'internal_recon_note', 'shipping_geo', 'data_arrived_at', 'landing_upsell_hold_until', 'landing_upsell_locked',
        'assigned_at', 'closed_at', 'inventory_deducted_at',
        'desired_delivery_at', 'next_operation_at', 'operation_stage', 'operation_result', 'closing_status',
        'delivery_status', 'return_reason', 'return_restocked_at',
        'shipping_method', 'shipping_provider', 'carrier_name', 'tracking_number',
        'reconciliation_status', 'is_returning_customer', 'is_duplicate_phone',
        'subtotal', 'discount', 'vat', 'shipping_fee_collected', 'total', 'deposit',
        'amount_to_collect', 'settled_cod_amount', 'settlement_matched_at',
        'carrier_service_fee', 'shipping_support_fee',
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
            'desired_delivery_at' => 'datetime',
            'next_operation_at' => 'datetime',
            'settlement_matched_at' => 'datetime',
            'is_returning_customer' => 'boolean',
            'is_duplicate_phone' => 'boolean',
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
        return (int) (
            $this->carrier_service_fee
            + $this->cod_fee
            + $this->shipping_support_fee
            + $this->cod_support
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
            DateType::Closing => 'closed_at',
            default => 'data_arrived_at',
        };

        if ($filter->dateFrom && $filter->dateTo && ! $filter->noClosingDateLimit) {
            $query->whereBetween($column, [$filter->dateFrom, $filter->dateTo]);
        }

        if ($filter->deliveryStatus) {
            $query->where('delivery_status', $filter->deliveryStatus);
        }

        if ($filter->reconciliationStatus) {
            $query->where('reconciliation_status', $filter->reconciliationStatus);
        }

        if ($filter->productId) {
            $query->where('product_id', $filter->productId);
        }

        if ($filter->parentProductId) {
            $query->whereHas('product', fn ($q) => $q->where('parent_id', $filter->parentProductId));
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

        if ($filter->warehouseId) {
            $query->where('warehouse_id', $filter->warehouseId);
        }

        if ($filter->shippingMethod) {
            $query->where('shipping_method', $filter->shippingMethod);
        }

        if ($filter->operationStage) {
            $query->where('operation_stage', $filter->operationStage);
        }

        if ($filter->operationResult) {
            $query->where('operation_result', $filter->operationResult);
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
