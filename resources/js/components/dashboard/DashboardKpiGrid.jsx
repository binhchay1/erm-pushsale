import { AlertTriangle, CheckCircle2, PackageCheck, PhoneCall, TrendingUp } from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function DashboardKpiGrid({ stats }) {
    const t = useT();
    const alertCount = Number(stats.failed_orders ?? 0) + Number(stats.shipping_mismatch ?? 0);

    const cards = [
        {
            title: t('dashboard.kpi.revenue_today'),
            value: formatCurrency(stats.revenue_today),
            hint: t('dashboard.kpi.revenue_today_hint'),
            icon: TrendingUp,
            accent: true,
        },
        {
            title: t('dashboard.kpi.orders_closed'),
            value: formatNumber(stats.orders_closed),
            hint: t('dashboard.kpi.orders_closed_hint'),
            icon: PackageCheck,
        },
        {
            title: t('dashboard.kpi.leads_today'),
            value: formatNumber(stats.leads_today),
            hint: t('dashboard.kpi.leads_today_hint'),
            icon: PhoneCall,
        },
        {
            title: t('dashboard.kpi.delivery_rate'),
            value: formatPercent(stats.delivery_rate),
            hint: t('dashboard.kpi.delivery_rate_hint'),
            icon: CheckCircle2,
        },
        {
            title: t('dashboard.kpi.ops_alerts'),
            value: formatNumber(alertCount),
            hint: t('dashboard.kpi.ops_alerts_hint'),
            icon: AlertTriangle,
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            {cards.map((card) => (
                <StatCard
                    key={card.title}
                    title={card.title}
                    value={card.value}
                    hint={card.hint}
                    accent={card.accent}
                    icon={card.icon}
                    className="min-h-[132px]"
                />
            ))}
        </div>
    );
}
