import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { SalesPerformanceTable } from '@/components/reports/SalesPerformanceTable';
import { TeamRevenueTable } from '@/components/reports/TeamRevenueTable';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function SalesPerformanceReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/sales/performance',
}) {
    return (
        <AppLayout>
            <Head title="Hiệu suất Telesale" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Báo cáo hiệu suất Telesale</h1>
                        <p className="text-sm text-muted-foreground">
                            Lead nhận · cuộc gọi · tỷ lệ bắt máy · chốt đơn · doanh thu theo từng sale
                        </p>
                    </div>
                    <ReportExportButton routeUrl={routeUrl} filters={filters} />
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                {report?.teamTree && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle>Doanh số theo team</CardTitle>
                            <CardDescription>
                                Mỗi team một dòng: trưởng nhóm, số đơn chốt, tỷ lệ chốt và doanh thu
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-2">
                            <TeamRevenueTable
                                roots={report.teamTree.roots}
                                emptyText="Chưa có team bán hàng nào. Vào mục Nhân viên để xếp nhân viên vào team."
                            />
                        </CardContent>
                    </Card>
                )}

                <SalesPerformanceTable rows={report?.rows ?? []} />
            </div>
        </AppLayout>
    );
}
