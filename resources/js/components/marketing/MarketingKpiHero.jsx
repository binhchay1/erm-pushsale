import { Percent, Package, ShoppingBag, Wallet } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function MarketingKpiHero({ kpis }) {
    const t = useT();

    const items = [
        {
            key: 'totalRevenue',
            title: t('dashboard.marketing.kpi_revenue'),
            icon: Wallet,
            format: formatCurrency,
            accent: 'text-emerald-600 dark:text-emerald-400',
            bg: 'from-emerald-500/10 to-transparent',
        },
        {
            key: 'productQuantity',
            title: t('dashboard.marketing.kpi_products'),
            icon: Package,
            format: (v) => `${formatNumber(v)} SP`,
            accent: 'text-blue-600 dark:text-blue-400',
            bg: 'from-blue-500/10 to-transparent',
        },
        {
            key: 'conversionRate',
            title: t('dashboard.marketing.kpi_closing_rate'),
            icon: Percent,
            format: formatPercent,
            accent: 'text-violet-600 dark:text-violet-400',
            bg: 'from-violet-500/10 to-transparent',
        },
        {
            key: 'averageOrderValue',
            title: t('dashboard.marketing.kpi_aov'),
            icon: ShoppingBag,
            format: formatCurrency,
            accent: 'text-amber-600 dark:text-amber-400',
            bg: 'from-amber-500/10 to-transparent',
        },
    ];

    return (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {items.map(({ key, title, icon: Icon, format, accent, bg }) => (
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
                                {t('dashboard.marketing.kpi_subtext', {
                                    orders: formatNumber(kpis.closedOrders),
                                    contacts: formatNumber(kpis.contacts),
                                })}
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
