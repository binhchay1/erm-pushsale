import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { formatCurrency, formatNumber } from '@/lib/format';

export default function Dashboard({ stats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Marketing" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Marketing</h1>
                    <p className="text-sm text-muted-foreground">Hiệu quả chiến dịch & nguồn lead của bạn</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Chiến dịch đang chạy" value={formatNumber(stats.active_campaigns)} />
                    <StatCard title="Lead hôm nay" value={formatNumber(stats.leads_today)} />
                    <StatCard title="Đơn chốt hôm nay" value={formatNumber(stats.orders_closed)} accent />
                    <StatCard title="Ngân sách" value={formatCurrency(stats.budget_total)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RevenueAreaChart
                        data={stats.lead_series}
                        title="Lead 7 ngày"
                        valueFormatter={(v) => formatNumber(v)}
                        yTickFormatter={(v) => String(v)}
                    />
                    <OrdersBarChart data={stats.conversion_series} title="Tỷ lệ chốt (%)" />
                </div>

                {stats.top_sources?.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold">Top nguồn của bạn</h2>
                        <div className="space-y-2">
                            {stats.top_sources.map((row) => (
                                <div
                                    key={row.name}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="truncate font-medium">{row.name}</span>
                                    <span className="shrink-0 text-muted-foreground">
                                        {formatNumber(row.contacts)} contact · {formatNumber(row.orders)} đơn
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
