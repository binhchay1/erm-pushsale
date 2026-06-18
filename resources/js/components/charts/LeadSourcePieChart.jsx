import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

const COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--muted-foreground)'];

export function LeadSourcePieChart({
    className,
    compact = false,
    fillHeight = false,
    data,
    title,
}) {
    const t = useT();
    const chartData = data ?? [];
    const total = chartData.reduce((sum, item) => sum + Number(item.value ?? 0), 0);
    const topSource = chartData[0];

    return (
        <Card
            className={cn(
                fillHeight && 'flex h-full flex-col',
                !compact && !fillHeight && 'h-full',
                className,
            )}
        >
            <CardHeader className={compact || fillHeight ? 'pb-2' : undefined}>
                <CardTitle className={compact || fillHeight ? 'text-base' : undefined}>
                    {title ?? t('charts.lead_sources_default')}
                </CardTitle>
                <CardDescription className={compact || fillHeight ? 'text-xs' : undefined}>
                    {t('charts.lead_distribution')}
                </CardDescription>
            </CardHeader>
            <CardContent className={cn(fillHeight && 'h-[280px] pl-0')}>
                {chartData.length === 0 ? (
                    <div
                        className={cn(
                            'flex h-full items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground',
                            !fillHeight && (compact ? 'min-h-[160px]' : 'min-h-[220px]'),
                        )}
                    >
                        {t('charts.no_leads_today')}
                    </div>
                ) : (
                    <div
                        className={cn(
                            'grid h-full gap-3',
                            compact || fillHeight
                                ? 'grid-cols-[120px_1fr]'
                                : 'grid-cols-1 sm:grid-cols-[160px_1fr]',
                        )}
                    >
                        <div
                            className={cn(
                                'relative min-h-0',
                                fillHeight ? 'h-full' : compact ? 'h-[120px]' : 'h-[180px]',
                            )}
                        >
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={chartData}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={compact || fillHeight ? 38 : 54}
                                        outerRadius={compact || fillHeight ? 54 : 78}
                                        paddingAngle={3}
                                        animationDuration={650}
                                        animationEasing="ease-out"
                                    >
                                        {chartData.map((_, index) => (
                                            <Cell key={index} fill={COLORS[index % COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip content={<ChartTooltip />} />
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span className={cn('font-bold tabular-nums', compact || fillHeight ? 'text-xl' : 'text-2xl')}>
                                    {formatNumber(total)}
                                </span>
                                <span className="text-[11px] text-muted-foreground">{t('charts.lead_unit')}</span>
                            </div>
                        </div>

                        <div
                            className={cn(
                                'flex min-h-0 min-w-0 flex-col justify-center overflow-y-auto',
                                compact || fillHeight ? 'gap-1.5' : 'gap-3',
                            )}
                        >
                            {!compact && !fillHeight && topSource && (
                                <div className="rounded-xl border bg-muted/30 p-3">
                                    <p className="text-xs text-muted-foreground">{t('charts.top_source')}</p>
                                    <div className="mt-1 flex items-end justify-between gap-3">
                                        <p className="truncate text-sm font-semibold">{topSource.name}</p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatPercent(Math.round((Number(topSource.value) / Math.max(total, 1)) * 1000) / 10)}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className={cn('flex flex-col', compact ? 'gap-1' : 'gap-2')}>
                                {chartData.map((item, index) => {
                                    const value = Number(item.value ?? 0);
                                    const percent = Math.round((value / Math.max(total, 1)) * 1000) / 10;

                                    return (
                                        <div key={item.name} className={cn('flex items-center gap-2', compact ? 'text-xs' : 'gap-3 text-sm')}>
                                            <span
                                                className="size-2.5 rounded-full"
                                                style={{ background: COLORS[index % COLORS.length] }}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate font-medium">{item.name}</span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {formatNumber(value)} · {formatPercent(percent)}
                                                    </span>
                                                </div>
                                                <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-primary"
                                                        style={{
                                                            width: `${Math.max(percent, 3)}%`,
                                                            background: COLORS[index % COLORS.length],
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
