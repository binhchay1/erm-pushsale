import { AlertCircle, CheckCircle2, Package } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

/** Display fee calculation result from API (`display` field normalized by backend). */
export function ShippingFeeResult({ display }) {
    const t = useT();

    if (!display) return null;

    if (!display.success) {
        return (
            <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                <div className="flex items-start gap-2">
                    <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <div>
                        <p className="text-sm font-semibold text-destructive">{t('shipping.fee_failed')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {display.message ?? t('shipping.fee_check_config')}
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <div className="mb-3 flex items-center gap-2">
                <CheckCircle2 className="size-4 text-emerald-600" />
                <p className="text-sm font-semibold">{t('shipping.fee_result')}</p>
            </div>

            {display.lines?.length > 0 && (
                <dl className="grid gap-2 sm:grid-cols-2">
                    {display.lines.map((line) => (
                        <div
                            key={line.label}
                            className={cn(
                                'rounded-md border bg-card px-3 py-2',
                                line.highlight && 'border-primary/40 bg-primary/5',
                            )}
                        >
                            <dt className="text-xs text-muted-foreground">{line.label}</dt>
                            <dd
                                className={cn(
                                    'text-sm font-semibold tabular-nums',
                                    line.highlight && 'text-primary',
                                )}
                            >
                                {line.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}

            {display.options?.length > 0 && (
                <div className="mt-3 space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        {t('shipping.fee_services')}
                    </p>
                    <ul className="space-y-1.5">
                        {display.options.map((opt, idx) => (
                            <li
                                key={`${opt.label}-${idx}`}
                                className="flex items-center justify-between gap-3 rounded-md border bg-card px-3 py-2 text-sm"
                            >
                                <div className="flex items-center gap-2 min-w-0">
                                    <Package className="size-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate font-medium">{opt.label}</span>
                                </div>
                                <div className="text-right shrink-0">
                                    <span className="font-semibold tabular-nums text-primary">
                                        {opt.value}
                                    </span>
                                    {opt.note && (
                                        <p className="text-[10px] text-muted-foreground">{opt.note}</p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {display.lines?.length === 0 && display.options?.length === 0 && (
                <p className="text-sm text-muted-foreground">{t('shipping.fee_empty_detail')}</p>
            )}
        </div>
    );
}

/** @deprecated */
export function ShippingFeeResultLegacy({ data }) {
    if (!data?.display) return null;
    return <ShippingFeeResult display={data.display} />;
}
