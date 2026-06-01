import { Deferred, Head } from '@inertiajs/react';
import { AlertTriangle, CopyCheck, GitBranch, Inbox, UsersRound } from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import AppLayout from '@/layouts/AppLayout';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

function AllocatorDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('allocator', initialStats);

    const kpis = [
        {
            title: 'Lead hôm nay',
            value: formatNumber(stats.leads_today),
            hint: 'Lead ingest từ webhook/platform',
            icon: Inbox,
        },
        {
            title: 'Chờ phân số',
            value: formatNumber(stats.pending_routing),
            hint: 'Lead cần routing sang sale',
            icon: GitBranch,
        },
        {
            title: 'Đã xử lý',
            value: formatNumber(stats.processed_today),
            hint: 'Lead processed trong kỳ',
            icon: CopyCheck,
            accent: true,
        },
        {
            title: 'Lead lỗi',
            value: formatNumber(stats.failed_leads),
            hint: 'Lead ingest thất bại cần retry',
            icon: AlertTriangle,
        },
        {
            title: 'Trùng số',
            value: formatNumber(stats.duplicate_leads),
            hint: 'Lead duplicate theo phone/external id',
            icon: UsersRound,
        },
    ];

    const alerts = [
        Number(stats.pending_routing ?? 0) > 0 && {
            type: 'info',
            title: 'Chờ phân số',
            value: stats.pending_routing,
            description: 'Lead mới cần được routing tới sale phù hợp.',
        },
        Number(stats.failed_leads ?? 0) > 0 && {
            type: 'danger',
            title: 'Lead lỗi',
            value: stats.failed_leads,
            description: 'Lead ingest lỗi cần kiểm tra payload hoặc retry.',
        },
        Number(stats.duplicate_leads ?? 0) > 0 && {
            type: 'warning',
            title: 'Trùng số',
            value: stats.duplicate_leads,
            description: 'Lead trùng cần rà soát trước khi chia lại.',
        },
    ].filter(Boolean);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Chia số</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Theo dõi lead ingest, routing, lỗi payload và nguồn lead theo thời gian gần thực.
                    </p>
                </div>
                <RealtimeBadge connected={connected} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.lead_series}
                    title="Lead ingest 7 ngày"
                    description="Số lead đổ về hệ thống theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <LeadSourcePieChart data={stats.platform_breakdown} title="Lead theo nền tảng" />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.processed_series}
                    title="Lead xử lý 7 ngày"
                    description="Lead processed theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.routing_status_breakdown}
                    title="Trạng thái routing"
                    description="Lead chờ phân số, lỗi và trùng"
                />
            </div>

            {stats.funnel?.length > 0 && <ConversionFunnel data={stats.funnel} />}

            <OpsAlerts alerts={alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Chia số" />

            <Deferred data="stats" fallback={<DashboardSkeleton role="allocator" />}>
                <AllocatorDashboardContent stats={initialStats} />
            </Deferred>
        </AppLayout>
    );
}
