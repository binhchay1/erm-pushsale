import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';

export function RevenueAreaChart({
    data,
    title = 'Doanh thu 7 ngày',
    description,
    valueFormatter = formatCurrency,
    yTickFormatter = (v) => `${Math.round(v / 1_000_000)}tr`,
}) {
    const chartData = data ?? [];

    return (
        <Card className="col-span-full lg:col-span-2">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="h-[280px] pl-0">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={chartData} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
                        <defs>
                            <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.35} />
                                <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={yTickFormatter}
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            width={42}
                        />
                        <Tooltip
                            content={
                                <ChartTooltip formatter={(v) => valueFormatter(v)} />
                            }
                            cursor={{ stroke: 'var(--chart-1)', strokeWidth: 1, strokeDasharray: '4 4' }}
                        />
                        <Area
                            type="monotone"
                            dataKey="value"
                            stroke="var(--chart-1)"
                            strokeWidth={2.5}
                            fill="url(#revenueFill)"
                            animationDuration={600}
                            animationEasing="ease-out"
                            dot={false}
                            activeDot={{ r: 5, strokeWidth: 0, fill: 'var(--chart-1)' }}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
