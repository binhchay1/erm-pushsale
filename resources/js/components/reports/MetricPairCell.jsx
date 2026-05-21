import { formatCurrency, formatNumber } from '@/lib/format';

export function MetricPairCell({ pair, showRevenue = true }) {
    if (!pair) return <span className="text-muted-foreground">—</span>;

    return (
        <div className="tabular-nums text-xs leading-relaxed">
            <div>{formatNumber(pair.qty)}</div>
            {showRevenue && (
                <div className="text-muted-foreground">{formatCurrency(pair.revenue)}</div>
            )}
        </div>
    );
}
