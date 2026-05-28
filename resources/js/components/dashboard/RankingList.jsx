import { Trophy } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

const rankClassNames = [
    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
    'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
];

export function RankingList({ title, description, rows, type = 'sales' }) {
    const data = rows ?? [];

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>
                {data.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Chưa có dữ liệu xếp hạng.
                    </p>
                ) : (
                    <div className="space-y-3">
                        {data.map((row, index) => (
                            <div
                                key={`${row.name}-${index}`}
                                className="flex items-center justify-between gap-3 rounded-xl border bg-card/60 p-3"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                            rankClassNames[index] ?? 'bg-muted text-muted-foreground'
                                        )}
                                    >
                                        {index < 3 ? <Trophy className="size-4" /> : index + 1}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">{row.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {type === 'sales'
                                                ? `${formatNumber(row.orders)} đơn · ${formatPercent(row.conversion_rate)} chốt`
                                                : `${formatNumber(row.leads)} lead · ${formatNumber(row.orders)} đơn`}
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-semibold tabular-nums">
                                        {formatCurrency(row.revenue)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">doanh thu</p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
