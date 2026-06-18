import { Head } from '@inertiajs/react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatNumber } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function BusinessOverview({ summary, charts }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.business_overview.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.business_overview.title')}
                    description={t('pages.business_overview.desc')}
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('pages.business_overview.orders_total')}
                        value={formatNumber(summary.orders_total)}
                        hint={t('pages.business_overview.orders_total_hint')}
                    />
                    <StatCard
                        title={t('pages.business_overview.orders_delivered')}
                        value={formatNumber(summary.orders_delivered)}
                        hint={t('pages.business_overview.orders_delivered_hint')}
                    />
                    <StatCard
                        title={t('pages.business_overview.leads_today')}
                        value={formatNumber(summary.leads_today)}
                        hint={t('pages.business_overview.leads_today_hint')}
                    />
                    <StatCard
                        title={t('pages.business_overview.shipping_mismatch')}
                        value={formatNumber(summary.shipping_mismatch)}
                        hint={t('pages.business_overview.shipping_mismatch_hint')}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={charts.revenue_by_day}
                        title={t('pages.business_overview.revenue_7d')}
                        description={t('pages.business_overview.revenue_7d_desc')}
                    />
                    <OrdersBarChart
                        data={charts.orders_by_day}
                        title={t('pages.business_overview.orders_7d')}
                        description={t('pages.business_overview.orders_7d_desc')}
                    />
                    <LeadSourcePieChart
                        data={charts.lead_sources}
                        title={t('pages.business_overview.lead_sources')}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
