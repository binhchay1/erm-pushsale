import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

function MetricBar({ value, max, color }) {
    const w = max > 0 ? Math.min(100, (value / max) * 100) : 0;
    return (
        <div className="h-2 w-16 overflow-hidden rounded-full bg-muted">
            <div className={cn('h-full rounded-full', color)} style={{ width: `${w}%` }} />
        </div>
    );
}

export default function Dashboard({ filters, filterOptions, report }) {
    const maxClose = Math.max(...(report.rows?.map((r) => r.closingRate) ?? [1]), 1);

    return (
        <AppLayout>
            <Head title="Dashboard Marketing" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Marketing</h1>
                    <p className="text-sm text-muted-foreground">Hiệu quả nguồn dữ liệu & chiến dịch</p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/marketing/dashboard"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <ScrollDataTable>
                    <table className="min-w-[1400px] w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <Th>STT</Th>
                                <Th>Nguồn</Th>
                                <Th>Sản phẩm</Th>
                                <Th>Kênh</Th>
                                <Th>NS</Th>
                                <Th>Contact</Th>
                                <Th>% Contact</Th>
                                <Th>Giá CP</Th>
                                <Th>Đơn chốt</Th>
                                <Th>% Chốt</Th>
                                <Th>Doanh số</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows?.map((row) => (
                                <tr
                                    key={row.id + (row.parentId ?? '')}
                                    className={cn(
                                        'hover:bg-muted/30',
                                        row.isChild && 'bg-rose-50/50 dark:bg-rose-950/20'
                                    )}
                                >
                                    <Td>{row.isChild ? '' : row.stt}</Td>
                                    <Td className={row.isChild ? 'pl-6' : ''}>{row.sourceName}</Td>
                                    <Td>{row.productName}</Td>
                                    <Td>{row.adChannel}</Td>
                                    <Td>{formatCurrency(row.budget)}</Td>
                                    <Td>{formatNumber(row.contacts)}</Td>
                                    <Td>{formatPercent(row.contactRate)}</Td>
                                    <Td>{formatCurrency(row.costPerContact)}</Td>
                                    <Td>{formatNumber(row.closedOrders)}</Td>
                                    <Td>
                                        <div className="flex items-center gap-2">
                                            <MetricBar value={row.closingRate} max={maxClose} color="bg-red-500" />
                                            {formatPercent(row.closingRate)}
                                        </div>
                                    </Td>
                                    <Td className="font-medium">{formatCurrency(row.totalRevenue)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
