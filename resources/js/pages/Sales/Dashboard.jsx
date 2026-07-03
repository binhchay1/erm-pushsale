import { Bell, PhoneCall, ShoppingBag, Target, TrendingUp } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function SalesDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('sales', initialStats);

    const kpis = [
        {
            title: t('dashboard.sales.leads_pending'),
            value: formatNumber(stats.leads_pending),
            hint: t('dashboard.sales.leads_pending_hint'),
            icon: PhoneCall,
        },
        {
            title: t('dashboard.sales.orders_today'),
            value: formatNumber(stats.orders_today),
            hint: t('dashboard.sales.orders_today_hint'),
            icon: Target,
            accent: true,
        },
        {
            title: t('dashboard.sales.reminders'),
            value: formatNumber(stats.reminders),
            hint: t('dashboard.sales.reminders_hint'),
            icon: Bell,
        },
        {
            title: t('dashboard.sales.aov'),
            value: formatCurrency(stats.aov ?? stats.summary?.aov ?? 0),
            hint: t('dashboard.sales.aov_hint'),
            icon: ShoppingBag,
        },
        {
            title: t('dashboard.sales.orders_period'),
            value: formatNumber(stats.summary?.orders ?? stats.orders_today),
            hint: t('dashboard.sales.orders_period_hint'),
            icon: TrendingUp,
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('dashboard.sales.title')}
                description={t('dashboard.sales.desc')}
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.calls_series}
                    title={t('dashboard.sales.calls_7d')}
                    description={t('dashboard.sales.calls_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.conversion_series}
                    title={t('dashboard.sales.conversion_7d')}
                    description={t('dashboard.sales.conversion_7d_desc')}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.orders_closed_series}
                    title={t('dashboard.sales.closed_7d')}
                    description={t('dashboard.sales.closed_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                {stats.pipeline?.length > 0 && (
                    <OrdersBarChart
                        data={stats.pipeline}
                        title={t('dashboard.sales.pipeline')}
                        description={t('dashboard.sales.pipeline_desc')}
                    />
                )}
            </div>

            {stats.funnel?.length > 0 && <ConversionFunnel data={stats.funnel} />}
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="sales" title={t('dashboard.sales.title')}>
            <SalesDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
