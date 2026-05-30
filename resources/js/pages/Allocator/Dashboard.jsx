import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { StatCard } from '@/components/charts/StatCard';
import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { formatNumber } from '@/lib/format';

export default function Dashboard({ stats }) {
    return (
        <AppLayout>
            <Head title="Dashboard Chia số" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard Chia số</h1>
                    <p className="text-sm text-muted-foreground">Lead ingest, phân số và xử lý lỗi</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard title="Lead hôm nay" value={formatNumber(stats.leads_today)} />
                    <StatCard title="Chờ phân số" value={formatNumber(stats.pending_routing)} />
                    <StatCard title="Đã xử lý hôm nay" value={formatNumber(stats.processed_today)} accent />
                    <StatCard title="Lead lỗi" value={formatNumber(stats.failed_leads)} />
                    <StatCard title="Trùng số" value={formatNumber(stats.duplicate_leads)} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RevenueAreaChart
                        data={stats.lead_series}
                        title="Lead ingest 7 ngày"
                        valueFormatter={(v) => formatNumber(v)}
                        yTickFormatter={(v) => String(v)}
                    />
                    <LeadSourcePieChart data={stats.platform_breakdown} title="Lead theo nền tảng" />
                </div>
            </div>
        </AppLayout>
    );
}
