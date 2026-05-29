import { Crown, Medal, Trophy } from 'lucide-react';

import { cn } from '@/lib/utils';
import { formatCurrency, formatNumber } from '@/lib/format';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';

const podiumStyles = {
    1: 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10',
    2: 'border-slate-300 bg-slate-50 dark:border-slate-500/40 dark:bg-slate-500/10',
    3: 'border-orange-300 bg-orange-50 dark:border-orange-500/40 dark:bg-orange-500/10',
};

const badgeStyles = {
    1: 'bg-amber-400 text-amber-950',
    2: 'bg-slate-300 text-slate-800',
    3: 'bg-orange-400 text-orange-950',
};

function RankBadge({ rank }) {
    const Icon = rank === 1 ? Crown : Medal;

    if (rank <= 3) {
        return (
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-full font-bold shadow-sm',
                    badgeStyles[rank]
                )}
            >
                <Icon className="size-4.5" />
            </span>
        );
    }

    return (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold text-muted-foreground">
            {rank}
        </span>
    );
}

export function RevenueRankingBoard({ items }) {
    const data = items ?? [];

    if (data.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                Chưa có doanh số chốt trong kỳ này.
            </p>
        );
    }

    const top10 = data.slice(0, 10);
    const rest = data.slice(10, 50);

    return (
        <div className="space-y-6">
            <div>
                <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Trophy className="size-4 text-amber-500" />
                    Top 10 doanh số
                </div>
                <div className="space-y-2">
                    {top10.map((row) => (
                        <div
                            key={row.id}
                            className={cn(
                                'flex items-center justify-between gap-3 rounded-xl border bg-card p-3 transition-colors hover:bg-muted/40',
                                row.rank <= 3 ? podiumStyles[row.rank] : 'border-border'
                            )}
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <RankBadge rank={row.rank} />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold">{row.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {row.team ?? 'Chưa có nhóm'} · {formatNumber(row.orders)} đơn chốt
                                    </p>
                                </div>
                            </div>
                            <div className="shrink-0 text-right">
                                <p className="text-sm font-bold tabular-nums text-foreground">
                                    {formatCurrency(row.revenue)}
                                </p>
                                <p className="text-[11px] text-muted-foreground">doanh số chốt</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {rest.length > 0 && (
                <div>
                    <div className="mb-3 text-sm font-semibold text-muted-foreground">
                        Hạng 11 – {10 + rest.length} (trong top 50)
                    </div>
                    <ScrollDataTable>
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <Th>#</Th>
                                    <Th>Nhân viên</Th>
                                    <Th>Nhóm</Th>
                                    <Th>Đơn chốt</Th>
                                    <Th>Doanh số chốt</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rest.map((row) => (
                                    <tr key={row.id} className="hover:bg-muted/30">
                                        <Td className="font-medium tabular-nums">{row.rank}</Td>
                                        <Td className="font-medium">{row.name}</Td>
                                        <Td>{row.team ?? '—'}</Td>
                                        <Td className="tabular-nums">{formatNumber(row.orders)}</Td>
                                        <Td className="tabular-nums">{formatCurrency(row.revenue)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </ScrollDataTable>
                </div>
            )}
        </div>
    );
}
