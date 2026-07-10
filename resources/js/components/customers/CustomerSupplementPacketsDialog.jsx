import { router } from '@inertiajs/react';
import {
    AlertCircle,
    BadgeCheck,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    ClipboardCheck,
    FilePlus2,
    Info,
    Loader2,
    Merge,
    PackagePlus,
    Phone,
    ReceiptText,
    RefreshCw,
    ShieldAlert,
    ShoppingBag,
    TriangleAlert,
    UserRound,
    UsersRound,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { StatusBadge } from '@/components/ui/status-badge';
import { apiGet, apiPost } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

const FILTERS = ['pending', 'reviewed', 'all'];

const ACTION_META = {
    merge_original: {
        icon: Merge,
        tone: 'info',
        buttonVariant: 'default',
        titleKey: 'supplement_decision_merge_title',
        descriptionKey: 'supplement_decision_merge_desc',
        confirmKey: 'supplement_decision_merge_confirm',
    },
    create_supplemental_order: {
        icon: FilePlus2,
        tone: 'purple',
        buttonVariant: 'outline',
        titleKey: 'supplement_decision_create_title',
        descriptionKey: 'supplement_decision_create_desc',
        confirmKey: 'supplement_decision_create_confirm',
    },
    acknowledge: {
        icon: ClipboardCheck,
        tone: 'warning',
        buttonVariant: 'outline',
        titleKey: 'supplement_decision_ack_title',
        descriptionKey: 'supplement_decision_ack_desc',
        confirmKey: 'supplement_decision_ack_confirm',
    },
};

function SummaryMetric({ icon: Icon, label, value, helper }) {
    return (
        <div className="rounded-xl border bg-background/80 p-3 shadow-sm">
            <div className="flex items-start gap-3">
                <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <div className="text-xs font-medium text-muted-foreground">{label}</div>
                    <div className="mt-0.5 truncate text-base font-semibold text-foreground">{value}</div>
                    {helper && <div className="mt-0.5 truncate text-[11px] text-muted-foreground">{helper}</div>}
                </div>
            </div>
        </div>
    );
}

function PacketItems({ items = [], compact = false, t }) {
    if (!items.length) {
        return (
            <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                {t('operations.customer_interactions.supplement_no_items')}
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border bg-background">
            <div className="min-w-[560px]">
                <div className="grid grid-cols-[minmax(0,1fr)_56px_104px_112px] gap-2 border-b bg-muted/45 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    <div>{t('operations.customer_interactions.supplement_product')}</div>
                    <div className="text-center">{t('operations.customer_interactions.supplement_quantity')}</div>
                    <div className="text-right">{t('operations.customer_interactions.supplement_unit_price')}</div>
                    <div className="text-right">{t('operations.customer_interactions.supplement_line_total')}</div>
                </div>
                {items.map((item, index) => (
                    <div
                        key={`${item.name}-${index}`}
                        className={cn(
                            'grid grid-cols-[minmax(0,1fr)_56px_104px_112px] items-center gap-2 border-b px-3 last:border-b-0',
                            compact ? 'py-2 text-xs' : 'py-2.5 text-sm',
                        )}
                    >
                        <div className="min-w-0">
                            <div className="truncate font-medium" title={item.name}>{item.name}</div>
                            <div className="mt-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">
                                {item.item_type ?? 'upsell'}
                            </div>
                        </div>
                        <div className="text-center text-muted-foreground">x{item.quantity}</div>
                        <div className="text-right text-muted-foreground">{formatCurrency(item.unit_price)}</div>
                        <div className="text-right font-semibold">{formatCurrency(item.line_total)}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function AmountSummary({ packet, t, compact = false }) {
    return (
        <div className={cn('rounded-xl border bg-muted/20', compact ? 'p-3 text-xs' : 'p-4 text-sm')}>
            <div className="flex items-center justify-between gap-3 text-muted-foreground">
                <span>{t('operations.customer_interactions.purchase_subtotal')}</span>
                <span>{formatCurrency(packet.subtotal)}</span>
            </div>
            {Number(packet.discount) > 0 && (
                <div className="mt-1.5 flex items-center justify-between gap-3 text-muted-foreground">
                    <span>{t('operations.customer_interactions.purchase_discount')}</span>
                    <span>-{formatCurrency(packet.discount)}</span>
                </div>
            )}
            <div className="mt-2 flex items-center justify-between gap-3 border-t pt-2 font-semibold text-foreground">
                <span>{t('operations.customer_interactions.supplement_packet_value')}</span>
                <span>{formatCurrency(packet.total)}</span>
            </div>
        </div>
    );
}

function MergeAvailability({ packet, orderSummary, t }) {
    if (!packet.can_review) return null;

    if (packet.can_merge_original) {
        return (
            <div className="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200">
                <BadgeCheck className="mt-0.5 size-4 shrink-0" />
                <div>
                    <div className="font-semibold">{t('operations.customer_interactions.supplement_merge_available')}</div>
                    <div className="mt-0.5 opacity-90">
                        {t('operations.customer_interactions.supplement_merge_available_desc', {
                            code: orderSummary?.order_code ?? packet.related_order_code ?? '—',
                        })}
                    </div>
                </div>
            </div>
        );
    }

    const reasonKey = packet.merge_block_reason
        ? `supplement_merge_block_${packet.merge_block_reason}`
        : 'supplement_merge_block_unknown';

    return (
        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200">
            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
            <div>
                <div className="font-semibold">{t('operations.customer_interactions.supplement_merge_unavailable')}</div>
                <div className="mt-0.5">{t(`operations.customer_interactions.${reasonKey}`)}</div>
            </div>
        </div>
    );
}

function ReviewResult({ packet, t }) {
    if (!packet.reviewed_at) return null;

    const resolutionKey = packet.review_resolution
        ? `supplement_resolution_${packet.review_resolution}`
        : 'supplement_resolution_unknown';

    return (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
            <div className="flex items-start gap-3">
                <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                    <CheckCircle2 className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="font-semibold text-emerald-900 dark:text-emerald-200">
                        {t(`operations.customer_interactions.${resolutionKey}`)}
                    </div>
                    <div className="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                        {formatDateTime(packet.reviewed_at)}
                        {packet.reviewed_by ? ` · ${packet.reviewed_by}` : ''}
                    </div>
                    {packet.review_note && (
                        <div className="mt-2 whitespace-pre-wrap rounded-lg bg-background/70 p-2.5 text-sm text-foreground">
                            {packet.review_note}
                        </div>
                    )}
                    {packet.review_resolution === 'create_supplemental_order' && packet.order_code && (
                        <div className="mt-2 text-xs text-emerald-900 dark:text-emerald-200">
                            {t('operations.customer_interactions.supplement_created_order_code')}: <strong>{packet.order_code}</strong>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function DecisionDialog({ state, orderSummary, note, setNote, submitting, onClose, onConfirm, t }) {
    const packet = state?.packet;
    const resolution = state?.resolution;
    const meta = resolution ? ACTION_META[resolution] : null;
    const requiresNote = resolution === 'acknowledge';
    const noteMissing = requiresNote && !note.trim();

    if (!packet || !meta) return null;

    const Icon = meta.icon;
    const originalCode = orderSummary?.order_code ?? packet.related_order_code ?? '—';

    return (
        <Dialog open={Boolean(state)} onOpenChange={(next) => !next && !submitting && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-[min(720px,calc(100vw-2rem))] overflow-hidden p-0" showClose={!submitting}>
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <div className="flex items-start gap-3">
                        <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground">
                            <Icon className="size-5" />
                        </span>
                        <div className="min-w-0">
                            <DialogTitle>{t(`operations.customer_interactions.${meta.titleKey}`)}</DialogTitle>
                            <DialogDescription className="mt-1 leading-relaxed">
                                {t(`operations.customer_interactions.${meta.descriptionKey}`, { code: originalCode })}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="max-h-[calc(90vh-170px)] space-y-4 overflow-y-auto px-6 py-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <SummaryMetric
                            icon={ReceiptText}
                            label={t('operations.customer_interactions.supplement_original_order')}
                            value={originalCode}
                        />
                        <SummaryMetric
                            icon={ShoppingBag}
                            label={t('operations.customer_interactions.supplement_item_quantity')}
                            value={packet.item_quantity ?? 0}
                            helper={t('operations.customer_interactions.supplement_item_lines', { count: packet.item_lines ?? 0 })}
                        />
                        <SummaryMetric
                            icon={CircleDollarSign}
                            label={t('operations.customer_interactions.supplement_packet_value')}
                            value={formatCurrency(packet.total)}
                        />
                    </div>

                    <PacketItems items={packet.items} compact t={t} />

                    <div className="rounded-xl border bg-muted/25 p-4">
                        <div className="flex items-start gap-2">
                            <Info className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <div className="text-sm leading-relaxed">
                                <div className="font-semibold">
                                    {t('operations.customer_interactions.supplement_decision_effect_title')}
                                </div>
                                <div className="mt-1 text-muted-foreground">
                                    {t(`operations.customer_interactions.supplement_decision_effect_${resolution}`, {
                                        code: originalCode,
                                        sale: orderSummary?.sale_name ?? '—',
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <label htmlFor="supplement-review-note" className="text-sm font-semibold">
                                {t('operations.customer_interactions.supplement_review_note')}
                                {requiresNote && <span className="ml-1 text-destructive">*</span>}
                            </label>
                            <span className="text-[11px] text-muted-foreground">{note.length}/1000</span>
                        </div>
                        <textarea
                            id="supplement-review-note"
                            value={note}
                            onChange={(event) => setNote(event.target.value.slice(0, 1000))}
                            placeholder={t(`operations.customer_interactions.supplement_review_note_placeholder_${resolution}`)}
                            disabled={submitting}
                            rows={4}
                            className={cn(
                                'w-full resize-y rounded-xl border bg-background px-3 py-2.5 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-60',
                                noteMissing && 'border-destructive/60',
                            )}
                        />
                        {noteMissing && (
                            <div className="mt-1.5 flex items-center gap-1.5 text-xs text-destructive">
                                <AlertCircle className="size-3.5" />
                                {t('operations.customer_interactions.supplement_review_note_required')}
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter className="border-t bg-muted/20 px-6 py-4">
                    <Button type="button" variant="outline" disabled={submitting} onClick={onClose}>
                        {t('operations.customer_interactions.supplement_cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant={meta.buttonVariant}
                        disabled={submitting || noteMissing}
                        onClick={onConfirm}
                    >
                        {submitting ? <Loader2 className="size-4 animate-spin" /> : <Icon className="size-4" />}
                        {t(`operations.customer_interactions.${meta.confirmKey}`)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PacketCard({ packet, orderSummary, busy, onAction, t }) {
    const pending = packet.requires_review && !packet.reviewed_at;

    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border bg-card shadow-sm transition',
                pending && 'border-amber-300/80 shadow-amber-100/50 dark:border-amber-900/70 dark:shadow-none',
            )}
        >
            <div className={cn('border-b px-5 py-4', pending ? 'bg-amber-50/60 dark:bg-amber-950/15' : 'bg-muted/20')}>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge tone={pending ? 'warning' : 'success'} icon={pending ? TriangleAlert : CheckCircle2}>
                                {pending
                                    ? t('operations.customer_interactions.supplement_pending')
                                    : t('operations.customer_interactions.supplement_reviewed_status')}
                            </StatusBadge>
                            <StatusBadge tone="info">{packet.packet_type_label ?? packet.packet_type}</StatusBadge>
                            <StatusBadge tone="muted">{packet.status_label ?? packet.status}</StatusBadge>
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span className="inline-flex items-center gap-1">
                                <CalendarClock className="size-3.5" />
                                {t('operations.customer_interactions.supplement_received_at')}: {formatDateTime(packet.created_at)}
                            </span>
                            {packet.external_id && (
                                <span className="max-w-[420px] truncate font-mono" title={packet.external_id}>
                                    #{packet.external_id}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-xs text-muted-foreground">
                            {t('operations.customer_interactions.supplement_packet_value')}
                        </div>
                        <div className="mt-0.5 text-lg font-bold">{formatCurrency(packet.total)}</div>
                        <div className="mt-0.5 text-[11px] text-muted-foreground">
                            {t('operations.customer_interactions.supplement_item_summary', {
                                quantity: packet.item_quantity ?? 0,
                                lines: packet.item_lines ?? 0,
                            })}
                        </div>
                    </div>
                </div>
            </div>

            <div className="space-y-4 p-5">
                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <PacketItems items={packet.items} t={t} />
                    <div className="space-y-3">
                        <AmountSummary packet={packet} t={t} />
                        <div className="rounded-xl border bg-muted/15 p-3 text-xs">
                            <div className="flex items-center gap-2 font-semibold">
                                <UserRound className="size-3.5 text-muted-foreground" />
                                {packet.customer_name || '—'}
                            </div>
                            <div className="mt-1.5 flex items-center gap-2 text-muted-foreground">
                                <Phone className="size-3.5" />
                                {packet.customer_phone || '—'}
                            </div>
                            <div className="mt-1.5 flex items-center gap-2 text-muted-foreground">
                                <ReceiptText className="size-3.5" />
                                {t('operations.customer_interactions.supplement_original_order')}: {packet.related_order_code ?? orderSummary?.order_code ?? '—'}
                            </div>
                            {packet.order_code && packet.order_code !== packet.related_order_code && (
                                <div className="mt-1.5 flex items-center gap-2 text-muted-foreground">
                                    <FilePlus2 className="size-3.5" />
                                    {t('operations.customer_interactions.supplement_resolved_order')}: {packet.order_code}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {(packet.message || packet.error_message) && (
                    <div className="grid gap-3 lg:grid-cols-2">
                        {packet.message && (
                            <div className="rounded-xl border bg-background p-3">
                                <div className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    {t('operations.customer_interactions.purchase_message')}
                                </div>
                                <div className="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed">{packet.message}</div>
                            </div>
                        )}
                        {packet.error_message && (
                            <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                                <div className="flex items-start gap-2">
                                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300" />
                                    <div>
                                        <div className="text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                                            {t('operations.customer_interactions.supplement_reason')}
                                        </div>
                                        <div className="mt-1 text-sm leading-relaxed text-amber-900 dark:text-amber-100">
                                            {packet.error_message}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {pending && <MergeAvailability packet={packet} orderSummary={orderSummary} t={t} />}
                <ReviewResult packet={packet} t={t} />

                {pending && packet.can_review && (
                    <div className="rounded-xl border bg-muted/15 p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="max-w-xl">
                                <div className="font-semibold">{t('operations.customer_interactions.supplement_choose_action')}</div>
                                <div className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                    {t('operations.customer_interactions.supplement_choose_action_desc')}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {packet.can_merge_original && (
                                    <Button disabled={busy} onClick={() => onAction(packet, 'merge_original')}>
                                        <Merge className="size-4" />
                                        {t('operations.customer_interactions.supplement_merge')}
                                    </Button>
                                )}
                                {packet.can_create_supplemental_order && (
                                    <Button variant="outline" disabled={busy} onClick={() => onAction(packet, 'create_supplemental_order')}>
                                        <FilePlus2 className="size-4" />
                                        {t('operations.customer_interactions.supplement_create_order')}
                                    </Button>
                                )}
                                <Button variant="ghost" disabled={busy} onClick={() => onAction(packet, 'acknowledge')}>
                                    <ClipboardCheck className="size-4" />
                                    {t('operations.customer_interactions.supplement_acknowledge')}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {pending && !packet.can_review && (
                    <div className="flex items-start gap-2 rounded-xl border bg-muted/30 p-3 text-xs text-muted-foreground">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        {t('operations.customer_interactions.supplement_read_only')}
                    </div>
                )}

                {pending && packet.can_review && !packet.has_actionable_content && (
                    <div className="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50/60 p-3 text-xs text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-200">
                        <XCircle className="mt-0.5 size-4 shrink-0" />
                        {t('operations.customer_interactions.supplement_no_actionable_content')}
                    </div>
                )}
            </div>
        </section>
    );
}

export function CustomerSupplementPacketsDialog({ order, count = 0 }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [submittingId, setSubmittingId] = useState(null);
    const [packets, setPackets] = useState([]);
    const [orderSummary, setOrderSummary] = useState(null);
    const [summary, setSummary] = useState(null);
    const [pendingCount, setPendingCount] = useState(count);
    const [activeFilter, setActiveFilter] = useState(count > 0 ? 'pending' : 'all');
    const [decision, setDecision] = useState(null);
    const [note, setNote] = useState('');

    const load = useCallback(async ({ silent = false } = {}) => {
        if (silent) setRefreshing(true);
        else setLoading(true);

        try {
            const data = await apiGet(`/customers/orders/${order.id}/supplement-packets`);
            setPackets(data.packets ?? []);
            setOrderSummary(data.order ?? null);
            setSummary(data.summary ?? null);
            setPendingCount(Number(data.pending_count ?? 0));
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
        } finally {
            if (silent) setRefreshing(false);
            else setLoading(false);
        }
    }, [order.id, t]);

    useEffect(() => {
        setPendingCount(count);
        if (count > 0) setActiveFilter('pending');
    }, [count]);

    useEffect(() => {
        if (!open) return;
        load();
    }, [load, open]);

    const filteredPackets = useMemo(() => {
        if (activeFilter === 'pending') {
            return packets.filter((packet) => packet.requires_review && !packet.reviewed_at);
        }
        if (activeFilter === 'reviewed') {
            return packets.filter((packet) => Boolean(packet.reviewed_at));
        }
        return packets;
    }, [activeFilter, packets]);

    const tabCounts = useMemo(() => ({
        pending: packets.filter((packet) => packet.requires_review && !packet.reviewed_at).length,
        reviewed: packets.filter((packet) => Boolean(packet.reviewed_at)).length,
        all: packets.length,
    }), [packets]);

    const openDecision = (packet, resolution) => {
        setNote('');
        setDecision({ packet, resolution });
    };

    const closeDecision = () => {
        if (submittingId) return;
        setDecision(null);
        setNote('');
    };

    const resolve = async () => {
        if (!decision) return;
        if (decision.resolution === 'acknowledge' && !note.trim()) return;

        const packet = decision.packet;
        setSubmittingId(packet.id);

        try {
            const data = await apiPost(
                `/customers/orders/${order.id}/supplement-packets/${packet.id}/review`,
                { resolution: decision.resolution, note: note.trim() || null },
            );

            setPackets((current) => current.map((row) => (row.id === packet.id ? data.packet : row)));
            if (data.order) setOrderSummary(data.order);
            if (data.pending_count != null) setPendingCount(Number(data.pending_count));
            setDecision(null);
            setNote('');
            toast.success(data.message ?? t('operations.customer_interactions.supplement_review_success'));

            await load({ silent: true });
            router.reload({ only: ['report'], preserveScroll: true, preserveState: true });
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.supplement_review_failed'));
        } finally {
            setSubmittingId(null);
        }
    };

    const effectiveOrder = orderSummary ?? {
        order_code: order.orderCode,
        customer_name: order.customerName,
        customer_phone: order.customerPhone,
        sale_name: order.saleName,
        team_name: order.teamName,
        total: order.total,
    };

    return (
        <>
            <Dialog open={open} onOpenChange={(next) => !submittingId && setOpen(next)}>
                <DialogTrigger asChild>
                    <button
                        type="button"
                        className="group mt-1 inline-flex min-h-7 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-800 shadow-sm transition hover:border-amber-400 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-amber-300/40 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/70"
                        title={t('operations.order_table.pending_supplement_hint')}
                    >
                        <span className="relative inline-flex size-4 items-center justify-center rounded-full bg-amber-200/70 dark:bg-amber-900/70">
                            <TriangleAlert className="size-3" />
                        </span>
                        {t('operations.order_table.pending_supplement', { count: pendingCount })}
                        <ChevronRight className="size-3 transition-transform group-hover:translate-x-0.5" />
                    </button>
                </DialogTrigger>

                <DialogContent className="max-h-[94vh] max-w-[min(1180px,calc(100vw-1.5rem))] overflow-hidden p-0">
                    <DialogHeader className="border-b bg-muted/15 px-6 py-5 pr-14">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
                                    <PackagePlus className="size-5" />
                                </span>
                                <div>
                                    <DialogTitle className="text-xl">
                                        {t('operations.customer_interactions.supplement_title')}
                                    </DialogTitle>
                                    <DialogDescription className="mt-1 max-w-2xl leading-relaxed">
                                        {t('operations.customer_interactions.supplement_subtitle')}
                                    </DialogDescription>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 pr-6">
                                {pendingCount > 0 ? (
                                    <StatusBadge tone="warning" icon={TriangleAlert}>
                                        {t('operations.customer_interactions.supplement_pending_count', { count: pendingCount })}
                                    </StatusBadge>
                                ) : (
                                    <StatusBadge tone="success" icon={CheckCircle2}>
                                        {t('operations.customer_interactions.supplement_all_resolved')}
                                    </StatusBadge>
                                )}
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    title={t('operations.customer_interactions.supplement_refresh')}
                                    disabled={loading || refreshing}
                                    onClick={() => load({ silent: true })}
                                >
                                    <RefreshCw className={cn('size-4', refreshing && 'animate-spin')} />
                                </Button>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="max-h-[calc(94vh-118px)] overflow-y-auto">
                        {loading ? (
                            <div className="flex min-h-[420px] items-center justify-center text-muted-foreground">
                                <Loader2 className="mr-2 size-5 animate-spin" />
                                {t('operations.customer_interactions.loading')}
                            </div>
                        ) : (
                            <div className="space-y-5 p-5 sm:p-6">
                                <div className="rounded-2xl border bg-card p-4 shadow-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                                        <div>
                                            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('operations.customer_interactions.supplement_original_order')}
                                            </div>
                                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-lg font-bold">{effectiveOrder.order_code ?? '—'}</span>
                                                <StatusBadge tone={effectiveOrder.can_accept_merge === false ? 'warning' : 'success'}>
                                                    {effectiveOrder.closing_status_label ?? t('operations.customer_interactions.supplement_order_open')}
                                                </StatusBadge>
                                                {effectiveOrder.delivery_status_label && (
                                                    <StatusBadge tone="muted">{effectiveOrder.delivery_status_label}</StatusBadge>
                                                )}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-xs text-muted-foreground">
                                                {t('operations.customer_interactions.supplement_current_order_value')}
                                            </div>
                                            <div className="mt-0.5 text-xl font-bold">{formatCurrency(effectiveOrder.total)}</div>
                                        </div>
                                    </div>

                                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <SummaryMetric
                                            icon={UserRound}
                                            label={t('operations.customer_interactions.purchase_customer')}
                                            value={effectiveOrder.customer_name ?? '—'}
                                            helper={effectiveOrder.customer_phone ?? '—'}
                                        />
                                        <SummaryMetric
                                            icon={UsersRound}
                                            label={t('operations.customer_interactions.purchase_sale')}
                                            value={effectiveOrder.sale_name ?? '—'}
                                            helper={effectiveOrder.team_name ?? undefined}
                                        />
                                        <SummaryMetric
                                            icon={ShoppingBag}
                                            label={t('operations.customer_interactions.supplement_current_items')}
                                            value={effectiveOrder.item_quantity ?? order.products?.reduce((sum, item) => sum + Number(item.quantity || 0), 0) ?? 0}
                                            helper={t('operations.customer_interactions.supplement_item_lines', {
                                                count: effectiveOrder.item_lines ?? order.products?.length ?? 0,
                                            })}
                                        />
                                        <SummaryMetric
                                            icon={CircleDollarSign}
                                            label={t('operations.customer_interactions.supplement_pending_value')}
                                            value={formatCurrency(summary?.pending_value ?? 0)}
                                            helper={t('operations.customer_interactions.supplement_pending_count', {
                                                count: summary?.pending_count ?? pendingCount,
                                            })}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 rounded-xl border bg-muted/15 p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex flex-wrap gap-2">
                                        {FILTERS.map((filter) => (
                                            <button
                                                key={filter}
                                                type="button"
                                                onClick={() => setActiveFilter(filter)}
                                                className={cn(
                                                    'inline-flex h-8 items-center gap-2 rounded-lg border px-3 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/30',
                                                    activeFilter === filter
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'bg-background hover:bg-muted',
                                                )}
                                            >
                                                {t(`operations.customer_interactions.supplement_filter_${filter}`)}
                                                <span className={cn(
                                                    'rounded-full px-1.5 py-0.5 text-[10px]',
                                                    activeFilter === filter ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground',
                                                )}>
                                                    {tabCounts[filter]}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {t('operations.customer_interactions.supplement_showing', {
                                            shown: filteredPackets.length,
                                            total: packets.length,
                                        })}
                                    </div>
                                </div>

                                {filteredPackets.length ? (
                                    <div className="space-y-4">
                                        {filteredPackets.map((packet) => (
                                            <PacketCard
                                                key={packet.id}
                                                packet={packet}
                                                orderSummary={effectiveOrder}
                                                busy={submittingId === packet.id}
                                                onAction={openDecision}
                                                t={t}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="flex min-h-52 flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/10 px-6 text-center">
                                        <span className="inline-flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                            {activeFilter === 'pending' ? <CheckCircle2 className="size-5" /> : <ShoppingBag className="size-5" />}
                                        </span>
                                        <div className="mt-3 font-semibold">
                                            {activeFilter === 'pending'
                                                ? t('operations.customer_interactions.supplement_no_pending')
                                                : t('operations.customer_interactions.supplement_empty')}
                                        </div>
                                        <div className="mt-1 max-w-md text-sm text-muted-foreground">
                                            {activeFilter === 'pending'
                                                ? t('operations.customer_interactions.supplement_no_pending_desc')
                                                : t('operations.customer_interactions.supplement_empty_desc')}
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-start gap-2 rounded-xl border bg-muted/20 p-3 text-xs leading-relaxed text-muted-foreground">
                                    <Info className="mt-0.5 size-4 shrink-0" />
                                    {t('operations.customer_interactions.supplement_audit_notice')}
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <DecisionDialog
                state={decision}
                orderSummary={effectiveOrder}
                note={note}
                setNote={setNote}
                submitting={Boolean(submittingId)}
                onClose={closeDecision}
                onConfirm={resolve}
                t={t}
            />
        </>
    );
}
