import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { formatNumber } from '@/lib/format';

export default function Dashboard({ stats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Kho" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Kho</h1>
                    <p className="text-sm text-muted-foreground">Tình trạng xuất kho, vận đơn và tồn kho</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Chờ vận đơn" value={formatNumber(stats.waiting_waybill)} />
                    <StatCard title="Đang giao" value={formatNumber(stats.delivering)} />
                    <StatCard title="Chờ lấy hàng" value={formatNumber(stats.pending_export)} />
                    <StatCard title="SP sắp hết" value={formatNumber(stats.low_stock_items)} accent />
                </div>

                <OrdersBarChart
                    data={stats.orders_series}
                    title="Đơn xử lý 7 ngày"
                    description="Số đơn có data về theo ngày"
                />

                {stats.inventory_alerts?.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold">Cảnh báo tồn kho thấp</h2>
                        <div className="space-y-2">
                            {stats.inventory_alerts.map((row) => (
                                <div
                                    key={`${row.warehouse}-${row.product}`}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="truncate">
                                        {row.product} · {row.warehouse}
                                    </span>
                                    <span className="shrink-0 font-medium text-destructive">
                                        Còn {formatNumber(row.stock)}
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
