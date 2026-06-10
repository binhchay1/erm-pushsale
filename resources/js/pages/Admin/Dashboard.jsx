import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { DashboardKpiGrid } from '@/components/dashboard/DashboardKpiGrid';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RankingList } from '@/components/dashboard/RankingList';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

function AdminDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);

    return (
        <div className="space-y-6">
            <PageHeader
                title="Tổng quan CEO"
                description="Theo dõi doanh thu, lead, đơn hàng và cảnh báo vận hành theo thời gian gần thực."
                actions={<RealtimeBadge connected={connected} />}
            />

            <DashboardKpiGrid stats={stats} />

            <div className="space-y-4">
                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={stats.revenue_series}
                        title="Doanh thu 7 ngày"
                        description="Doanh thu từ đơn đã giao / đã thanh toán"
                    />
                    <OrdersBarChart
                        data={stats.orders_series}
                        title="Đơn phát sinh 7 ngày"
                        description="Số đơn tạo mới theo ngày"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3 lg:items-stretch">
                    <RevenueAreaChart
                        data={stats.lead_series}
                        title="Lead 7 ngày"
                        description="Số lead đổ về theo ngày"
                        valueFormatter={(v) => formatNumber(v)}
                        yTickFormatter={(v) => String(v)}
                    />
                    <LeadSourcePieChart
                        compact
                        fillHeight
                        data={stats.lead_sources}
                        title="Nguồn lead hôm nay"
                    />
                </div>
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
        <RoleDashboardShell role="admin" title="Tổng quan CEO">
            <AdminDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
