import { Deferred, Head } from '@inertiajs/react';
import { Megaphone, MousePointerClick, Target, Wallet } from 'lucide-react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { ConversionFunnel } from '@/components/dashboard/ConversionFunnel';
import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { RankingList } from '@/components/dashboard/RankingList';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import AppLayout from '@/layouts/AppLayout';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber } from '@/lib/format';

function MarketingDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('marketing', initialStats);

    const kpis = [
        {
            title: 'Chiến dịch đang chạy',
            value: formatNumber(stats.active_campaigns),
            hint: 'Nguồn / campaign đang active',
            icon: Megaphone,
        },
        {
            title: 'Lead hôm nay',
            value: formatNumber(stats.leads_today),
            hint: 'Lead từ landing/platform trong kỳ',
            icon: MousePointerClick,
        },
        {
            title: 'Đơn chốt hôm nay',
            value: formatNumber(stats.orders_closed),
            hint: 'Đơn chốt từ campaign phụ trách',
            icon: Target,
            accent: true,
        },
        {
            title: 'Ngân sách',
            value: formatCurrency(stats.budget_total),
            hint: 'Tổng budget nguồn marketing',
            icon: Wallet,
        },
    ];

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Marketing</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Theo dõi hiệu quả campaign, lead source, conversion và doanh thu theo nguồn.
                    </p>
                </div>
                <RealtimeBadge connected={connected} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.lead_series}
                    title="Lead 7 ngày"
                    description="Lead ingest theo ngày"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.conversion_series}
                    title="Tỷ lệ chốt / đơn theo ngày"
                    description="Hiệu quả chuyển đổi từ nguồn marketing"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <LeadSourcePieChart data={stats.lead_sources} title="Nguồn lead" />
                <RevenueAreaChart
                    data={stats.revenue_series}
                    title="Doanh thu 7 ngày"
                    description="Doanh thu delivered/paid từ campaign"
                />
            </div>

            {stats.funnel?.length > 0 && <ConversionFunnel data={stats.funnel} />}

            {stats.top_sources?.length > 0 && (
                <RankingList
                    title="Top nguồn / campaign"
                    description="Nguồn tạo đơn và doanh thu tốt nhất"
                    rows={stats.top_sources}
                    type="sources"
                />
            )}
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Marketing" />

            <Deferred data="stats" fallback={<DashboardSkeleton role="marketing" />}>
                <MarketingDashboardContent stats={initialStats} />
            </Deferred>
        </AppLayout>
    );
}
