import { router } from '@inertiajs/react';
import { CheckCircle2, FilePlus2, Loader2, Merge, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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
import { apiGet, apiPost } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function PacketItems({ items = [] }) {
    if (!items.length) {
        return <div className="text-sm text-muted-foreground">—</div>;
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            {items.map((item, index) => (
                <div
                    key={`${item.name}-${index}`}
                    className="grid grid-cols-[1fr_auto_auto] gap-3 border-b px-3 py-2 text-sm last:border-b-0"
                >
                    <div className="min-w-0 font-medium">{item.name}</div>
                    <div className="text-muted-foreground">x{item.quantity}</div>
                    <div className="min-w-20 text-right">{formatCurrency(item.unit_price)}</div>
                </div>
            ))}
        </div>
    );
}

export function CustomerSupplementPacketsDialog({ order, count = 0 }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [submittingId, setSubmittingId] = useState(null);
    const [packets, setPackets] = useState([]);
    const [pendingCount, setPendingCount] = useState(count);

    const load = useCallback(async ({ silent = false } = {}) => {
        if (!silent) setLoading(true);

        try {
            const data = await apiGet(`/customers/orders/${order.id}/supplement-packets`);
            setPackets(data.packets ?? []);
            setPendingCount(Number(data.pending_count ?? 0));
        } catch (error) {
            if (!silent) toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
        } finally {
            if (!silent) setLoading(false);
        }
    }, [order.id, t]);

    useEffect(() => {
        setPendingCount(count);
    }, [count]);

    useEffect(() => {
        if (!open) return;
        load();
    }, [load, open]);

    const resolve = async (packet, resolution) => {
        const confirmKey = resolution === 'merge_original'
            ? 'supplement_merge_confirm'
            : resolution === 'create_supplemental_order'
              ? 'supplement_create_confirm'
              : 'supplement_ack_confirm';

        if (!window.confirm(t(`operations.customer_interactions.${confirmKey}`))) return;

        setSubmittingId(packet.id);
        try {
            await apiPost(
                `/customers/orders/${order.id}/supplement-packets/${packet.id}/review`,
                { resolution },
            );
            toast.success(t('operations.customer_interactions.supplement_review_success'));
            await load({ silent: true });
            router.reload({ only: ['report'], preserveScroll: true, preserveState: true });
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.supplement_review_failed'));
        } finally {
            setSubmittingId(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="mt-1 inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 transition hover:bg-amber-100"
                    title={t('operations.order_table.pending_supplement_hint')}
                >
                    <TriangleAlert className="size-3" />
                    {t('operations.order_table.pending_supplement', { count: pendingCount })}
                </button>
            </DialogTrigger>

            <DialogContent className="max-h-[88vh] max-w-[min(980px,calc(100vw-2rem))] overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <DialogTitle>{t('operations.customer_interactions.supplement_title')}</DialogTitle>
                    <DialogDescription>
                        {order.customerName ?? '—'} · {order.customerPhone ?? '—'} · {order.orderCode}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(88vh-104px)] space-y-4 overflow-auto p-5">
                    {loading ? (
                        <div className="flex min-h-48 items-center justify-center text-muted-foreground">
                            <Loader2 className="mr-2 size-5 animate-spin" />
                            {t('operations.customer_interactions.loading')}
                        </div>
                    ) : packets.length ? (
                        packets.map((packet) => {
                            const pending = packet.requires_review && !packet.reviewed_at;
                            const busy = submittingId === packet.id;

                            return (
                                <section
                                    key={packet.id}
                                    className={`rounded-xl border p-4 ${pending ? 'border-amber-300 bg-amber-50/60 dark:bg-amber-950/10' : 'bg-card'}`}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                                                    {packet.packet_type_label ?? packet.packet_type}
                                                </span>
                                                <span className="rounded-full bg-muted px-2 py-1 text-xs font-medium">
                                                    {packet.status_label ?? packet.status}
                                                </span>
                                                {pending && (
                                                    <span className="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                                        {t('operations.customer_interactions.supplement_pending')}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-2 text-xs text-muted-foreground">
                                                {formatDateTime(packet.created_at)}
                                                {packet.external_id ? ` · ${packet.external_id}` : ''}
                                            </div>
                                        </div>
                                        <div className="text-right text-xs text-muted-foreground">
                                            {packet.order_code && <div>{t('operations.customer_interactions.supplement_merged_order')}: {packet.order_code}</div>}
                                            {packet.related_order_code && <div>{t('operations.customer_interactions.supplement_related_order')}: {packet.related_order_code}</div>}
                                        </div>
                                    </div>

                                    <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_280px]">
                                        <PacketItems items={packet.items} />
                                        <div className="space-y-2 text-sm">
                                            {packet.discount > 0 && (
                                                <div className="flex justify-between gap-3">
                                                    <span className="text-muted-foreground">{t('operations.customer_interactions.purchase_discount')}</span>
                                                    <span>{formatCurrency(packet.discount)}</span>
                                                </div>
                                            )}
                                            {packet.message && (
                                                <div>
                                                    <div className="text-xs font-semibold uppercase text-muted-foreground">
                                                        {t('operations.customer_interactions.purchase_message')}
                                                    </div>
                                                    <div className="mt-1 whitespace-pre-wrap">{packet.message}</div>
                                                </div>
                                            )}
                                            {packet.error_message && (
                                                <div className="rounded-lg bg-amber-100/80 p-2 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                                    {packet.error_message}
                                                </div>
                                            )}
                                            {packet.reviewed_at && (
                                                <div className="rounded-lg bg-emerald-50 p-2 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                                                    {t('operations.customer_interactions.supplement_reviewed')}: {formatDateTime(packet.reviewed_at)}
                                                    {packet.reviewed_by ? ` · ${packet.reviewed_by}` : ''}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {pending && packet.can_review && (
                                        <div className="mt-4 flex flex-wrap justify-end gap-2 border-t pt-4">
                                            {packet.can_merge_original && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={busy}
                                                    onClick={() => resolve(packet, 'merge_original')}
                                                >
                                                    {busy ? <Loader2 className="size-4 animate-spin" /> : <Merge className="size-4" />}
                                                    {t('operations.customer_interactions.supplement_merge')}
                                                </Button>
                                            )}
                                            {packet.can_create_supplemental_order && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={busy}
                                                    onClick={() => resolve(packet, 'create_supplemental_order')}
                                                >
                                                    {busy ? <Loader2 className="size-4 animate-spin" /> : <FilePlus2 className="size-4" />}
                                                    {t('operations.customer_interactions.supplement_create_order')}
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={busy}
                                                onClick={() => resolve(packet, 'acknowledge')}
                                            >
                                                {busy ? <Loader2 className="size-4 animate-spin" /> : <CheckCircle2 className="size-4" />}
                                                {t('operations.customer_interactions.supplement_acknowledge')}
                                            </Button>
                                        </div>
                                    )}

                                    {pending && !packet.can_review && (
                                        <div className="mt-4 border-t pt-3 text-xs text-muted-foreground">
                                            {t('operations.customer_interactions.supplement_read_only')}
                                        </div>
                                    )}
                                </section>
                            );
                        })
                    ) : (
                        <div className="flex min-h-40 items-center justify-center text-muted-foreground">
                            {t('operations.customer_interactions.supplement_empty')}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
