import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

export default function Dashboard({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);

    return (
        <AppLayout>
            <Head title="Tổng quan CEO" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Tổng quan CEO</h1>
                        <p className="text-sm text-muted-foreground">
                            Số liệu cập nhật real-time qua WebSocket (Reverb)
                        </p>
                    </div>
                    <RealtimeBadge connected={connected} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Doanh thu tạm tính (ngày)"
                        value={formatCurrency(stats.revenue_today)}
                    />
                    <StatCard
                        title="Đơn đã chốt"
                        value={formatNumber(stats.orders_closed)}
                        accent
                    />
                    <StatCard
                        title="Lead mới (ngày)"
                        value={formatNumber(stats.leads_today)}
                    />
                    <StatCard
                        title="Tỷ lệ giao thành công"
                        value={formatPercent(stats.delivery_rate)}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={stats.revenue_series}
                        description="Area chart — animation mượt (Recharts)"
                    />
                    <OrdersBarChart data={stats.orders_series} />
                    <LeadSourcePieChart data={stats.lead_sources} />
                </div>
            </div>
        </AppLayout>
    );
}
