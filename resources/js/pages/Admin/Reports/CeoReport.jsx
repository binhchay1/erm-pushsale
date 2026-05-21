import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusSummaryBar } from '@/components/reports/StatusSummaryBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

export default function CeoReport({ filters, filterOptions, report }) {
    return (
        <AppLayout>
            <Head title="Báo cáo điều hành CEO" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Báo cáo điều hành CEO</h1>
                    <p className="text-sm text-muted-foreground">ERM SaleOps — tổng hợp Sale & Marketing</p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/reports/ceo"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <StatusSummaryBar summary={report.statusSummary} />

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Hiệu suất Sale</h2>
                    <ScrollDataTable>
                        <table className="min-w-[1200px] w-full border-collapse">
                            <thead>
                                <tr>
                                    <Th>STT</Th>
                                    <Th>Sale</Th>
                                    <Th colSpan={5}>Khách mới</Th>
                                    <Th colSpan={5}>Khách cũ</Th>
                                    <Th>Tổng DS</Th>
                                    <Th>KPI</Th>
                                    <Th>% KPI</Th>
                                </tr>
                                <tr className="bg-primary/90 text-primary-foreground text-xs">
                                    <Th />
                                    <Th />
                                    <Th>Tiếp xúc</Th>
                                    <Th>Chốt</Th>
                                    <Th>%</Th>
                                    <Th>SP</Th>
                                    <Th>DS</Th>
                                    <Th>Tiếp xúc</Th>
                                    <Th>Chốt</Th>
                                    <Th>%</Th>
                                    <Th>SP</Th>
                                    <Th>DS</Th>
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
                    <h2 className="text-lg font-semibold">Hiệu suất Marketing</h2>
                    <ScrollDataTable>
                        <table className="w-full min-w-[640px] border-collapse">
                            <thead>
                                <tr>
                                    <Th>STT</Th>
                                    <Th>Marketing</Th>
                                    <Th>Ngân sách</Th>
                                    <Th>Giá contact</Th>
                                    <Th>% NS/DS mới</Th>
                                    <Th>% NS/DS tổng</Th>
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
