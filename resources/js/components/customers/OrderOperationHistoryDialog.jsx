import { Loader2, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { apiGet } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';


function ActionBadge({ action, children }) {
    const styles = {
        initial_snapshot: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        call: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
        status_updated: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
        order_updated: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
        order_closed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    };

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-1 text-[11px] font-semibold',
                styles[action] ?? 'bg-muted text-foreground',
            )}
        >
            {children}
        </span>
    );
}

export function OrderOperationHistoryDialog({ order }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [histories, setHistories] = useState([]);
    const [hasMore, setHasMore] = useState(false);

    useEffect(() => {
        if (!open || loaded) return;

        let active = true;
        setLoading(true);

        apiGet(`/customers/orders/${order.id}/operation-history`)
            .then((data) => {
                if (!active) return;
                setCustomer(data.customer ?? null);
                setHistories(data.histories ?? []);
                setHasMore(Boolean(data.hasMore));
                setLoaded(true);
            })
            .catch((error) => {
                if (active) toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [loaded, open, order.id, t]);

    const handleOpenChange = (nextOpen) => {
        setOpen(nextOpen);
        if (!nextOpen) return;

        // Khi row được Inertia refresh, mở lại sẽ lấy dữ liệu mới nhất.
        setLoaded(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    className="text-sky-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950/40"
                    title={t('operations.customer_interactions.history_title')}
                    aria-label={t('operations.customer_interactions.history_title')}
                >
                    <RotateCcw className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent className="ps-dialog-surface max-h-[88vh] overflow-hidden p-0" style={{ '--ps-dialog-width': '1280px' }}>
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <DialogTitle>{t('operations.customer_interactions.history_title')}</DialogTitle>
                    <DialogDescription>
                        {(customer?.name ?? order.customerName ?? '—')} · {(customer?.phone ?? order.customerPhone ?? '—')}
                        {' · '}
                        {customer?.orderCode ?? order.orderCode}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(88vh-104px)] overflow-auto p-5">
                    {loading ? (
                        <div className="flex min-h-48 items-center justify-center text-muted-foreground">
                            <Loader2 className="mr-2 size-5 animate-spin" />
                            {t('operations.customer_interactions.loading')}
                        </div>
                    ) : histories.length ? (
                        <>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="min-w-[1120px] w-full border-collapse text-sm">
                                    <thead className="sticky top-0 z-10 bg-primary text-primary-foreground">
                                        <tr>
                                            <th className="w-14 border-r border-primary-foreground/20 px-3 py-3 text-center">#</th>
                                            <th className="min-w-52 border-r border-primary-foreground/20 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_activity')}
                                            </th>
                                            <th className="min-w-40 border-r border-primary-foreground/20 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_before')}
                                            </th>
                                            <th className="min-w-40 border-r border-primary-foreground/20 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_result')}
                                            </th>
                                            <th className="min-w-40 border-r border-primary-foreground/20 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_next')}
                                            </th>
                                            <th className="min-w-72 border-r border-primary-foreground/20 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_note')}
                                            </th>
                                            <th className="min-w-44 px-3 py-3 text-left">
                                                {t('operations.customer_interactions.history_updated_at')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {histories.map((history, index) => (
                                            <tr key={history.id} className="border-b align-top last:border-b-0 hover:bg-muted/40">
                                                <td className="border-r px-3 py-3 text-center text-muted-foreground">{index + 1}</td>
                                                <td className="border-r px-3 py-3">
                                                    <ActionBadge action={history.action}>{history.actionLabel}</ActionBadge>
                                                    <div className="mt-2 font-medium">{history.actorName}</div>
                                                    {history.actorRole && (
                                                        <div className="text-xs text-muted-foreground">{history.actorRole}</div>
                                                    )}
                                                </td>
                                                <td className="border-r px-3 py-3">
                                                    <div>{history.stageBefore || '—'}</div>
                                                </td>
                                                <td className="border-r px-3 py-3">
                                                    <div className="font-medium">{history.result || '—'}</div>
                                                    {history.metadata?.contact_count ? (
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            {t('operations.customer_interactions.contact_count', {
                                                                count: history.metadata.contact_count,
                                                            })}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="border-r px-3 py-3">
                                                    <div>{history.stageAfter || '—'}</div>
                                                    {history.nextOperationAt && (
                                                        <div className="mt-1 text-xs text-primary">
                                                            {formatDateTime(history.nextOperationAt)}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="border-r whitespace-pre-wrap px-3 py-3 text-muted-foreground">
                                                    {history.note || '—'}
                                                </td>
                                                <td className="px-3 py-3 text-muted-foreground">
                                                    {formatDateTime(history.createdAt)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {hasMore && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {t('operations.customer_interactions.history_limited')}
                                </p>
                            )}
                        </>
                    ) : (
                        <div className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                            {t('operations.customer_interactions.history_empty')}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
