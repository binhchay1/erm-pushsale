import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatNumber, formatPercent } from '@/lib/format';

const COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--muted-foreground)'];

export function LeadSourcePieChart({ data, title = 'Nguồn lead hôm nay' }) {
    const chartData = data ?? [];
    const total = chartData.reduce((sum, item) => sum + Number(item.value ?? 0), 0);
    const topSource = chartData[0];

    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>Phân bổ lead theo nền tảng/kênh ads</CardDescription>
            </CardHeader>
            <CardContent>
                {chartData.length === 0 ? (
                    <div className="flex min-h-[220px] items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                        Chưa có lead hôm nay.
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-[180px_1fr] lg:grid-cols-1 xl:grid-cols-[180px_1fr]">
                        <div className="relative h-[180px]">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={chartData}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={54}
                                        outerRadius={78}
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
                                <span className="text-2xl font-bold tabular-nums">{formatNumber(total)}</span>
                                <span className="text-[11px] text-muted-foreground">lead</span>
                            </div>
                        </div>

                        <div className="flex flex-col justify-center gap-3">
                            {topSource && (
                                <div className="rounded-xl border bg-muted/30 p-3">
                                    <p className="text-xs text-muted-foreground">Nguồn mạnh nhất</p>
                                    <div className="mt-1 flex items-end justify-between gap-3">
                                        <p className="truncate text-sm font-semibold">{topSource.name}</p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatPercent(Math.round((Number(topSource.value) / Math.max(total, 1)) * 1000) / 10)}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-col gap-2">
                                {chartData.map((item, index) => {
                                    const value = Number(item.value ?? 0);
                                    const percent = Math.round((value / Math.max(total, 1)) * 1000) / 10;

                                    return (
                                        <div key={item.name} className="flex items-center gap-3 text-sm">
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
