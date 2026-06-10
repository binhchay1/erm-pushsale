import { AlertTriangle, CopyCheck, GitBranch, Inbox, UsersRound } from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

function AllocatorDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('allocator', initialStats);

    const kpis = [
        {
            title: 'Lead hôm nay',
            value: formatNumber(stats.leads_today),
            hint: 'Lead đổ về từ các kênh quảng cáo',
            icon: Inbox,
        },
        {
            title: 'Chờ phân số',
            value: formatNumber(stats.pending_routing),
            hint: 'Lead cần phân số cho sale',
            icon: GitBranch,
        },
        {
            title: 'Đã xử lý',
            value: formatNumber(stats.processed_today),
            hint: 'Lead đã xử lý trong kỳ',
            icon: CopyCheck,
            accent: true,
        },
        {
            title: 'Lead lỗi',
            value: formatNumber(stats.failed_leads),
            hint: 'Lead nhận về thất bại — cần thử lại',
            icon: AlertTriangle,
        },
        {
            title: 'Trùng số',
            value: formatNumber(stats.duplicate_leads),
            hint: 'Lead trùng số điện thoại hoặc mã ngoài',
            icon: UsersRound,
        },
    ];

    const alerts = [
        Number(stats.pending_routing ?? 0) > 0 && {
            type: 'info',
            title: 'Chờ phân số',
            value: stats.pending_routing,
            description: 'Lead mới cần được phân số cho sale phù hợp.',
        },
        Number(stats.failed_leads ?? 0) > 0 && {
            type: 'danger',
            title: 'Lead lỗi',
            value: stats.failed_leads,
            description: 'Lead nhận về lỗi — kiểm tra dữ liệu gửi lên hoặc thử lại.',
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
            <PageHeader
                title="Dashboard Chia số"
                description="Theo dõi lead đổ về, phân số, lỗi dữ liệu và nguồn lead theo thời gian gần thực."
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.lead_series}
                    title="Lead đổ về 7 ngày"
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
                    description="Số lead đã xử lý theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.routing_status_breakdown}
                    title="Trạng thái phân số"
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
        <RoleDashboardShell role="allocator" title="Dashboard Chia số">
            <AllocatorDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
