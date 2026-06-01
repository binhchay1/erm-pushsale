import { Deferred, Head } from '@inertiajs/react';
import { AlertTriangle, Package, PackageCheck, Truck } from 'lucide-react';

import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { OpsAlerts } from '@/components/dashboard/OpsAlerts';
import { RealtimeBadge } from '@/components/layout/RealtimeBadge';
import AppLayout from '@/layouts/AppLayout';
import { useRealtimeDashboard } from '@/hooks/useRealtimeDashboard';
import { formatNumber } from '@/lib/format';

function WarehouseDashboardContent({ stats: initialStats }) {
    const { stats, connected } = useRealtimeDashboard('warehouse', initialStats);

    const kpis = [
        {
            title: 'Chờ vận đơn',
            value: formatNumber(stats.waiting_waybill),
            hint: 'Đơn cần tạo / đẩy vận đơn',
            icon: PackageCheck,
        },
        {
            title: 'Đang giao',
            value: formatNumber(stats.delivering),
            hint: 'Đơn đang ở carrier',
            icon: Truck,
        },
        {
            title: 'Chờ lấy hàng',
            value: formatNumber(stats.pending_export),
            hint: 'Đơn cần bàn giao kho / shipper',
            icon: Package,
        },
        {
            title: 'SP sắp hết',
            value: formatNumber(stats.low_stock_items ?? stats.stock_issues),
            hint: 'SKU tồn thấp cần xử lý',
            icon: AlertTriangle,
            accent: true,
        },
    ];

    const alerts = (stats.inventory_alerts ?? []).map((row) => ({
        type: 'warning',
        title: row.product,
        value: row.stock,
        description: `${row.warehouse} còn ${formatNumber(row.stock)} sản phẩm`,
    }));

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Kho</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Theo dõi vận đơn, xử lý xuất kho, đơn đang giao và cảnh báo tồn kho.
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
                    data={stats.orders_series}
                    title="Đơn xử lý 7 ngày"
                    description="Số đơn phát sinh theo ngày để kho xử lý"
                    valueFormatter={(v) => formatNumber(v)}
                    yTickFormatter={(v) => String(v)}
                />
                <OrdersBarChart
                    data={stats.delivery_breakdown}
                    title="Trạng thái vận đơn"
                    description="Phân bổ đơn đang xử lý"
                />
            </div>

            <OpsAlerts alerts={alerts} />
        </div>
    );
}

export default function Dashboard({ stats: initialStats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Kho" />

            <Deferred data="stats" fallback={<DashboardSkeleton role="warehouse" />}>
                <WarehouseDashboardContent stats={initialStats} />
            </Deferred>
        </AppLayout>
    );
}
