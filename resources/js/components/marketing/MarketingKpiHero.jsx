import { Percent, Package, Wallet } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

const ITEMS = [
    {
        key: 'totalRevenue',
        title: 'Tổng doanh thu',
        icon: Wallet,
        format: formatCurrency,
        accent: 'text-emerald-600 dark:text-emerald-400',
        bg: 'from-emerald-500/10 to-transparent',
    },
    {
        key: 'productQuantity',
        title: 'Sản phẩm bán ra',
        icon: Package,
        format: (v) => `${formatNumber(v)} SP`,
        accent: 'text-blue-600 dark:text-blue-400',
        bg: 'from-blue-500/10 to-transparent',
    },
    {
        key: 'conversionRate',
        title: 'Tỷ lệ chốt',
        icon: Percent,
        format: formatPercent,
        accent: 'text-violet-600 dark:text-violet-400',
        bg: 'from-violet-500/10 to-transparent',
    },
];

export function MarketingKpiHero({ kpis }) {
    return (
        <div className="grid gap-6 md:grid-cols-3">
            {ITEMS.map(({ key, title, icon: Icon, format, accent, bg }) => (
                <Card
                    key={key}
                    className={cn(
                        'overflow-hidden border-border/80 bg-gradient-to-br py-0 shadow-sm',
                        bg
                    )}
                >
                    <CardContent className="flex flex-col gap-4 p-6 sm:p-8">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm font-medium text-muted-foreground">{title}</p>
                            <div className="rounded-xl border border-border/60 bg-background/80 p-2.5">
                                <Icon className={cn('size-5', accent)} />
                            </div>
                        </div>
                        <p className={cn('text-3xl font-bold tracking-tight tabular-nums sm:text-4xl', accent)}>
                            {format(kpis?.[key])}
                        </p>
                        {key === 'conversionRate' && kpis?.closedOrders != null && (
                            <p className="text-xs text-muted-foreground">
                                {formatNumber(kpis.closedOrders)} đơn / {formatNumber(kpis.contacts)} contact
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
