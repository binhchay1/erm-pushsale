import { AlertTriangle, CopyCheck, GitBranch, Inbox, UsersRound } from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RoleDashboardFrame, RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function AllocatorDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('allocator', initialStats);

    const kpis = [
        { title: t('dashboard.allocator.leads_today'), value: formatNumber(stats.leads_today), hint: t('dashboard.allocator.leads_today_hint'), icon: Inbox },
        { title: t('dashboard.allocator.pending_routing'), value: formatNumber(stats.pending_routing), hint: t('dashboard.allocator.pending_routing_hint'), icon: GitBranch },
        { title: t('dashboard.allocator.processed'), value: formatNumber(stats.processed_today), hint: t('dashboard.allocator.processed_hint'), icon: CopyCheck, accent: true },
        { title: t('dashboard.allocator.failed'), value: formatNumber(stats.failed_leads), hint: t('dashboard.allocator.failed_hint'), icon: AlertTriangle },
        { title: t('dashboard.allocator.duplicate'), value: formatNumber(stats.duplicate_leads), hint: t('dashboard.allocator.duplicate_hint'), icon: UsersRound },
    ];

    const alerts = [
        Number(stats.pending_routing ?? 0) > 0 && { type: 'info', title: t('dashboard.allocator.pending_routing'), value: stats.pending_routing, description: t('dashboard.allocator.pending_alert') },
        Number(stats.failed_leads ?? 0) > 0 && { type: 'danger', title: t('dashboard.allocator.failed'), value: stats.failed_leads, description: t('dashboard.allocator.failed_alert') },
        Number(stats.duplicate_leads ?? 0) > 0 && { type: 'warning', title: t('dashboard.allocator.duplicate'), value: stats.duplicate_leads, description: t('dashboard.allocator.duplicate_alert') },
    ].filter(Boolean);

    return (
        <RoleDashboardFrame
            role="allocator"
            title={t('dashboard.allocator.title')}
            subtitle={t('dashboard.allocator.desc')}
            actions={<RealtimeBadge connected={connected} />}
        >
            <div className="ps-role-kpi-grid is-5">
                {kpis.map((card) => <StatCard key={card.title} {...card} className="ps-role-kpi-card" />)}
            </div>

            <div className="ps-role-chart-grid">
                <RevenueAreaChart
                    data={stats.lead_series}
                    title={t('dashboard.allocator.leads_7d')}
                    description={t('dashboard.allocator.leads_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <LeadSourcePieChart data={stats.platform_breakdown} title={t('dashboard.allocator.platform')} />
            </div>

            <div className="ps-role-chart-grid">
                <RevenueAreaChart
                    data={stats.processed_series}
                    title={t('dashboard.allocator.processed_7d')}
                    description={t('dashboard.allocator.processed_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.routing_status_breakdown}
                    title={t('dashboard.allocator.routing_status')}
                    description={t('dashboard.allocator.routing_status_desc')}
                />
            </div>

            {stats.funnel?.length > 0 ? <ConversionFunnel data={stats.funnel} /> : null}
            <OpsAlerts alerts={alerts} />
        </RoleDashboardFrame>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="allocator" title={t('dashboard.allocator.title')}>
            <AllocatorDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
