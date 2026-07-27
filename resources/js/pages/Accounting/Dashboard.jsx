import { AlertTriangle, Banknote, FileCheck2, WalletCards } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RoleDashboardFrame, RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function AccountingDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('accounting', initialStats);
    const paidValue = stats.paid_today ?? stats.paid ?? 0;

    const kpis = [
        { title: t('dashboard.accounting.pending_cod'), value: formatNumber(stats.pending_cod), hint: t('dashboard.accounting.pending_cod_hint'), icon: WalletCards },
        { title: t('dashboard.accounting.paid'), value: formatNumber(paidValue), hint: t('dashboard.accounting.paid_hint'), icon: Banknote, accent: true },
        { title: t('dashboard.accounting.cod_mismatch'), value: formatNumber(stats.cod_mismatch), hint: t('dashboard.accounting.cod_mismatch_hint'), icon: AlertTriangle },
        { title: t('dashboard.accounting.reconciliation'), value: formatNumber(stats.reconciliation_pending), hint: t('dashboard.accounting.reconciliation_hint'), icon: FileCheck2 },
    ];

    const alerts = [
        Number(stats.cod_mismatch ?? 0) > 0 && { type: 'warning', title: t('dashboard.accounting.cod_mismatch'), value: stats.cod_mismatch, description: t('dashboard.accounting.cod_mismatch_alert') },
        Number(stats.reconciliation_pending ?? 0) > 0 && { type: 'info', title: t('dashboard.accounting.reconciliation'), value: stats.reconciliation_pending, description: t('dashboard.accounting.reconciliation_alert') },
    ].filter(Boolean);

    return (
        <RoleDashboardFrame
            role="accounting"
            title={t('dashboard.accounting.title')}
            subtitle={t('dashboard.accounting.desc')}
            actions={<RealtimeBadge connected={connected} />}
        >
            <div className="ps-role-kpi-grid is-4">
                {kpis.map((card) => <StatCard key={card.title} {...card} className="ps-role-kpi-card" />)}
            </div>

            <div className="ps-role-chart-grid">
                <RevenueAreaChart
                    data={stats.revenue_series}
                    title={t('dashboard.revenue_7d')}
                    description={t('dashboard.revenue_7d_desc')}
                    valueFormatter={(v) => formatCurrency(v)}
                />
                <RevenueAreaChart
                    data={stats.cod_series}
                    title={t('dashboard.accounting.cod_7d')}
                    description={t('dashboard.accounting.cod_7d_desc')}
                    valueFormatter={(v) => formatCurrency(v)}
                />
            </div>

            <div className="ps-role-chart-grid is-single">
                <OrdersBarChart
                    data={stats.paid_orders_series}
                    title={t('dashboard.accounting.paid_7d')}
                    description={t('dashboard.accounting.paid_7d_desc')}
                />
            </div>

            <OpsAlerts alerts={alerts} />
        </RoleDashboardFrame>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="accounting" title={t('dashboard.accounting.title')}>
            <AccountingDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
