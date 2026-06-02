import { useCallback, useEffect, useState } from 'react';
import { Circle, ExternalLink, Loader2, MapPin, Printer, RefreshCw, Truck, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiGet, apiPost } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

// ─── Tracking timeline ────────────────────────────────────────────────────────

function TrackingTimeline({ events }) {
    if (!events?.length) return null;

    return (
        <div>
            <p className="mb-3 text-sm font-semibold">Lộ trình vận chuyển</p>
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

// ─── Fee / action result ───────────────────────────────────────────────────────

function ActionResult({ title, data }) {
    if (!data) return null;

    return (
        <div className="rounded-lg border bg-muted/30 p-3 text-xs">
            <p className="mb-2 font-semibold">{title}</p>
            <pre className="max-h-40 overflow-auto whitespace-pre-wrap break-all text-muted-foreground">
                {JSON.stringify(data, null, 2)}
            </pre>
        </div>
    );
}

// ─── Main modal ───────────────────────────────────────────────────────────────

export function ShippingOrderDetailModal({ open, onOpenChange, orderId, apiBase }) {
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
                window.open(url, '_blank', 'noopener,noreferrer');
                toast.success('Đang mở nhãn…');
                return;
            }
            const data = await apiPost(path, { provider: selectedProvider });
            if (action === 'fee') {
                setFeeResult(data);
            } else {
                setDetail(data);
                if (data.activeProvider) setSelectedProvider(data.activeProvider);
            }
            toast.success(data.message ?? 'Thành công');
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

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Chi tiết vận chuyển — {order?.orderCode ?? '…'}</DialogTitle>
                    <DialogDescription>
                        Tạo vận đơn, đồng bộ trạng thái và theo dõi lộ trình giao hàng
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12 text-muted-foreground">
                        <Loader2 className="mr-2 size-5 animate-spin" />
                        Đang tải…
                    </div>
                ) : (
                    <div className="space-y-5">
                        {/* No carrier configured warning */}
                        {readyCarriers.length === 0 && (
                            <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10">
                                Chưa có đơn vị vận chuyển nào được bật — vào cấu hình đối tác để thiết lập.
                            </p>
                        )}

                        {/* Carrier selector tabs */}
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
                                        {!c.ready && ' (chưa bật)'}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Order info */}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="mb-1 font-semibold">Khách hàng</p>
                                <p className="font-medium">{order?.customerName}</p>
                                <p className="text-muted-foreground">{order?.customerPhone}</p>
                                <p className="mt-2 text-xs text-muted-foreground leading-relaxed">
                                    {order?.shippingAddress}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3 text-sm">
                                <p className="mb-1 font-semibold">Tài chính / COD</p>
                                <div className="space-y-0.5">
                                    <p>Tổng đơn: <span className="font-medium">{formatCurrency(order?.total)}</span></p>
                                    <p>Thu hộ (COD): <span className="font-medium">{formatCurrency(order?.amountToCollect)}</span></p>
                                    <p className="text-muted-foreground">Trạng thái: {order?.deliveryStatus}</p>
                                </div>
                            </div>
                        </div>

                        {/* Shipment card */}
                        <div className="rounded-lg border p-3">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p className="font-semibold">
                                    Vận đơn{' '}
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
                                        Tra cứu <ExternalLink className="size-3" />
                                    </a>
                                )}
                            </div>

                            {shipment ? (
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">Mã vận đơn</dt>
                                        <dd className="font-mono font-medium">
                                            {shipment.trackingNumber ?? '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">Trạng thái</dt>
                                        <dd
                                            className={cn(
                                                'font-medium',
                                                shipment.state === 'failed' && 'text-destructive',
                                                shipment.state === 'cancelled' && 'text-muted-foreground',
                                                shipment.state === 'submitted' && 'text-emerald-600',
                                            )}
                                        >
                                            {shipment.statusText ?? shipment.state}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">Phí vận chuyển</dt>
                                        <dd>{formatCurrency(shipment.fee)}</dd>
                                    </div>
                                    {shipment.submittedAt && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Tạo lúc</dt>
                                            <dd className="text-xs">{formatDateTime(shipment.submittedAt)}</dd>
                                        </div>
                                    )}
                                    {shipment.lastSyncedAt && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Đồng bộ lần cuối</dt>
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
                                <p className="text-sm text-muted-foreground">Chưa có vận đơn cho đơn vị này.</p>
                            )}
                        </div>

                        {/* Action buttons */}
                        <div className="flex flex-wrap gap-2">
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
                                Tạo vận đơn
                            </Button>

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
                                Đồng bộ
                            </Button>

                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!!acting || !selectedProvider}
                                onClick={() => runAction('fee', `${apiBase}/${orderId}/calculate-fee`)}
                            >
                                Tính phí
                            </Button>

                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!!acting || !shipment?.trackingNumber}
                                onClick={() =>
                                    runAction(
                                        'label',
                                        `${apiBase}/${orderId}/label?provider=${selectedProvider}`,
                                        { method: 'GET' },
                                    )
                                }
                            >
                                <Printer className="mr-1 size-4" />
                                In nhãn
                            </Button>

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
                                Hủy
                            </Button>
                        </div>

                        {/* Fee result */}
                        <ActionResult title="Kết quả tính phí" data={feeResult} />

                        {/* Tracking timeline */}
                        {tracking.length > 0 && <TrackingTimeline events={tracking} />}

                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
