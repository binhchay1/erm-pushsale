import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

export function TeamRevenueTable({ roots, emptyText }) {
    const t = useT();
    const summary = roots?.[0];
    const teams = summary?.children ?? [];
    const displayEmpty = emptyText ?? t('reports.team_table.empty');

    if (!teams.length) {
        return (
            <p className="rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                {displayEmpty}
            </p>
        );
    }

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr>
                        <Th>Team</Th>
                        <Th>{t('reports.team_table.team_lead')}</Th>
                        <Th className="text-right">{t('reports.team_table.members')}</Th>
                        <Th className="text-right">{t('reports.team_table.closed_orders')}</Th>
                        <Th className="text-right">{t('reports.team_table.closing_rate')}</Th>
                        <Th className="text-right">{t('reports.team_table.revenue')}</Th>
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
                        <Td className="font-semibold">{t('reports.team_table.grand_total')}</Td>
                        <Td className="text-muted-foreground">{summary.name}</Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatNumber(
                                teams.reduce(
                                    (sum, teamRow) => sum + (teamRow.memberCount ?? teamRow.children?.length ?? 0),
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
