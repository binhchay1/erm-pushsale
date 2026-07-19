import { useCallback, useEffect, useState } from 'react';
import { Circle, ExternalLink, Loader2, MapPin, Printer, RefreshCw, Truck, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { ShippingFeeResult } from '@/components/shipping/ShippingFeeResult';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { StatusBadge } from '@/components/ui/status-badge';
import { apiGet, apiPost } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';
import { deliveryTone, shipmentTone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function TrackingTimeline({ events }) {
    const t = useT();

    if (!events?.length) return null;

    return (
        <div>
            <p className="mb-3 text-sm font-semibold">{t('shipping.tracking_title')}</p>
            <ol className="relative border-l border-border pl-4 space-y-4">
                {[...events].reverse().map((ev, idx) => (
                    <li key={idx} className="relative">
                        <span
                            className={cn(
                                'absolute -left-[1.15rem] flex size-4 items-center justify-center rounded-full border',
                                ev.isCurrent
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground/40 bg-background',
                            )}
                        >
                            {ev.isCurrent ? (
                                <MapPin className="size-2.5" />
                            ) : (
                                <Circle className="size-2 fill-muted-foreground/30 text-muted-foreground/30" />
                            )}
                        </span>
                        <div className="pl-2">
                            <p
                                className={cn(
                                    'text-sm font-medium',
                                    ev.isCurrent ? 'text-primary' : 'text-foreground',
                                )}
                            >
                                {ev.statusText}
                            </p>
                            {ev.note && (
                                <p className="text-xs text-muted-foreground">{ev.note}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {formatDateTime(ev.at)}
                                {ev.provider && (
                                    <span className="ml-1 rounded bg-muted px-1 py-0.5 font-mono text-[10px] uppercase">
                                        {ev.provider}
                                    </span>
                                )}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}

export function ShippingOrderDetailDialog({ open, onOpenChange, orderId, apiBase }) {
    const t = useT();
    const [loading, setLoading] = useState(false);
    const [acting, setActing] = useState(null);
    const [detail, setDetail] = useState(null);
    const [feeResult, setFeeResult] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(null);

    const load = useCallback(async () => {
        if (!orderId) return;
        setLoading(true);
        try {
            const data = await apiGet(`${apiBase}/${orderId}/detail`);
            setDetail(data);
            setSelectedProvider(data.activeProvider ?? data.carriers?.find((c) => c.ready)?.provider ?? null);
        } catch (e) {
            toast.error(e.message);
        } finally {
            setLoading(false);
        }
    }, [apiBase, orderId]);

    useEffect(() => {
        if (open && orderId) {
            setFeeResult(null);
            load();
        }
    }, [open, orderId, load]);

    const runAction = async (action, path, options = {}) => {
        setActing(action);
        try {
            if (options.method === 'GET') {
                const url = `${path}${path.includes('?') ? '&' : '?'}provider=${selectedProvider}`;
                await openShippingLabel(url);
                toast.success(t('shipping.label_opened'));
                return;
            }
            const data = await apiPost(path, { provider: selectedProvider });
            if (action === 'fee') {
                setFeeResult(data);
            } else {
                setDetail(data);
                if (data.activeProvider) setSelectedProvider(data.activeProvider);
            }
            toast.success(data.message ?? t('shipping.success_default'));
        } catch (e) {
            toast.error(e.message);
        } finally {
            setActing(null);
        }
    };

    const order = detail?.order;
    const shipment =
        detail?.shipments?.find((s) => s.provider === selectedProvider) ?? detail?.shipment;
    const readyCarriers = detail?.carriers?.filter((c) => c.ready) ?? [];
    const tracking = detail?.tracking ?? [];

    const actions = resolveActions(shipment ?? null, order);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('shipping.detail_title_with_code', { code: order?.orderCode ?? '…' })}
                    </DialogTitle>
                    <DialogDescription>{t('shipping.detail_desc')}</DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12 text-muted-foreground">
                        <Loader2 className="mr-2 size-5 animate-spin" />
                        {t('shipping.loading')}
                    </div>
                ) : (
                    <div className="space-y-5">
                        {readyCarriers.length === 0 && (
                            <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10">
                                {t('shipping.no_carrier_enabled')}
                            </p>
                        )}

                        {detail?.carriers?.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {detail.carriers.map((c) => (
                                    <button
                                        key={c.provider}
                                        type="button"
                                        disabled={!c.ready}
                                        onClick={() => setSelectedProvider(c.provider)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                            selectedProvider === c.provider
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-card text-muted-foreground',
                                            !c.ready && 'cursor-not-allowed opacity-40',
                                        )}
                                    >
                                        {c.label}
                                        {!c.ready && ` ${t('shipping.not_enabled')}`}
                                    </button>
                                ))}
                            </div>
                        )}

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="mb-1 font-semibold">{t('shipping.customer')}</p>
                                <p className="font-medium">{order?.customerName}</p>
                                <p className="text-muted-foreground">{order?.customerPhone}</p>
                                <p className="mt-2 text-xs text-muted-foreground leading-relaxed">
                                    {order?.shippingAddress}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="mb-1 font-semibold">{t('shipping.finance_cod')}</p>
                                <div className="space-y-0.5">
                                    <p>
                                        {t('shipping.order_total')}:{' '}
                                        <span className="font-medium">{formatCurrency(order?.total)}</span>
                                    </p>
                                    <p>
                                        {t('shipping.cod_amount')}:{' '}
                                        <span className="font-medium">{formatCurrency(order?.amountToCollect)}</span>
                                    </p>
                                    <p className="text-muted-foreground">
                                        {t('shipping.status')}:{' '}
                                        <StatusBadge tone={deliveryTone(order?.deliveryStatusValue)} className="ml-1 align-middle">
                                            {order?.deliveryStatus}
                                        </StatusBadge>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg border p-3">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p className="font-semibold">
                                    {t('shipping.waybill')}{' '}
                                    {shipment?.providerLabel
                                        ? `— ${shipment.providerLabel}`
                                        : selectedProvider
                                          ? `— ${selectedProvider.toUpperCase()}`
                                          : ''}
                                </p>
                                {detail?.trackingUrl && (
                                    <a
                                        href={detail.trackingUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                    >
                                        {t('shipping.lookup')} <ExternalLink className="size-3" />
                                    </a>
                                )}
                            </div>

                            {shipment ? (
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">{t('shipping.waybill_code')}</dt>
                                        <dd className="font-mono font-medium">
                                            {shipment.trackingNumber ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">{t('shipping.status')}</dt>
                                        <dd>
                                            <StatusBadge tone={shipmentTone(shipment.state)}>
                                                {shipment.statusText ?? shipment.state}
                                            </StatusBadge>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">{t('shipping.shipping_fee')}</dt>
                                        <dd>{formatCurrency(shipment.fee)}</dd>
                                    </div>
                                    {shipment.submittedAt && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">{t('shipping.created_at')}</dt>
                                            <dd className="text-xs">{formatDateTime(shipment.submittedAt)}</dd>
                                        </div>
                                    )}
                                    {shipment.lastSyncedAt && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">{t('shipping.synced_at')}</dt>
                                            <dd className="text-xs">{formatDateTime(shipment.lastSyncedAt)}</dd>
                                        </div>
                                    )}
                                    {shipment.errorMessage && (
                                        <div className="sm:col-span-2 rounded border border-destructive/30 bg-destructive/5 px-2 py-1 text-xs text-destructive">
                                            {shipment.errorMessage}
                                        </div>
                                    )}
                                </dl>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('shipping.no_waybill_for_provider')}</p>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {actions.canCreate && (
                                <Button
                                    size="sm"
                                    disabled={!!acting || !selectedProvider}
                                    onClick={() => runAction('create', `${apiBase}/${orderId}/create-shipment`)}
                                >
                                    {acting === 'create' ? (
                                        <Loader2 className="mr-1 size-4 animate-spin" />
                                    ) : (
                                        <Truck className="mr-1 size-4" />
                                    )}
                                    {t('shipping.create_waybill')}
                                </Button>
                            )}

                            {actions.canSync && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!!acting || !shipment}
                                    onClick={() => runAction('sync', `${apiBase}/${orderId}/sync-status`)}
                                >
                                    {acting === 'sync' ? (
                                        <Loader2 className="mr-1 size-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="mr-1 size-4" />
                                    )}
                                    {t('shipping.sync')}
                                </Button>
                            )}

                            {actions.canCalculateFee && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!!acting || !selectedProvider}
                                    onClick={() => runAction('fee', `${apiBase}/${orderId}/calculate-fee`)}
                                >
                                    {t('shipping.calc_fee')}
                                </Button>
                            )}

                            {actions.canPrintLabel && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!!acting || !shipment?.trackingNumber}
                                    onClick={() =>
                                        runAction('label', `${apiBase}/${orderId}/label`, {
                                            method: 'GET',
                                        })
                                    }
                                >
                                    <Printer className="mr-1 size-4" />
                                    {t('shipping.print_label')}
                                </Button>
                            )}

                            {actions.canCancel && (
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    disabled={!!acting || !shipment}
                                    onClick={() => runAction('cancel', `${apiBase}/${orderId}/cancel-shipment`)}
                                >
                                    {acting === 'cancel' ? (
                                        <Loader2 className="mr-1 size-4 animate-spin" />
                                    ) : (
                                        <XCircle className="mr-1 size-4" />
                                    )}
                                    {t('shipping.cancel_waybill')}
                                </Button>
                            )}

                            {!actions.canCreate &&
                                !actions.canSync &&
                                !actions.canCalculateFee &&
                                !actions.canPrintLabel &&
                                !actions.canCancel && (
                                    <p className="text-sm text-muted-foreground">
                                        {t('shipping.no_shipping_actions')}
                                    </p>
                                )}
                        </div>

                        <ShippingFeeResult display={feeResult?.display} />

                        {tracking.length > 0 && <TrackingTimeline events={tracking} />}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** Mirror ShipmentActionResolver (PHP) when switching carrier tab. */
function resolveActions(shipment, order) {
    const status = order?.deliveryStatusValue;
    const orderFinal = [
        'delivered', 'paid', 'returned', 'returning', 'refund',
        'cancel_closing', 'cannot_deliver',
    ].includes(status);
    const deliveryTerminal = orderFinal || status === 'cancel_waybill';

    if (!shipment) {
        return {
            canCreate: !orderFinal,
            canSync: false,
            canCalculateFee: !orderFinal,
            canPrintLabel: false,
            canCancel: false,
        };
    }

    const hasTracking = Boolean(shipment.trackingNumber);
    const state = shipment.state;
    const isSubmitted = state === 'submitted';
    const isRetryable = state === 'failed' || state === 'cancelled';

    const hasActiveWaybill = hasTracking && isSubmitted;

    return {
        canCreate: (!hasTracking || isRetryable) && !orderFinal,
        canSync: hasActiveWaybill,
        canCalculateFee: !hasActiveWaybill && !orderFinal,
        canPrintLabel: hasActiveWaybill,
        canCancel: hasActiveWaybill && !deliveryTerminal,
    };
}
