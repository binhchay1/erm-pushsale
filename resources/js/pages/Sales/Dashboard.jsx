import { Bell, PhoneCall, Target, TrendingUp } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

function SalesDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('sales', initialStats);

    const kpis = [
        {
            title: 'Lead chờ gọi',
            value: formatNumber(stats.leads_pending),
            hint: 'Lead còn trong pipeline tác nghiệp',
            icon: PhoneCall,
        },
        {
            title: 'Đơn chốt hôm nay',
            value: formatNumber(stats.orders_today),
            hint: 'Đơn đã chốt theo scope của bạn',
            icon: Target,
            accent: true,
        },
        {
            title: 'Nhắc việc',
            value: formatNumber(stats.reminders),
            hint: 'Lead cần follow-up / chăm sóc',
            icon: Bell,
        },
        {
            title: 'Tổng đơn trong kỳ',
            value: formatNumber(stats.summary?.orders ?? stats.orders_today),
            hint: 'Theo bộ lọc dashboard hiện tại',
            icon: TrendingUp,
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Dashboard Telesale"
                description="Theo dõi lead, cuộc gọi, pipeline và tỷ lệ chốt theo thời gian gần thực."
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.calls_series}
                    title="Cuộc gọi 7 ngày"
                    description="Tổng contact count theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.conversion_series}
                    title="Tỷ lệ chốt / đơn theo ngày"
                    description="Xu hướng chuyển đổi trong kỳ gần nhất"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.orders_closed_series}
                    title="Đơn chốt 7 ngày"
                    description="Số đơn chốt theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                {stats.pipeline?.length > 0 && (
                    <OrdersBarChart
                        data={stats.pipeline}
                        title="Pipeline tác nghiệp"
                        description="Lead theo giai đoạn chăm sóc"
                    />
                )}
            </div>

            {stats.funnel?.length > 0 && <ConversionFunnel data={stats.funnel} />}
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <RoleDashboardShell role="sales" title="Dashboard Telesale">
            <SalesDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
