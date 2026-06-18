import { Megaphone, MousePointerClick, Target, Wallet } from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { RankingList } from '@/components/dashboard/RankingList';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function MarketingDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('marketing', initialStats);

    const kpis = [
        {
            title: t('dashboard.marketing.active_campaigns'),
            value: formatNumber(stats.active_campaigns),
            hint: t('dashboard.marketing.active_campaigns_hint'),
            icon: Megaphone,
        },
        {
            title: t('dashboard.marketing.leads_today'),
            value: formatNumber(stats.leads_today),
            hint: t('dashboard.marketing.leads_today_hint'),
            icon: MousePointerClick,
        },
        {
            title: t('dashboard.marketing.orders_closed'),
            value: formatNumber(stats.orders_closed),
            hint: t('dashboard.marketing.orders_closed_hint'),
            icon: Target,
            accent: true,
        },
        {
            title: t('dashboard.marketing.budget'),
            value: formatCurrency(stats.budget_total),
            hint: t('dashboard.marketing.budget_hint'),
            icon: Wallet,
        },
    ];

    return (
        <div className="space-y-8">
            <PageHeader
                title={t('dashboard.marketing.title')}
                description={t('dashboard.marketing.desc')}
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.lead_series}
                    title={t('dashboard.leads_7d')}
                    description={t('dashboard.leads_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.conversion_series}
                    title={t('dashboard.marketing.conversion_chart')}
                    description={t('dashboard.marketing.conversion_chart_desc')}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <LeadSourcePieChart data={stats.lead_sources} title={t('dashboard.marketing.lead_sources')} />
                <RevenueAreaChart
                    data={stats.revenue_series}
                    title={t('dashboard.revenue_7d')}
                    description={t('dashboard.marketing.revenue_chart_desc')}
                />
            </div>

            {stats.funnel?.length > 0 && <ConversionFunnel data={stats.funnel} />}

            {stats.top_sources?.length > 0 && (
                <RankingList
                    title={t('dashboard.marketing.top_sources')}
                    description={t('dashboard.marketing.top_sources_desc')}
                    rows={stats.top_sources}
                    type="sources"
                />
            )}
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="marketing" title={t('dashboard.marketing.title')}>
            <MarketingDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
