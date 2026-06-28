<?php

namespace App\Models;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_code', 'sale_user_id', 'marketer_user_id', 'team_id', 'marketing_source_id',
        'warehouse_id', 'product_id', 'customer_name', 'customer_phone', 'phone_carrier',
        'customer_note',         'shipping_address', 'shipping_notes', 'accounting_notes',
        'internal_recon_note', 'shipping_geo', 'data_arrived_at', 'assigned_at', 'closed_at', 'inventory_deducted_at',
        'desired_delivery_at', 'next_operation_at', 'operation_stage', 'operation_result', 'closing_status',
        'delivery_status', 'return_reason', 'return_restocked_at',
        'shipping_method', 'shipping_provider', 'carrier_name', 'tracking_number',
        'reconciliation_status', 'is_returning_customer', 'is_duplicate_phone',
        'subtotal', 'discount', 'vat', 'shipping_fee_collected', 'total', 'deposit',
        'amount_to_collect', 'carrier_service_fee', 'shipping_support_fee',
        'cod_fee', 'cod_support', 'contact_count',
    ];

    protected function casts(): array
    {
        return [
            'data_arrived_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
            'inventory_deducted_at' => 'datetime',
            'return_restocked_at' => 'datetime',
            'desired_delivery_at' => 'datetime',
            'next_operation_at' => 'datetime',
            'is_returning_customer' => 'boolean',
            'is_duplicate_phone' => 'boolean',
            'shipping_geo' => 'array',
        ];
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

    public function shippingApiLogs(): HasMany
    {
        return $this->hasMany(ShippingApiLog::class);
    }

    public function effectiveRevenue(): int
    {
        $base = $this->total > 0 ? $this->total : $this->subtotal;

        return (int) max(0, $base - $this->discount);
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
