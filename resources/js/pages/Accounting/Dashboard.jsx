import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { formatCurrency, formatNumber } from '@/lib/format';

export default function Dashboard({ stats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Kế toán" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Kế toán</h1>
                    <p className="text-sm text-muted-foreground">Theo dõi COD, đối soát và thu tiền</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Chờ thu COD" value={formatNumber(stats.pending_cod)} />
                    <StatCard title="Đã thu hôm nay" value={formatNumber(stats.paid_today)} accent />
                    <StatCard title="Lệch COD" value={formatNumber(stats.cod_mismatch)} />
                    <StatCard title="Chờ đối soát" value={formatNumber(stats.reconciliation_pending)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RevenueAreaChart
                        data={stats.revenue_series}
                        title="Doanh thu 7 ngày"
                        valueFormatter={(v) => formatCurrency(v)}
                    />
                    <RevenueAreaChart
                        data={stats.cod_series}
                        title="COD thu 7 ngày"
                        valueFormatter={(v) => formatCurrency(v)}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
