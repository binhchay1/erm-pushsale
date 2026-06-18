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
import { useT } from '@/providers/I18nProvider';

function AdminDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('admin', initialStats);

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('dashboard.admin_title')}
                description={t('dashboard.admin_desc')}
                actions={<RealtimeBadge connected={connected} />}
            />

            <DashboardKpiGrid stats={stats} />

            <div className="space-y-4">
                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={stats.revenue_series}
                        title={t('dashboard.revenue_7d')}
                        description={t('dashboard.revenue_7d_desc')}
                    />
                    <OrdersBarChart
                        data={stats.orders_series}
                        title={t('dashboard.orders_7d')}
                        description={t('dashboard.orders_7d_desc')}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3 lg:items-stretch">
                    <RevenueAreaChart
                        data={stats.lead_series}
                        title={t('dashboard.leads_7d')}
                        description={t('dashboard.leads_7d_desc')}
                        valueFormatter={(v) => formatNumber(v)}
                        yTickFormatter={(v) => String(v)}
                    />
                    <LeadSourcePieChart
                        compact
                        fillHeight
                        data={stats.lead_sources}
                        title={t('dashboard.lead_sources_today')}
                    />
                </div>
            </div>

            <ConversionFunnel data={stats.funnel} />

            <div className="grid gap-4 xl:grid-cols-2">
                <RankingList
                    title={t('dashboard.top_sales')}
                    description={t('dashboard.top_sales_desc')}
                    rows={stats.top_sales}
                    type="sales"
                />
                <RankingList
                    title={t('dashboard.top_sources')}
                    description={t('dashboard.top_sources_desc')}
                    rows={stats.top_sources}
                    type="sources"
                />
            </div>

            <OpsAlerts alerts={stats.alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="admin" title={t('dashboard.admin_title')}>
            <AdminDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
