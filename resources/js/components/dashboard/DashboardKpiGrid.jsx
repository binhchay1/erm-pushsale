import { AlertTriangle, CheckCircle2, PackageCheck, PhoneCall, TrendingUp } from 'lucide-react';

import { StatCard } from '@/components/charts/StatCard';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

export function DashboardKpiGrid({ stats }) {
    const alertCount = Number(stats.failed_orders ?? 0) + Number(stats.shipping_mismatch ?? 0);

    const cards = [
        {
            title: 'Doanh thu hôm nay',
            value: formatCurrency(stats.revenue_today),
            hint: 'Đơn đã giao / đã thanh toán trong ngày',
            icon: TrendingUp,
            accent: true,
        },
        {
            title: 'Đơn đã chốt',
            value: formatNumber(stats.orders_closed),
            hint: 'Đơn phát sinh hôm nay',
            icon: PackageCheck,
        },
        {
            title: 'Lead hôm nay',
            value: formatNumber(stats.leads_today),
            hint: 'Từ webhook/landing/platform',
            icon: PhoneCall,
        },
        {
            title: 'Tỷ lệ giao thành công',
            value: formatPercent(stats.delivery_rate),
            hint: 'Delivered hoặc paid / tổng đơn',
            icon: CheckCircle2,
        },
        {
            title: 'Cảnh báo vận hành',
            value: formatNumber(alertCount),
            hint: 'Đơn lỗi + lệch COD',
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
