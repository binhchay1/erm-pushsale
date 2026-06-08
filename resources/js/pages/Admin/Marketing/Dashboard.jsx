import { Head } from '@inertiajs/react';

import { MarketingKpiHero } from '@/components/marketing/MarketingKpiHero';
import { MarketingTeamTree } from '@/components/marketing/MarketingTeamTree';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import {
    MARKETING_SOURCE_COLUMNS,
    TableColumnToggle,
    useMarketingSourceColumns,
} from '@/components/reports/TableColumnToggle';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
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

export default function Dashboard({ filters, filterOptions, filterFields, report, filterRouteUrl }) {
    const routeUrl = filterRouteUrl ?? '/admin/marketing/dashboard';
    const { visible, isVisible, toggle } = useMarketingSourceColumns();
    const maxClose = Math.max(...(report.rows?.map((r) => r.closingRate) ?? [1]), 1);

    return (
        <AppLayout>
            <Head title="Dashboard Marketing" />

            <div className="space-y-8 pb-8">
                <div className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Dashboard Marketing</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                        Theo dõi hiệu suất team, tỷ lệ chốt và doanh thu theo nguồn chiến dịch
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <MarketingKpiHero kpis={report.kpis} />

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="pb-2">
                        <CardTitle>Sơ đồ doanh thu theo Team</CardTitle>
                        <CardDescription>
                            Giám đốc / Leader → Team → Nhân viên MKT — khối xanh là hiệu suất cao
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-2">
                        <MarketingTeamTree roots={report.teamTree?.roots} />
                    </CardContent>
                </Card>

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4 pb-2">
                        <div className="space-y-1">
                            <CardTitle>Chi tiết nguồn / chiến dịch</CardTitle>
                            <CardDescription>
                                Bảng tóm tắt — dùng nút bên phải để ẩn cột ít dùng
                            </CardDescription>
                        </div>
                        <TableColumnToggle
                            columns={MARKETING_SOURCE_COLUMNS}
                            visible={visible}
                            onToggle={toggle}
                        />
                    </CardHeader>
                    <CardContent className="pt-2">
                        <ScrollDataTable>
                            <table className="min-w-[960px] w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <Th>STT</Th>
                                        <Th>Nguồn</Th>
                                        {isVisible('product') && <Th>Sản phẩm</Th>}
                                        {isVisible('channel') && <Th>Kênh</Th>}
                                        {isVisible('budget') && <Th>NS</Th>}
                                        {isVisible('contacts') && <Th>Contact</Th>}
                                        {isVisible('contactRate') && <Th>% Contact</Th>}
                                        {isVisible('costPerContact') && <Th>Giá CP</Th>}
                                        {isVisible('closedOrders') && <Th>Đơn chốt</Th>}
                                        {isVisible('closingRate') && <Th>% Chốt</Th>}
                                        <Th>Doanh số</Th>
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
                                                Không có dữ liệu trong kỳ đã chọn
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
