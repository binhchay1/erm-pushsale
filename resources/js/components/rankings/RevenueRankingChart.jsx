import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { barFill, RankingChartTooltip } from '@/components/rankings/RankingChartTooltip';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Sắp tăng dần doanh số: hạng thấp bên trái → #1 bên phải (cột cao dần). */
function toAscendingChartData(chartItems) {
    return [...(chartItems ?? [])].sort((a, b) => a.revenue - b.revenue);
}

function ChartXTick({ x, y, payload, data }) {
    const row = data.find((d) => d.rank === payload.value);
    if (!row) return null;

    const lastName = row.name?.trim().split(/\s+/).pop() ?? row.name;

    return (
        <g transform={`translate(${x},${y + 8})`}>
            <text textAnchor="middle" fontSize={11} fontWeight={600} fill="var(--foreground)">
                #{row.rank}
            </text>
            <text textAnchor="middle" y={14} fontSize={10} fill="var(--muted-foreground)">
                {row.initials}
            </text>
            <text textAnchor="middle" y={26} fontSize={9} fill="var(--muted-foreground)">
                {lastName && lastName.length > 10 ? `${lastName.slice(0, 9)}…` : lastName}
            </text>
        </g>
    );
}

function formatRevenueCompact(value) {
    if (value == null) return '—';
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `${Math.round(value / 1_000)}K`;
    return String(value);
}

function RevenueLabel({ x, y, width, value }) {
    if (value == null || x == null || y == null) return null;
    return (
        <text
            x={x + width / 2}
            y={y - 6}
            textAnchor="middle"
            fontSize={10}
            fontWeight={600}
            fill="var(--muted-foreground)"
        >
            {formatRevenueCompact(value)}
        </text>
    );
}

export function RevenueRankingChart({
    chartItems,
    tableItems,
    highlightUserId,
    departmentLabel,
    compactTable = false,
}) {
    const ascending = toAscendingChartData(chartItems);
    const rest = (tableItems ?? []).slice(3, 50);
    const barCount = ascending.length;
    const chartMinWidth = Math.max(520, barCount * 52);

    if (!barCount) {
        return (
            <p className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                Chưa có doanh số chốt trong kỳ / bộ lọc hiện tại.
            </p>
        );
    }

    return (
        <div className="space-y-5">
            <div>
                <p className="text-sm font-semibold text-foreground">
                    {departmentLabel ? `Doanh số — ${departmentLabel}` : 'Doanh số theo hạng'}
                </p>
                <p className="text-xs text-muted-foreground">
                    Cột tăng dần từ trái sang phải · Di chuột để xem chi tiết
                </p>
            </div>

            <div className="-mx-1 overflow-x-auto pb-1">
                <div className="h-[340px]" style={{ minWidth: chartMinWidth }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={ascending}
                            margin={{ top: 28, right: 12, left: 4, bottom: 52 }}
                            barCategoryGap="18%"
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                className="stroke-border/50"
                                vertical={false}
                            />
                            <XAxis
                                dataKey="rank"
                                tickLine={false}
                                axisLine={false}
                                interval={0}
                                height={56}
                                tick={(props) => (
                                    <ChartXTick {...props} data={ascending} />
                                )}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                width={48}
                                tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                                tickFormatter={(v) =>
                                    v >= 1_000_000
                                        ? `${(v / 1_000_000).toFixed(1)}M`
                                        : v >= 1_000
                                          ? `${Math.round(v / 1_000)}K`
                                          : v
                                }
                            />
                            <Tooltip
                                content={<RankingChartTooltip />}
                                cursor={{ fill: 'var(--muted)', opacity: 0.1 }}
                            />
                            <Bar
                                dataKey="revenue"
                                radius={[8, 8, 0, 0]}
                                maxBarSize={44}
                                animationDuration={700}
                                animationEasing="ease-out"
                            >
                                {ascending.map((row) => (
                                    <Cell
                                        key={row.id}
                                        fill={barFill(row.rank, highlightUserId === row.id)}
                                        stroke={
                                            highlightUserId === row.id ? 'var(--primary)' : 'transparent'
                                        }
                                        strokeWidth={highlightUserId === row.id ? 2 : 0}
                                    />
                                ))}
                                <LabelList dataKey="revenue" content={<RevenueLabel />} />
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {!compactTable && rest.length > 0 && (
                <div className="border-t border-border/60 pt-4">
                    <p className="mb-3 text-sm font-medium text-muted-foreground">
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
                                        <Td className="tabular-nums">
                                            {formatCurrency(row.avgOrderValue)}
                                        </Td>
                                        <Td className="tabular-nums font-semibold">
                                            {formatCurrency(row.revenue)}
                                        </Td>
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
