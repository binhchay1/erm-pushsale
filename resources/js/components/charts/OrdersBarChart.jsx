import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function OrdersBarChart({ data, title, description }) {
    const t = useT();
    const chartData = data ?? [];
    const displayTitle = title ?? t('reports.orders_chart_default');

    return (
        <Card>
            <CardHeader>
                <CardTitle>{displayTitle}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="h-[280px] pl-0">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={chartData} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
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
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            width={32}
                        />
                        <Tooltip
                            content={<ChartTooltip formatter={(v) => formatNumber(v)} />}
                            cursor={{ fill: 'var(--muted)', opacity: 0.15 }}
                        />
                        <Bar
                            dataKey="value"
                            fill="var(--chart-2)"
                            radius={[6, 6, 0, 0]}
                            maxBarSize={40}
                            animationDuration={550}
                            animationEasing="ease-out"
                        />
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
