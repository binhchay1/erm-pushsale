<?php

namespace App\Support;

use App\Models\MarketingSource;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * KPI Marketing theo chuẩn ads/CRM:
 * - attributed_revenue: doanh thu gán cho marketer (sau phí VC, KHÔNG trừ ngân sách quảng cáo)
 * - ad_spend: ngân sách/chi quảng cáo campaign
 * - roas: attributed_revenue / ad_spend
 * - net_contribution: attributed_revenue − ad_spend (lợi nhuận gộp sau ads)
 */
final class MarketingMetrics
{
    /**
     * @param  Collection<int, Order>  $eligibleOrders
     * @param  Collection<int, MarketingSource>|null  $campaigns
     * @return array{attributed_revenue: int, ad_spend: int, roas: float, net_contribution: int}
     */
    public static function summarize(Collection $eligibleOrders, ?Collection $campaigns = null): array
    {
        $attributedRevenue = (int) $eligibleOrders->sum(fn (Order $o) => $o->netRevenue());

        $campaigns ??= MarketingSource::query()
            ->whereIn('id', $eligibleOrders->pluck('marketing_source_id')->filter()->unique())
            ->get();

        $adSpend = (int) $campaigns->sum('budget');
        $roas = $adSpend > 0 ? round($attributedRevenue / $adSpend, 2) : 0.0;
        $netContribution = $attributedRevenue - $adSpend;

        return [
            'attributed_revenue' => $attributedRevenue,
            'ad_spend' => $adSpend,
            'roas' => $roas,
            'net_contribution' => $netContribution,
        ];
    }

    public static function roasLabel(float $roas): string
    {
        if ($roas <= 0) {
            return '—';
        }

        return number_format($roas, 2).'x';
    }
}
