import { Head } from '@inertiajs/react';
import { Clock, PhoneCall, ShoppingCart, Wallet } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function HourlyStats({
    rows = [],
    totals = {},
    peak = {},
    filters,
    filterOptions,
    filterFields = [],
    routeUrl,
}) {
    const t = useT();

    const title = t('reports.hourly.title');
    const contactSeries = rows.map((r) => ({ label: r.label, value: r.contacts }));
    const closedSeries = rows.map((r) => ({ label: r.label, value: r.closed }));

    const peakContact = peak.contact_hour === null || peak.contact_hour === undefined ? '—' : `${String(peak.contact_hour).padStart(2, '0')}h`;
    const peakClosed = peak.closed_hour === null || peak.closed_hour === undefined ? '—' : `${String(peak.closed_hour).padStart(2, '0')}h`;

    return (
        <AppLayout>
            <Head title={title} />

            <div className="space-y-6">
                <PageHeader
                    icon={Clock}
                    title={title}
                    description={t('reports.hourly.description')}
                    actions={<ReportExportButton routeUrl={routeUrl} filters={filters} />}
                />

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title={t('reports.hourly.total_contacts')} value={formatNumber(totals.contacts ?? 0)} icon={PhoneCall} />
                    <StatCard title={t('reports.hourly.total_closed')} value={formatNumber(totals.closed ?? 0)} icon={ShoppingCart} />
                    <StatCard title={t('reports.hourly.total_revenue')} value={formatCurrency(totals.revenue ?? 0)} accent icon={Wallet} />
                    <StatCard
                        title={t('reports.hourly.peak')}
                        value={peakClosed}
                        hint={t('reports.hourly.peak_hint', { contact: peakContact })}
                        icon={Clock}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <OrdersBarChart data={contactSeries} title={t('reports.hourly.contacts_chart')} />
                    <OrdersBarChart data={closedSeries} title={t('reports.hourly.closed_chart')} />
                </div>

                <ScrollDataTable>
                    <table className="w-full min-w-max text-sm">
                        <thead>
                            <tr>
                                <Th>{t('reports.hourly.col_hour')}</Th>
                                <Th className="text-right">{t('reports.columns.contacts')}</Th>
                                <Th className="text-right">{t('reports.columns.closed')}</Th>
                                <Th className="text-right">{t('reports.columns.rate')}</Th>
                                <Th className="text-right">{t('reports.columns.revenue')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.hour}>
                                    <Td className="font-medium">{row.label}</Td>
                                    <Td className="text-right tabular-nums">{formatNumber(row.contacts)}</Td>
                                    <Td className="text-right tabular-nums">{formatNumber(row.closed)}</Td>
                                    <Td className="text-right tabular-nums">{row.rate === null ? '—' : formatPercent(row.rate)}</Td>
                                    <Td className="text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                                        {formatCurrency(row.revenue)}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t-2 border-border bg-muted/60 font-semibold">
                                <Td>{t('common.grand_total')}</Td>
                                <Td className="text-right tabular-nums">{formatNumber(totals.contacts ?? 0)}</Td>
                                <Td className="text-right tabular-nums">{formatNumber(totals.closed ?? 0)}</Td>
                                <Td className="text-right tabular-nums">—</Td>
                                <Td className="text-right tabular-nums">{formatCurrency(totals.revenue ?? 0)}</Td>
                            </tr>
                        </tfoot>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
