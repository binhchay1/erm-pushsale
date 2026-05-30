import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

export default function Dashboard({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('sales', initialStats);

    return (
        <AppLayout>
            <Head title="Dashboard Telesale" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Dashboard Telesale</h1>
                        <p className="text-sm text-muted-foreground">
                            Tổng quan lead, cuộc gọi và tỷ lệ chốt của bạn
                        </p>
                    </div>
                    <RealtimeBadge connected={connected} />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard title="Lead chờ gọi" value={formatNumber(stats.leads_pending)} />
                    <StatCard title="Đơn chốt hôm nay" value={formatNumber(stats.orders_today)} accent />
                    <StatCard title="Nhắc việc" value={formatNumber(stats.reminders)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RevenueAreaChart
                        data={stats.calls_series}
                        title="Cuộc gọi 7 ngày"
                        valueFormatter={(v) => formatNumber(v)}
                        yTickFormatter={(v) => String(v)}
                    />
                    <OrdersBarChart data={stats.conversion_series} title="Tỷ lệ chốt (%)" />
                </div>

                {stats.pipeline?.length > 0 && (
                    <OrdersBarChart
                        data={stats.pipeline}
                        title="Pipeline tác nghiệp"
                        description="Số lead đang xử lý theo giai đoạn"
                    />
                )}
            </div>
        </AppLayout>
    );
}
