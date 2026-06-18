import { AlertTriangle, Package, PackageCheck, Truck } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function WarehouseDashboardContent({ stats: initialStats }) {
    const t = useT();
    const { stats, connected } = useRealtimeDashboard('warehouse', initialStats);

    const kpis = [
        {
            title: t('dashboard.warehouse.waiting_waybill'),
            value: formatNumber(stats.waiting_waybill),
            hint: t('dashboard.warehouse.waiting_waybill_hint'),
            icon: PackageCheck,
        },
        {
            title: t('dashboard.warehouse.delivering'),
            value: formatNumber(stats.delivering),
            hint: t('dashboard.warehouse.delivering_hint'),
            icon: Truck,
        },
        {
            title: t('dashboard.warehouse.pending_export'),
            value: formatNumber(stats.pending_export),
            hint: t('dashboard.warehouse.pending_export_hint'),
            icon: Package,
        },
        {
            title: t('dashboard.warehouse.low_stock'),
            value: formatNumber(stats.low_stock_items ?? stats.stock_issues),
            hint: t('dashboard.warehouse.low_stock_hint'),
            icon: AlertTriangle,
            accent: true,
        },
    ];

    const alerts = (stats.inventory_alerts ?? []).map((row) => ({
        type: 'warning',
        title: row.product,
        value: row.stock,
        description: t('dashboard.warehouse.stock_alert', {
            warehouse: row.warehouse,
            stock: formatNumber(row.stock),
        }),
    }));

    return (
        <div className="space-y-6">
            <PageHeader
                title={t('dashboard.warehouse.title')}
                description={t('dashboard.warehouse.desc')}
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <RevenueAreaChart
                    data={stats.orders_series}
                    title={t('dashboard.warehouse.orders_7d')}
                    description={t('dashboard.warehouse.orders_7d_desc')}
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.delivery_breakdown}
                    title={t('dashboard.warehouse.delivery_status')}
                    description={t('dashboard.warehouse.delivery_status_desc')}
                />
            </div>

            <OpsAlerts alerts={alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    const t = useT();

    return (
        <RoleDashboardShell role="warehouse" title={t('dashboard.warehouse.title')}>
            <WarehouseDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
