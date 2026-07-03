import { Head } from '@inertiajs/react';

import { MarketingKpiHero } from '@/components/marketing/MarketingKpiHero';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { TeamRevenueTable } from '@/components/reports/TeamRevenueTable';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { TableColumnToggle, useMarketingSourceColumns } from '@/components/reports/TableColumnToggle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function MetricBar({ value, max, color }) {
    const w = max > 0 ? Math.min(100, (value / max) * 100) : 0;
    return (
        <div className="h-2 w-16 overflow-hidden rounded-full bg-muted">
            <div className={cn('h-full rounded-full', color)} style={{ width: `${w}%` }} />
        </div>
    );
}

export default function Dashboard({ filters, filterOptions, filterFields, report, filterRouteUrl }) {
    const t = useT();
    const routeUrl = filterRouteUrl ?? '/admin/marketing/dashboard';
    const { visible, isVisible, toggle, columns } = useMarketingSourceColumns();
    const maxClose = Math.max(...(report.rows?.map((r) => r.closingRate) ?? [1]), 1);

    return (
        <AppLayout>
            <Head title={t('dashboard.marketing.title')} />

            <div className="space-y-8 pb-8">
                <PageHeader
                    title={t('dashboard.marketing.title')}
                    description={t('dashboard.marketing.admin_desc')}
                />

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <MarketingKpiHero kpis={report.kpis} />

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="pb-2">
                        <CardTitle>{t('reports.marketing_dashboard.team_revenue')}</CardTitle>
                        <CardDescription>{t('reports.marketing_dashboard.team_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-2">
                        <TeamRevenueTable
                            roots={report.teamTree?.roots}
                            emptyText={t('reports.marketing_dashboard.team_empty')}
                        />
                    </CardContent>
                </Card>

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4 pb-2">
                        <div className="space-y-1">
                            <CardTitle>{t('reports.marketing_dashboard.source_detail')}</CardTitle>
                            <CardDescription>{t('reports.marketing_dashboard.source_desc')}</CardDescription>
                        </div>
                        <TableColumnToggle columns={columns} visible={visible} onToggle={toggle} />
                    </CardHeader>
                    <CardContent className="pt-2">
                        <ScrollDataTable>
                            <table className="min-w-[960px] w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <Th>{t('reports.marketing_dashboard.stt')}</Th>
                                        <Th>{t('reports.marketing_dashboard.source')}</Th>
                                        {isVisible('product') && <Th>{t('reports.marketing_dashboard.product')}</Th>}
                                        {isVisible('channel') && <Th>{t('reports.marketing_dashboard.channel')}</Th>}
                                        {isVisible('budget') && <Th>{t('reports.marketing_dashboard.budget')}</Th>}
                                        {isVisible('contacts') && <Th>{t('reports.marketing_dashboard.contacts')}</Th>}
                                        {isVisible('contactRate') && (
                                            <Th>{t('reports.marketing_dashboard.contact_rate')}</Th>
                                        )}
                                        {isVisible('costPerContact') && (
                                            <Th>{t('reports.marketing_dashboard.cost_per_contact')}</Th>
                                        )}
                                        {isVisible('closedOrders') && (
                                            <Th>{t('reports.marketing_dashboard.closed_orders')}</Th>
                                        )}
                                        {isVisible('closingRate') && (
                                            <Th>{t('reports.marketing_dashboard.closing_rate')}</Th>
                                        )}
                                        <Th>{t('reports.marketing_dashboard.revenue')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.rows?.length ? (
                                        report.rows.map((row) => (
                                            <tr
                                                key={row.id + (row.parentId ?? '')}
                                                className={cn(
                                                    'hover:bg-muted/30',
                                                    row.isChild && 'bg-muted/20'
                                                )}
                                            >
                                                <Td>{row.isChild ? '' : row.stt}</Td>
                                                <Td className={cn('font-medium', row.isChild && 'pl-6')}>
                                                    {row.sourceName}
                                                </Td>
                                                {isVisible('product') && <Td>{row.productName}</Td>}
                                                {isVisible('channel') && <Td>{row.adChannel}</Td>}
                                                {isVisible('budget') && <Td>{formatCurrency(row.budget)}</Td>}
                                                {isVisible('contacts') && <Td>{formatNumber(row.contacts)}</Td>}
                                                {isVisible('contactRate') && (
                                                    <Td>{formatPercent(row.contactRate)}</Td>
                                                )}
                                                {isVisible('costPerContact') && (
                                                    <Td>{formatCurrency(row.costPerContact)}</Td>
                                                )}
                                                {isVisible('closedOrders') && (
                                                    <Td>{formatNumber(row.closedOrders)}</Td>
                                                )}
                                                {isVisible('closingRate') && (
                                                    <Td>
                                                        <div className="flex items-center gap-2">
                                                            <MetricBar
                                                                value={row.closingRate}
                                                                max={maxClose}
                                                                color="bg-violet-500"
                                                            />
                                                            {formatPercent(row.closingRate)}
                                                        </div>
                                                    </Td>
                                                )}
                                                <Td className="font-semibold tabular-nums">
                                                    {formatCurrency(row.totalRevenue)}
                                                </Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Td colSpan={12} className="py-12 text-center text-muted-foreground">
                                                {t('pages.empty_period')}
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </ScrollDataTable>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
