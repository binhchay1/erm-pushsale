<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Models\MarketingSource;
use App\Services\Marketing\MarketingBudgetService;
use Carbon\CarbonInterface;
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
     * Doanh thu gộp 1 đơn = GIÁ TRỊ CUỐI của đơn.
     *
     * `total` đã là giá trị cuối (đã gồm combo & trừ chiết khấu). Chỉ fallback
     * subtotal − discount cho đơn cũ chưa có total, tránh trừ chiết khấu 2 lần.
     */
    public static function grossAmountSql(string $table = 'orders'): string
    {
        $fallback = self::maxZero("COALESCE({$table}.subtotal, 0) - COALESCE({$table}.discount, 0)");

        return "(CASE WHEN COALESCE({$table}.total, 0) > 0 THEN {$table}.total ELSE {$fallback} END)";
    }

    /** Phí vận chuyển & COD do hãng thu (chi phí). */
    public static function shippingCostSql(string $table = 'orders'): string
    {
        return 'CASE WHEN ('
            .'COALESCE('.$table.'.carrier_service_fee, 0)'
            .' + COALESCE('.$table.'.carrier_return_fee, 0)'
            .' + COALESCE('.$table.'.carrier_other_fee, 0)'
            .' + COALESCE('.$table.'.cod_fee, 0)'
            .' + COALESCE('.$table.'.shipping_support_fee, 0)'
            .' + COALESCE('.$table.'.cod_support, 0)'
            .' - COALESCE('.$table.'.carrier_compensation_amount, 0)'
            .') < 0 THEN 0 ELSE ('
            .'COALESCE('.$table.'.carrier_service_fee, 0)'
            .' + COALESCE('.$table.'.carrier_return_fee, 0)'
            .' + COALESCE('.$table.'.carrier_other_fee, 0)'
            .' + COALESCE('.$table.'.cod_fee, 0)'
            .' + COALESCE('.$table.'.shipping_support_fee, 0)'
            .' + COALESCE('.$table.'.cod_support, 0)'
            .' - COALESCE('.$table.'.carrier_compensation_amount, 0)'
            .') END';
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
    public static function aggregate(Builder $ordersQuery, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $eligible = self::scopeEligible(clone $ordersQuery);

        $gross = (int) (clone $eligible)
            ->selectRaw('SUM('.self::grossAmountSql().') as aggregate_value')
            ->value('aggregate_value');

        // Phí giao/hoàn phát sinh cả với đơn giao thất bại, không chỉ đơn ghi nhận doanh thu.
        $shippingCost = (int) (clone $ordersQuery)
            ->whereNotNull('closed_at')
            ->selectRaw('SUM('.self::shippingCostSql().') as aggregate_value')
            ->value('aggregate_value');

        $sourceIds = (clone $eligible)
            ->whereNotNull('marketing_source_id')
            ->distinct()
            ->pluck('marketing_source_id');

        $marketing = ($from && $to)
            ? app(MarketingBudgetService::class)->effectiveForSourceIds($sourceIds, $from, $to)
            : [
                'amount' => self::marketingCostForSourceIds($sourceIds),
                'actual' => 0,
                'planned' => self::marketingCostForSourceIds($sourceIds),
                'basis' => 'legacy',
            ];
        $marketingCost = (int) $marketing['amount'];
        $net = $gross - $shippingCost - $marketingCost;

        return [
            'gross' => $gross,
            'shipping_cost' => $shippingCost,
            'marketing_cost' => $marketingCost,
            'marketing_actual' => (int) $marketing['actual'],
            'marketing_planned' => (int) $marketing['planned'],
            'marketing_basis' => $marketing['basis'],
            'net' => $net,
        ];
    }
}
