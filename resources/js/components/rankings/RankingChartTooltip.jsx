import { Crown } from 'lucide-react';

import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export function RankingChartTooltip({ active, payload }) {
    if (!active || !payload?.length) return null;

    const row = payload[0]?.payload;
    if (!row) return null;

    return (
        <div className="min-w-[220px] rounded-xl border border-border bg-popover p-3 text-xs shadow-lg">
            <div className="mb-2 flex items-center gap-2">
                <span className="flex size-9 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary">
                    {row.initials}
                </span>
                <div>
                    <p className="font-semibold text-foreground">{row.name}</p>
                    <p className="text-muted-foreground">@{row.username}</p>
                </div>
            </div>
            <div className="space-y-1 text-muted-foreground">
                <p>
                    <span className="font-medium text-foreground">Hạng #{row.rank}</span>
                    {row.team ? ` · ${row.team}` : ''}
                </p>
                <p>Doanh số chốt: {formatCurrency(row.revenue)}</p>
                <p>Đơn chốt: {formatNumber(row.orders)}</p>
                <p>TB/đơn: {formatCurrency(row.avgOrderValue)}</p>
            </div>
        </div>
    );
}

const podiumStyles = {
    1: 'border-amber-400 bg-gradient-to-b from-amber-50 to-amber-100/80 shadow-amber-200/60 dark:from-amber-500/20 dark:to-amber-500/5 dark:border-amber-500/50',
    2: 'border-slate-300 bg-gradient-to-b from-slate-50 to-slate-100/80 dark:from-slate-500/15 dark:to-slate-500/5 dark:border-slate-500/40',
    3: 'border-orange-300 bg-gradient-to-b from-orange-50 to-orange-100/80 dark:from-orange-500/15 dark:to-orange-500/5 dark:border-orange-500/40',
};

function PodiumAvatar({ row, highlightUserId }) {
    const isMe = highlightUserId === row.id;

    return (
        <div
            className={cn(
                'relative flex flex-col items-center gap-2 rounded-2xl border px-3 py-4 transition-transform hover:-translate-y-0.5',
                podiumStyles[row.rank] ?? 'border-border bg-card',
                isMe && 'ring-2 ring-primary ring-offset-2 ring-offset-background'
            )}
        >
            {row.rank === 1 && (
                <Crown className="absolute -top-3 size-5 text-amber-500 drop-shadow-sm" />
            )}
            <span
                className={cn(
                    'flex size-14 items-center justify-center rounded-full text-lg font-bold shadow-inner',
                    row.rank === 1 && 'bg-amber-400 text-amber-950',
                    row.rank === 2 && 'bg-slate-300 text-slate-800',
                    row.rank === 3 && 'bg-orange-400 text-orange-950',
                    row.rank > 3 && 'bg-primary/15 text-primary'
                )}
            >
                {row.initials}
            </span>
            <span className="text-lg font-bold tabular-nums text-foreground">#{row.rank}</span>
            <p className="max-w-[120px] truncate text-center text-sm font-medium">{row.name}</p>
            <p className="text-xs font-semibold tabular-nums text-primary">{formatCurrency(row.revenue)}</p>
        </div>
    );
}

export function barFill(rank, isMe) {
    if (isMe) return 'var(--primary)';
    if (rank === 1) return '#f59e0b';
    if (rank === 2) return '#94a3b8';
    if (rank === 3) return '#fb923c';
    return 'var(--chart-2)';
}

export { PodiumAvatar };
