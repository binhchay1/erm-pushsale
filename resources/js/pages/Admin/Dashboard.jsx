import { Deferred, Head } from '@inertiajs/react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { DashboardKpiGrid } from '@/components/dashboard/DashboardKpiGrid';
import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RankingList } from '@/components/dashboard/RankingList';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import AppLayout from '@/layouts/AppLayout';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';

function AdminDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-bold tracking-tight">Tổng quan CEO</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Theo dõi doanh thu, lead, đơn hàng và cảnh báo vận hành theo thời gian gần thực.
                    </p>
                </div>
                <RealtimeBadge connected={connected} />
            </div>

            <DashboardKpiGrid stats={stats} />

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.revenue_series}
                    title="Doanh thu 7 ngày"
                    description="Doanh thu từ đơn delivered/paid"
                />
                <OrdersBarChart
                    data={stats.orders_series}
                    title="Đơn phát sinh 7 ngày"
                    description="Số đơn tạo mới theo ngày"
                />
                <LeadSourcePieChart
                    data={stats.lead_sources}
                    title="Nguồn lead hôm nay"
                />
            </div>

            <ConversionFunnel data={stats.funnel} />

            <div className="grid gap-4 xl:grid-cols-2">
                <RankingList
                    title="Top sale"
                    description="Xếp hạng theo doanh thu giao thành công"
                    rows={stats.top_sales}
                    type="sales"
                />
                <RankingList
                    title="Top nguồn lead / campaign"
                    description="Nguồn tạo doanh thu và đơn hàng tốt nhất"
                    rows={stats.top_sources}
                    type="sources"
                />
            </div>

            <OpsAlerts alerts={stats.alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <AppLayout>
            <Head title="Tổng quan CEO" />

            <Deferred data="stats" fallback={<DashboardSkeleton role="admin" />}>
                <AdminDashboardContent stats={initialStats} />
            </Deferred>
        </AppLayout>
    );
}
