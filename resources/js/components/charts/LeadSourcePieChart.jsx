import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--muted-foreground)'];

export function LeadSourcePieChart({ data, title = 'Nguồn lead hôm nay' }) {
    const chartData = data ?? [];

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>Phân bổ theo kênh ads</CardDescription>
            </CardHeader>
            <CardContent className="h-[280px]">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={chartData}
                            dataKey="value"
                            nameKey="name"
                            cx="50%"
                            cy="50%"
                            innerRadius={58}
                            outerRadius={88}
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
                <div className="mt-2 flex flex-wrap justify-center gap-3 text-xs text-muted-foreground">
                    {chartData.map((item, i) => (
                        <span key={item.name} className="flex items-center gap-1.5">
                            <span
                                className="size-2 rounded-full"
                                style={{ background: COLORS[i % COLORS.length] }}
                            />
                            {item.name} ({item.value})
                        </span>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
