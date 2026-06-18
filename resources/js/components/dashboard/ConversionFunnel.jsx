import { ArrowDown } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function ConversionFunnel({ data }) {
    const t = useT();
    const steps = data ?? [];
    const total = Math.max(Number(steps[0]?.value ?? 0), 1);
    const paidStep = steps[steps.length - 1];
    const finalRate = Math.round((Number(paidStep?.value ?? 0) / total) * 1000) / 10;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>{t('dashboard.funnel_ui.title')}</CardTitle>
                    <CardDescription>{t('dashboard.funnel_ui.desc')}</CardDescription>
                </div>
                {steps.length > 0 && (
                    <div className="rounded-xl border bg-muted/40 px-3 py-2 text-right">
                        <p className="text-xs text-muted-foreground">{t('dashboard.funnel_ui.final_rate')}</p>
                        <p className="text-lg font-bold tabular-nums">{formatPercent(finalRate)}</p>
                    </div>
                )}
            </CardHeader>
            <CardContent>
                {steps.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('dashboard.funnel_ui.empty')}
                    </p>
                ) : (
                    <div className="grid gap-3 md:grid-cols-5">
                        {steps.map((step, index) => {
                            const value = Number(step.value ?? 0);
                            const previousValue = Number(steps[index - 1]?.value ?? total);
                            const percentOfTotal = Math.round((value / total) * 1000) / 10;
                            const stepRate = index === 0
                                ? 100
                                : Math.round((value / Math.max(previousValue, 1)) * 1000) / 10;

                            return (
                                <div key={step.label} className="relative rounded-xl border bg-card p-4">
                                    {index > 0 && (
                                        <div className="absolute -top-3 left-1/2 hidden -translate-x-1/2 rounded-full border bg-background p-1 text-muted-foreground md:-left-3 md:top-1/2 md:block md:-translate-y-1/2 md:translate-x-0">
                                            <ArrowDown className="size-3 md:-rotate-90" />
                                        </div>
                                    )}
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium">{step.label}</p>
                                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                                {formatNumber(value)}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                            {formatPercent(percentOfTotal)}
                                        </span>
                                    </div>
                                    <div className="mt-4">
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{ width: `${Math.max(percentOfTotal, 3)}%` }}
                                            />
                                        </div>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {index === 0
                                                ? t('dashboard.funnel_ui.start')
                                                : t('dashboard.funnel_ui.vs_prev', { rate: formatPercent(stepRate) })}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
