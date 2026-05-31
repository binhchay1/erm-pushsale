import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { barFill, PodiumAvatar, RankingChartTooltip } from '@/components/rankings/RankingChartTooltip';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export function RevenueRankingChart({ chartItems, tableItems, highlightUserId, departmentLabel }) {
    const podium = (chartItems ?? []).slice(0, 3);
    const bars = (chartItems ?? []).slice().reverse();
    const rest = (tableItems ?? []).slice(3, 50);

    if (!chartItems?.length) {
        return (
            <p className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                Chưa có doanh số chốt trong kỳ / bộ lọc hiện tại.
            </p>
        );
    }

    return (
        <div className="space-y-6">
            {podium.length > 0 && (
                <div className="grid items-end gap-3 sm:grid-cols-3">
                    {[podium[1], podium[0], podium[2]].filter(Boolean).map((row) => (
                        <div
                            key={row.id}
                            className={cn(row.rank === 1 && 'sm:-mt-4 sm:scale-105')}
                        >
                            <PodiumAvatar row={row} highlightUserId={highlightUserId} />
                        </div>
                    ))}
                </div>
            )}

            <Card className="overflow-hidden border-border/80">
                <CardHeader className="pb-2">
                    <CardTitle className="text-base">Biểu đồ doanh số — {departmentLabel}</CardTitle>
                    <CardDescription>Di chuột vào cột để xem chi tiết nhân sự</CardDescription>
                </CardHeader>
                <CardContent className="h-[360px] pl-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={bars}
                            layout="vertical"
                            margin={{ top: 8, right: 24, left: 8, bottom: 8 }}
                        >
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" horizontal={false} />
                            <XAxis
                                type="number"
                                tickLine={false}
                                axisLine={false}
                                tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                                tickFormatter={(v) => `${Math.round(v / 1_000_000)}M`}
                            />
                            <YAxis
                                type="category"
                                dataKey="name"
                                width={120}
                                tickLine={false}
                                axisLine={false}
                                tick={{ fontSize: 11, fill: 'var(--foreground)' }}
                            />
                            <Tooltip
                                content={<RankingChartTooltip />}
                                cursor={{ fill: 'var(--muted)', opacity: 0.12 }}
                            />
                            <Bar dataKey="revenue" radius={[0, 8, 8, 0]} maxBarSize={28} animationDuration={600}>
                                {bars.map((row) => (
                                    <Cell
                                        key={row.id}
                                        fill={barFill(row.rank, highlightUserId === row.id)}
                                        stroke={highlightUserId === row.id ? 'var(--primary)' : 'transparent'}
                                        strokeWidth={highlightUserId === row.id ? 2 : 0}
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>

            {rest.length > 0 && (
                <div>
                    <p className="mb-3 text-sm font-semibold text-muted-foreground">
                        Hạng 4 – {3 + rest.length}
                    </p>
                    <ScrollDataTable>
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <Th>#</Th>
                                    <Th>Nhân viên</Th>
                                    <Th>Nhóm</Th>
                                    <Th>Đơn chốt</Th>
                                    <Th>TB/đơn</Th>
                                    <Th>Doanh số chốt</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rest.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={cn(
                                            'hover:bg-muted/30',
                                            highlightUserId === row.id && 'bg-primary/10'
                                        )}
                                    >
                                        <Td className="font-medium tabular-nums">{row.rank}</Td>
                                        <Td>
                                            <div className="flex items-center gap-2">
                                                <span className="flex size-7 items-center justify-center rounded-full bg-muted text-[10px] font-bold">
                                                    {row.initials}
                                                </span>
                                                <span className="font-medium">{row.name}</span>
                                            </div>
                                        </Td>
                                        <Td>{row.team ?? '—'}</Td>
                                        <Td className="tabular-nums">{formatNumber(row.orders)}</Td>
                                        <Td className="tabular-nums">{formatCurrency(row.avgOrderValue)}</Td>
                                        <Td className="tabular-nums font-semibold">{formatCurrency(row.revenue)}</Td>
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
