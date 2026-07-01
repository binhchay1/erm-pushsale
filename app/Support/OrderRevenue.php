<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Models\MarketingSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Công thức doanh thu thống nhất: thu đơn − chi phí vận chuyển − chi quảng cáo. */
final class OrderRevenue
{
    private static function maxZero(string $expression): string
    {
        return "(CASE WHEN ({$expression}) < 0 THEN 0 ELSE ({$expression}) END)";
    }

    /**
     * Doanh thu gộp 1 đơn (sau giảm giá): ưu tiên total, fallback subtotal.
     */
    public static function grossAmountSql(string $table = 'orders'): string
    {
        $base = "COALESCE(NULLIF({$table}.total, 0), {$table}.subtotal, 0)";
        $afterDiscount = "({$base} - COALESCE({$table}.discount, 0))";

        return self::maxZero($afterDiscount);
    }

    /** Phí vận chuyển & COD do hãng thu (chi phí). */
    public static function shippingCostSql(string $table = 'orders'): string
    {
        return 'COALESCE('.$table.'.carrier_service_fee, 0)'
            .' + COALESCE('.$table.'.cod_fee, 0)'
            .' + COALESCE('.$table.'.shipping_support_fee, 0)'
            .' + COALESCE('.$table.'.cod_support, 0)';
    }

    /** Doanh thu ròng cấp đơn (chưa trừ ngân sách campaign). */
    public static function netAmountSql(string $table = 'orders'): string
    {
        $net = '('.self::grossAmountSql($table).') - ('.self::shippingCostSql($table).')';

        return self::maxZero($net);
    }

    /**
     * SQL biểu thức doanh thu 1 đơn (ròng sau phí VC).
     *
     * @deprecated alias — dùng netAmountSql()
     */
    public static function amountSql(string $table = 'orders'): string
    {
        return self::netAmountSql($table);
    }

    /** @param  Builder<\Illuminate\Database\Eloquent\Model>  $query */
    public static function scopeEligible(Builder $query): Builder
    {
        return $query->whereIn('delivery_status', DeliveryStatus::revenueEligible());
    }

    /**
     * Tổng ngân sách quảng cáo của các campaign liên quan (mỗi nguồn tính 1 lần).
     *
     * @param  Collection<int, int|string>|list<int|string>  $sourceIds
     */
    public static function marketingCostForSourceIds(Collection|array $sourceIds): int
    {
        $ids = collect($sourceIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) MarketingSource::query()->whereIn('id', $ids)->sum('budget');
    }

    /**
     * Tổng hợp thu/chi doanh thu trên tập đơn (đã lọc sẵn hoặc chưa).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $ordersQuery
     * @return array{gross: int, shipping_cost: int, marketing_cost: int, net: int}
     */
    public static function aggregate(Builder $ordersQuery): array
    {
        $eligible = self::scopeEligible(clone $ordersQuery);

        $gross = (int) (clone $eligible)
            ->selectRaw('SUM('.self::grossAmountSql().') as aggregate_value')
            ->value('aggregate_value');

        $shippingCost = (int) (clone $eligible)
            ->selectRaw('SUM('.self::shippingCostSql().') as aggregate_value')
            ->value('aggregate_value');

        $sourceIds = (clone $eligible)
            ->whereNotNull('marketing_source_id')
            ->distinct()
            ->pluck('marketing_source_id');

        $marketingCost = self::marketingCostForSourceIds($sourceIds);
        $net = max(0, $gross - $shippingCost - $marketingCost);

        return [
            'gross' => $gross,
            'shipping_cost' => $shippingCost,
            'marketing_cost' => $marketingCost,
            'net' => $net,
        ];
    }
}
