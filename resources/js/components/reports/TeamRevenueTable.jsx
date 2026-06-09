import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Bảng doanh số theo team — thay cho dạng sơ đồ thẻ trải ngang.
 * Nhận dữ liệu cây (trưởng bộ phận → team → nhân viên) và hiển thị mỗi team 1 dòng.
 */
export function TeamRevenueTable({ roots, emptyText = 'Chưa có dữ liệu team trong kỳ đã chọn.' }) {
    const summary = roots?.[0];
    const teams = summary?.children ?? [];

    if (!teams.length) {
        return (
            <p className="rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                {emptyText}
            </p>
        );
    }

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr>
                        <Th>Team</Th>
                        <Th>Trưởng nhóm</Th>
                        <Th className="text-right">Nhân viên</Th>
                        <Th className="text-right">Đơn chốt</Th>
                        <Th className="text-right">Tỷ lệ chốt</Th>
                        <Th className="text-right">Doanh thu</Th>
                    </tr>
                </thead>
                <tbody>
                    {teams.map((team) => (
                        <tr key={team.id}>
                            <Td className="font-medium">{team.name}</Td>
                            <Td>{team.leaderName ?? '—'}</Td>
                            <Td className="text-right tabular-nums">
                                {formatNumber(team.memberCount ?? team.children?.length ?? 0)}
                            </Td>
                            <Td className="text-right tabular-nums">{formatNumber(team.closedOrders)}</Td>
                            <Td
                                className={cn(
                                    'text-right font-semibold tabular-nums',
                                    team.isHighPerformer && 'text-emerald-600 dark:text-emerald-400'
                                )}
                            >
                                {formatPercent(team.conversionRate)}
                            </Td>
                            <Td
                                className={cn(
                                    'text-right font-semibold tabular-nums',
                                    team.isHighPerformer && 'text-emerald-600 dark:text-emerald-400'
                                )}
                            >
                                {formatCurrency(team.revenue)}
                            </Td>
                        </tr>
                    ))}
                    <tr className="bg-muted/40">
                        <Td className="font-semibold">Tổng cộng</Td>
                        <Td className="text-muted-foreground">{summary.name}</Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatNumber(
                                teams.reduce(
                                    (sum, t) => sum + (t.memberCount ?? t.children?.length ?? 0),
                                    0
                                )
                            )}
                        </Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatNumber(summary.closedOrders)}
                        </Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatPercent(summary.conversionRate)}
                        </Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatCurrency(summary.revenue)}
                        </Td>
                    </tr>
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
