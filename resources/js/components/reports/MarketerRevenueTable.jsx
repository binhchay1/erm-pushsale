import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function flattenMarketers(roots) {
    const marketers = [];

    for (const root of roots ?? []) {
        for (const team of root.children ?? []) {
            for (const member of team.children ?? []) {
                if (member.type === 'marketer') {
                    marketers.push({
                        ...member,
                        teamName: team.name,
                    });
                }
            }
        }
    }

    return marketers.sort((a, b) => (b.revenue ?? 0) - (a.revenue ?? 0));
}

export function MarketerRevenueTable({ roots, emptyText }) {
    const t = useT();
    const marketers = flattenMarketers(roots);
    const displayEmpty = emptyText ?? t('reports.marketer_table.empty');

    if (!marketers.length) {
        return (
            <p className="rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                {displayEmpty}
            </p>
        );
    }

    const totalRevenue = marketers.reduce((sum, row) => sum + (row.revenue ?? 0), 0);
    const totalClosed = marketers.reduce((sum, row) => sum + (row.closedOrders ?? 0), 0);
    const totalContacts = marketers.reduce((sum, row) => sum + (row.contacts ?? 0), 0);

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[720px] border-collapse text-sm">
                <thead>
                    <tr>
                        <Th>{t('reports.marketer_table.marketer')}</Th>
                        <Th>{t('reports.team_table.team_lead')}</Th>
                        <Th className="text-right">{t('reports.marketing_dashboard.contacts')}</Th>
                        <Th className="text-right">{t('reports.team_table.closed_orders')}</Th>
                        <Th className="text-right">{t('reports.team_table.closing_rate')}</Th>
                        <Th className="text-right">{t('reports.team_table.revenue')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {marketers.map((row) => (
                        <tr key={row.id}>
                            <Td className="font-medium">{row.name}</Td>
                            <Td className="text-muted-foreground">{row.teamName ?? '—'}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.contacts)}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.closedOrders)}</Td>
                            <Td
                                className={cn(
                                    'text-right font-semibold tabular-nums',
                                    row.isHighPerformer && 'text-emerald-600 dark:text-emerald-400',
                                )}
                            >
                                {formatPercent(row.conversionRate)}
                            </Td>
                            <Td
                                className={cn(
                                    'text-right font-semibold tabular-nums',
                                    row.isHighPerformer && 'text-emerald-600 dark:text-emerald-400',
                                )}
                            >
                                {formatCurrency(row.revenue)}
                            </Td>
                        </tr>
                    ))}
                    <tr className="bg-muted/40">
                        <Td className="font-semibold" colSpan={2}>
                            {t('reports.team_table.grand_total')}
                        </Td>
                        <Td className="text-right font-semibold tabular-nums">{formatNumber(totalContacts)}</Td>
                        <Td className="text-right font-semibold tabular-nums">{formatNumber(totalClosed)}</Td>
                        <Td className="text-right font-semibold tabular-nums">
                            {formatPercent(totalContacts > 0 ? (totalClosed / totalContacts) * 100 : 0)}
                        </Td>
                        <Td className="text-right font-semibold tabular-nums">{formatCurrency(totalRevenue)}</Td>
                    </tr>
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
