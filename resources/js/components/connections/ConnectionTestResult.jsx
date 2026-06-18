import { CheckCircle2, List, XCircle } from 'lucide-react';

import { ShippingFeeResult } from '@/components/shipping/ShippingFeeResult';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

export function ConnectionTestResult({ result, actionLabel }) {
    const t = useT();

    if (!result) return null;

    const failed = Boolean(result.error) || result.data?.success === false;
    const display = result.data?.display;
    const message =
        result.error ??
        result.data?.message ??
        (failed ? t('integrations.test_failed') : t('integrations.test_success'));

    if (display && (display.lines?.length || display.options?.length)) {
        return (
            <div className="mt-3 space-y-2">
                {actionLabel && (
                    <p className="text-xs text-muted-foreground">
                        {t('integrations.test_result')}{' '}
                        <span className="font-medium text-foreground">{actionLabel}</span>
                    </p>
                )}
                <ShippingFeeResult display={display} />
            </div>
        );
    }

    const lines = display?.lines ?? [];
    const items = display?.items ?? [];

    return (
        <div
            className={cn(
                'mt-3 rounded-lg border p-3 text-sm',
                failed
                    ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10'
                    : 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10',
            )}
        >
            <div className="flex items-start gap-2">
                {failed ? (
                    <XCircle className="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                ) : (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                )}
                <div className="min-w-0 flex-1 space-y-2">
                    {actionLabel && (
                        <p className="text-xs text-muted-foreground">
                            {t('integrations.test_label')}{' '}
                            <span className="font-medium text-foreground">{actionLabel}</span>
                        </p>
                    )}
                    <p
                        className={cn(
                            'font-medium',
                            failed ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300',
                        )}
                    >
                        {message}
                    </p>

                    {lines.length > 0 && (
                        <dl className="grid gap-2 sm:grid-cols-2">
                            {lines.map((line) => (
                                <div
                                    key={line.label}
                                    className={cn(
                                        'rounded-md border bg-card px-3 py-2',
                                        line.highlight && 'border-primary/40 bg-primary/5',
                                    )}
                                >
                                    <dt className="text-xs text-muted-foreground">{line.label}</dt>
                                    <dd className="text-sm font-semibold break-words">{line.value}</dd>
                                </div>
                            ))}
                        </dl>
                    )}

                    {items.length > 0 && (
                        <div className="space-y-1.5">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <List className="size-3.5" />
                                {t('integrations.list_count', { count: items.length })}
                            </p>
                            <ul className="max-h-48 space-y-1 overflow-y-auto rounded-md border bg-card p-2">
                                {items.map((item, idx) => (
                                    <li
                                        key={`${item.label}-${idx}`}
                                        className="rounded px-2 py-1.5 text-xs odd:bg-muted/30"
                                    >
                                        <span className="font-medium">{item.label}</span>
                                        {item.value && (
                                            <span className="text-muted-foreground"> — {item.value}</span>
                                        )}
                                        {item.note && (
                                            <p className="mt-0.5 text-[11px] text-muted-foreground">{item.note}</p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
