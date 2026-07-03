import { Head } from '@inertiajs/react';
import { Network } from 'lucide-react';
import { Fragment } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ReportRefreshButton } from '@/components/reports/ReportRefreshButton';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function Row({ row, isTeam }) {
    const t = useT();

    return (
        <tr className={cn(isTeam && 'bg-muted/50 font-semibold')}>
            <Td className={cn(!isTeam && 'pl-8 text-muted-foreground')}>
                {isTeam ? (
                    <span>
                        {row.name}
                        {row.leaderName ? (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                {t('reports.tree.leader_prefix', { name: row.leaderName })}
                            </span>
                        ) : null}
                    </span>
                ) : (
                    row.name
                )}
            </Td>
            <Td className="text-right tabular-nums">{formatCurrency(row.budget)}</Td>
            <Td className="text-right tabular-nums">{formatNumber(row.contacts)}</Td>
            <Td className="text-right tabular-nums">{formatCurrency(row.costPerContact)}</Td>
            <Td className="text-right tabular-nums">{formatNumber(row.closed)}</Td>
            <Td className="text-right tabular-nums">{formatPercent(row.closeRate)}</Td>
            <Td className="text-right tabular-nums">{formatCurrency(row.revenueNew)}</Td>
            <Td className="text-right tabular-nums">{formatCurrency(row.revenueOld)}</Td>
            <Td className="text-right tabular-nums text-emerald-700 dark:text-emerald-400">{formatCurrency(row.revenueTotal)}</Td>
            <Td className="text-right tabular-nums">{formatPercent(row.budgetRevenueRatio)}</Td>
            <Td className="text-right tabular-nums">{formatCurrency(row.kpiRevenue)}</Td>
            <Td className="text-right tabular-nums">{formatPercent(row.kpiRate)}</Td>
        </tr>
    );
}

export default function TeamLeaderStats({
    rows = [],
    totals = {},
    filters,
    filterOptions,
    filterFields = [],
    routeUrl,
    cachedAt,
}) {
    const t = useT();
    const title = t('reports.team_leaders.title');

    return (
        <AppLayout>
            <Head title={title} />

            <div className="space-y-6">
                <PageHeader
                    icon={Network}
                    title={title}
                    description={t('reports.team_leaders.description')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <ReportExportButton routeUrl={routeUrl} filters={filters} />
                            <ReportRefreshButton routeUrl={routeUrl} filters={filters} cachedAt={cachedAt} />
                        </div>
                    }
                />

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <ScrollDataTable>
                    <table className="w-full min-w-max text-sm">
                        <thead>
                            <tr>
                                <Th>{t('reports.team_leaders.col_team')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_budget')}</Th>
                                <Th className="text-right">{t('reports.columns.contacts')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_cost_contact')}</Th>
                                <Th className="text-right">{t('reports.columns.closed')}</Th>
                                <Th className="text-right">{t('reports.columns.close_rate')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_rev_new')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_rev_old')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_rev_total')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_budget_ratio')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_kpi')}</Th>
                                <Th className="text-right">{t('reports.team_leaders.col_kpi_rate')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <Td colSpan={12} className="py-10 text-center text-muted-foreground">
                                        {t('reports.empty_period')}
                                    </Td>
                                </tr>
                            )}
                            {rows.map((team) => (
                                <Fragment key={team.id}>
                                    <Row row={team} isTeam />
                                    {(team.children ?? []).map((member) => (
                                        <Row key={member.id} row={member} isTeam={false} />
                                    ))}
                                </Fragment>
                            ))}
                        </tbody>
                        {rows.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-border bg-muted/60 font-semibold">
                                    <Td>{t('common.grand_total')}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.budget ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatNumber(totals.contacts ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.costPerContact ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatNumber(totals.closed ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatPercent(totals.closeRate ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.revenueNew ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.revenueOld ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.revenueTotal ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatPercent(totals.budgetRevenueRatio ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatCurrency(totals.kpiRevenue ?? 0)}</Td>
                                    <Td className="text-right tabular-nums">{formatPercent(totals.kpiRate ?? 0)}</Td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
