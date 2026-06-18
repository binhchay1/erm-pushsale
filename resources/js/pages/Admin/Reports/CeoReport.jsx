import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusSummaryBar } from '@/components/reports/StatusSummaryBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export default function CeoReport({ filters, filterOptions, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.ceo_report.title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('reports.ceo_report.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('reports.ceo_report.desc')}</p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/reports/ceo"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <StatusSummaryBar summary={report.statusSummary} />

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t('reports.ceo_report.sale_section')}</h2>
                    <ScrollDataTable>
                        <table className="min-w-[1200px] w-full border-collapse">
                            <thead>
                                <tr>
                                    <Th>{t('reports.ceo_report.stt')}</Th>
                                    <Th>{t('reports.ceo_report.sale')}</Th>
                                    <Th colSpan={5}>{t('reports.ceo_report.new_customers')}</Th>
                                    <Th colSpan={5}>{t('reports.ceo_report.old_customers')}</Th>
                                    <Th>{t('reports.ceo_report.total_revenue')}</Th>
                                    <Th>{t('reports.ceo_report.kpi')}</Th>
                                    <Th>{t('reports.ceo_report.kpi_pct')}</Th>
                                </tr>
                                <tr className="bg-primary/90 text-primary-foreground text-xs">
                                    <Th />
                                    <Th />
                                    <Th>{t('reports.ceo_report.contact')}</Th>
                                    <Th>{t('reports.ceo_report.closed')}</Th>
                                    <Th>{t('reports.ceo_report.pct')}</Th>
                                    <Th>{t('reports.ceo_report.products')}</Th>
                                    <Th>{t('reports.ceo_report.revenue')}</Th>
                                    <Th>{t('reports.ceo_report.contact')}</Th>
                                    <Th>{t('reports.ceo_report.closed')}</Th>
                                    <Th>{t('reports.ceo_report.pct')}</Th>
                                    <Th>{t('reports.ceo_report.products')}</Th>
                                    <Th>{t('reports.ceo_report.revenue')}</Th>
                                    <Th />
                                    <Th />
                                    <Th />
                                </tr>
                            </thead>
                            <tbody>
                                {report.saleRows?.map((r) => (
                                    <tr key={r.saleStaffId} className="hover:bg-muted/30">
                                        <Td>{r.stt}</Td>
                                        <Td>
                                            {r.saleStaffName}
                                            <span className="text-muted-foreground"> ({r.saleUsername})</span>
                                        </Td>
                                        <Td>{formatNumber(r.newContact)}</Td>
                                        <Td>{formatNumber(r.newClosed)}</Td>
                                        <Td>{formatPercent(r.newCloseRate)}</Td>
                                        <Td>{formatNumber(r.newProductQty)}</Td>
                                        <Td>{formatCurrency(r.newEstRevenue)}</Td>
                                        <Td>{formatNumber(r.oldContact)}</Td>
                                        <Td>{formatNumber(r.oldClosed)}</Td>
                                        <Td>{formatPercent(r.oldCloseRate)}</Td>
                                        <Td>{formatNumber(r.oldProductQty)}</Td>
                                        <Td>{formatCurrency(r.oldEstRevenue)}</Td>
                                        <Td className="font-semibold">{formatCurrency(r.totalEstRevenue)}</Td>
                                        <Td>{formatCurrency(r.salesKpi)}</Td>
                                        <Td>{formatPercent(r.achievementRate)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </ScrollDataTable>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t('reports.ceo_report.marketing_section')}</h2>
                    <ScrollDataTable>
                        <table className="w-full min-w-[640px] border-collapse">
                            <thead>
                                <tr>
                                    <Th>{t('reports.ceo_report.stt')}</Th>
                                    <Th>{t('reports.ceo_report.marketing')}</Th>
                                    <Th>{t('reports.ceo_report.budget')}</Th>
                                    <Th>{t('reports.ceo_report.contact_price')}</Th>
                                    <Th>{t('reports.ceo_report.budget_new_pct')}</Th>
                                    <Th>{t('reports.ceo_report.budget_total_pct')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.marketingRows?.map((r) => (
                                    <tr key={r.marketerId} className="hover:bg-muted/30">
                                        <Td>{r.stt}</Td>
                                        <Td>{r.marketerName}</Td>
                                        <Td>{formatCurrency(r.budget)}</Td>
                                        <Td>{formatCurrency(r.contactPrice)}</Td>
                                        <Td>{formatPercent(r.budgetRevenueRatioNew)}</Td>
                                        <Td>{formatPercent(r.budgetRevenueRatioTotal)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </ScrollDataTable>
                </section>
            </div>
        </AppLayout>
    );
}
