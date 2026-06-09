import { Head } from '@inertiajs/react';

import { LeadSourcePieChart } from '@/components/charts/LeadSourcePieChart';
import { OrdersBarChart } from '@/components/charts/OrdersBarChart';
import { RevenueAreaChart } from '@/components/charts/RevenueAreaChart';
import { StatCard } from '@/components/charts/StatCard';
import { PageHeader } from '@/components/layout/PageHeader';
import { formatNumber } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout';

export default function BusinessOverview({ summary, charts }) {
    return (
        <AppLayout>
            <Head title="Toàn cảnh vận hành" />

            <div className="space-y-6">
                <PageHeader
                    title="Toàn cảnh vận hành"
                    description="Số liệu marketing, telesale, kho và đối soát vận chuyển gom về một màn hình."
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Tổng đơn" value={formatNumber(summary.orders_total)} hint="Toàn bộ đơn hàng trên hệ thống" />
                    <StatCard title="Đơn giao thành công" value={formatNumber(summary.orders_delivered)} hint="Đã giao hoặc đã thu tiền" />
                    <StatCard title="Lead hôm nay" value={formatNumber(summary.leads_today)} hint="Từ trang Landing và các kênh quảng cáo" />
                    <StatCard title="Lệch tiền vận chuyển" value={formatNumber(summary.shipping_mismatch)} hint="Số lần hãng vận chuyển báo lệch COD" />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <RevenueAreaChart
                        data={charts.revenue_by_day}
                        title="Doanh thu 7 ngày"
                        description="Doanh thu từ đơn giao thành công"
                    />
                    <OrdersBarChart
                        data={charts.orders_by_day}
                        title="Đơn phát sinh 7 ngày"
                        description="Số lượng đơn theo ngày"
                    />
                    <LeadSourcePieChart
                        data={charts.lead_sources}
                        title="Nguồn lead hôm nay"
                    />
                </div>
            </div>
        </AppLayout>
    );
}
