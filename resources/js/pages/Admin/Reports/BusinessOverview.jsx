import { Head } from '@inertiajs/react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';

function Stat({ label, value, hint }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{new Intl.NumberFormat('vi-VN').format(value)}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <p className="text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

export default function BusinessOverview({ summary, charts }) {
    return (
        <AppLayout>
            <Head title="Tổng hợp vận hành" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Thống kê tổng hợp theo business</h1>
                    <p className="text-sm text-muted-foreground">
                        Gom KPI marketing, telesale, kho và đối soát vận chuyển về một màn.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Tổng đơn" value={summary.orders_total} hint="Toàn bộ vòng đời đơn hàng" />
                    <Stat label="Đơn giao thành công" value={summary.orders_delivered} hint="Đã delivered/paid" />
                    <Stat label="Lead hôm nay" value={summary.leads_today} hint="Từ landing + webhook nền tảng" />
                    <Stat label="Lệch vận chuyển" value={summary.shipping_mismatch} hint="Số callback lệch COD" />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={charts.revenue_by_day}
                        title="Doanh thu 7 ngày"
                        description="Doanh thu từ đơn giao thành công"
                    />
                    <OrdersBarChart
                        data={charts.orders_by_day}
                        title="Đơn phát sinh 7 ngày"
                        description="Số lượng đơn theo ngày"
                    />
                    <LeadSourcePieChart
                        data={charts.lead_sources}
                        title="Nguồn lead hôm nay"
                    />
                </div>
            </div>
        </AppLayout>
    );
}
