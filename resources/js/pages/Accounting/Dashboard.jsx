import { AlertTriangle, Banknote, FileCheck2, WalletCards } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RoleDashboardShell } from '@/components/dashboard/RoleDashboardShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatCurrency, formatNumber } from '@/lib/format';

function AccountingDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('accounting', initialStats);

    const paidValue = stats.paid_today ?? stats.paid ?? 0;
    const kpis = [
        {
            title: 'Chờ thu COD',
            value: formatNumber(stats.pending_cod),
            hint: 'Đơn delivered chờ ghi nhận tiền',
            icon: WalletCards,
        },
        {
            title: 'Đã thu',
            value: formatNumber(paidValue),
            hint: 'Đơn paid trong kỳ hiện tại',
            icon: Banknote,
            accent: true,
        },
        {
            title: 'Lệch COD',
            value: formatNumber(stats.cod_mismatch),
            hint: 'Webhook vận chuyển lệch tiền',
            icon: AlertTriangle,
        },
        {
            title: 'Chờ đối soát',
            value: formatNumber(stats.reconciliation_pending),
            hint: 'Đơn pending reconciliation',
            icon: FileCheck2,
        },
    ];

    const alerts = [
        Number(stats.cod_mismatch ?? 0) > 0 && {
            type: 'warning',
            title: 'Lệch COD',
            value: stats.cod_mismatch,
            description: 'Cần kiểm tra dữ liệu từ hãng vận chuyển và số tiền đối soát.',
        },
        Number(stats.reconciliation_pending ?? 0) > 0 && {
            type: 'info',
            title: 'Chờ đối soát',
            value: stats.reconciliation_pending,
            description: 'Danh sách đơn cần xác nhận COD / chuyển khoản.',
        },
    ].filter(Boolean);

    return (
        <div className="space-y-6">
            <PageHeader
                title="Dashboard Kế toán"
                description="Theo dõi COD, doanh thu, lệch tiền và tiến độ đối soát vận chuyển."
                actions={<RealtimeBadge connected={connected} />}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((card) => (
                    <StatCard key={card.title} {...card} className="min-h-[132px]" />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <RevenueAreaChart
                    className="col-span-full lg:col-span-1"
                    data={stats.revenue_series}
                    title="Doanh thu 7 ngày"
                    description="Doanh thu delivered/paid theo ngày"
                    valueFormatter={(v) => formatCurrency(v)}
                />
                <RevenueAreaChart
                    className="col-span-full lg:col-span-1"
                    data={stats.cod_series}
                    title="COD thu 7 ngày"
                    description="Tổng COD ghi nhận theo ngày"
                    valueFormatter={(v) => formatCurrency(v)}
                />
            </div>

            <OrdersBarChart
                data={stats.paid_orders_series}
                title="Đơn paid 7 ngày"
                description="Số đơn đã thanh toán theo ngày"
            />

            <OpsAlerts alerts={alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <RoleDashboardShell role="accounting" title="Dashboard Kế toán">
            <AccountingDashboardContent stats={initialStats} />
        </RoleDashboardShell>
    );
}
