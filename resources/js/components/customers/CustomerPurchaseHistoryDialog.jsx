import {
    ChevronDown,
    ChevronUp,
    Loader2,
    PackageSearch,
    ShoppingBag,
    Truck,
    UserRound,
    WalletCards,
} from 'lucide-react';
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
import { StatusBadge } from '@/components/ui/status-badge';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { deliveryTone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function SummaryCard({ icon: Icon, label, value, hint }) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-start gap-3">
                <div className="rounded-lg bg-[#3782dc]/10 p-2 text-[#3782dc]">
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</div>
                    <div className="mt-1 text-xl font-bold text-foreground">{value}</div>
                    {hint && <div className="mt-1 text-xs text-muted-foreground">{hint}</div>}
                </div>
            </div>
        </div>
    );
}

function DetailRow({ label, children, className }) {
    return (
        <div className={cn('grid grid-cols-[140px_1fr] gap-3 border-b py-2.5 text-sm last:border-b-0', className)}>
            <div className="font-medium text-muted-foreground">{label}</div>
            <div className="min-w-0 whitespace-pre-wrap break-words text-foreground">{children || '—'}</div>
        </div>
    );
}

function PurchaseOrderCard({ item, expanded, onToggle, t }) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-sm',
                item.isSelected && 'border-[#3782dc] ring-1 ring-[#3782dc]/25',
            )}
        >
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-start justify-between gap-4 px-5 py-4 text-left transition hover:bg-muted/40"
            >
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-base font-bold text-[#2467b5]">{item.orderCode}</span>
                        {item.isSelected && (
                            <span className="rounded-full bg-[#3782dc]/10 px-2 py-0.5 text-[11px] font-semibold text-[#2467b5]">
                                {t('operations.customer_interactions.purchase_selected_order')}
                            </span>
                        )}
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                item.closingStatus === 'closed'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                            )}
                        >
                            {item.closingStatusLabel || '—'}
                        </span>
                    </div>
                    <div className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2 xl:grid-cols-4">
                        <span>{t('operations.customer_interactions.purchase_data_at')}: {formatDateTime(item.dataArrivedAt)}</span>
                        <span>{t('operations.customer_interactions.purchase_sale')}: {item.saleName || '—'}</span>
                        <span>{t('operations.customer_interactions.purchase_products')}: {formatNumber(item.products?.reduce((sum, product) => sum + Number(product.quantity || 0), 0))}</span>
                        <span className="font-semibold text-foreground">{t('operations.customer_interactions.purchase_total')}: {formatCurrency(item.total)}</span>
                    </div>
                </div>
                {expanded ? <ChevronUp className="mt-1 size-5 shrink-0" /> : <ChevronDown className="mt-1 size-5 shrink-0" />}
            </button>

            {expanded && (
                <div className="border-t bg-muted/10 p-5">
                    <div className="grid gap-5 xl:grid-cols-[1.1fr_1fr_1fr]">
                        <div className="rounded-lg border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2 font-semibold">
                                <UserRound className="size-4 text-[#3782dc]" />
                                {t('operations.customer_interactions.purchase_customer_info')}
                            </div>
                            <DetailRow label={t('operations.customer_interactions.purchase_customer')}>{item.customerName}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_phone')}>{item.customerPhone}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_address')}>{item.address}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_receiver')}>
                                {[item.receiverName, item.receiverPhone].filter(Boolean).join(' · ')}
                            </DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_message')}>{item.customerNote}</DetailRow>
                        </div>

                        <div className="rounded-lg border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2 font-semibold">
                                <PackageSearch className="size-4 text-[#3782dc]" />
                                {t('operations.customer_interactions.purchase_operation_info')}
                            </div>
                            <DetailRow label={t('operations.customer_interactions.purchase_source')}>{item.sourceName}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_sale')}>
                                {[item.saleName, item.teamName].filter(Boolean).join(' · ')}
                            </DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_assigned_at')}>{formatDateTime(item.assignedAt)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_operation')}>{item.operationStage}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_result')}>{item.operationResult}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_next_at')}>{formatDateTime(item.nextOperationAt)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_closed_at')}>{formatDateTime(item.closedAt)}</DetailRow>
                        </div>

                        <div className="rounded-lg border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2 font-semibold">
                                <Truck className="size-4 text-[#3782dc]" />
                                {t('operations.customer_interactions.purchase_shipping_info')}
                            </div>
                            <DetailRow label={t('operations.customer_interactions.purchase_warehouse')}>{item.warehouseName}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_delivery_status')}>
                                <StatusBadge tone={deliveryTone(item.deliveryStatusValue)}>{item.deliveryStatus || '—'}</StatusBadge>
                            </DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_carrier')}>
                                {[item.shippingProvider, item.carrierName, item.shippingMethod].filter(Boolean).join(' · ')}
                            </DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_tracking')}>{item.trackingNumber}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_desired_at')}>{formatDateTime(item.desiredDeliveryAt)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_shipping_note')}>{item.shippingNotes}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_return_reason')}>{item.returnReason}</DetailRow>
                        </div>
                    </div>

                    <div className="mt-5 overflow-hidden rounded-lg border bg-background">
                        <div className="flex items-center gap-2 border-b bg-muted/40 px-4 py-3 font-semibold">
                            <ShoppingBag className="size-4 text-[#3782dc]" />
                            {t('operations.customer_interactions.purchase_product_detail')}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-[760px] w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th className="bg-[#3782dc] px-3 py-3 text-left font-semibold text-white">#</th>
                                        <th className="bg-[#3782dc] px-3 py-3 text-left font-semibold text-white">{t('operations.customer_interactions.purchase_product_name')}</th>
                                        <th className="bg-[#3782dc] px-3 py-3 text-center font-semibold text-white">{t('operations.customer_interactions.purchase_quantity')}</th>
                                        <th className="bg-[#3782dc] px-3 py-3 text-right font-semibold text-white">{t('operations.customer_interactions.purchase_unit_price')}</th>
                                        <th className="bg-[#3782dc] px-3 py-3 text-right font-semibold text-white">{t('operations.customer_interactions.purchase_discount')}</th>
                                        <th className="bg-[#3782dc] px-3 py-3 text-right font-semibold text-white">{t('operations.customer_interactions.purchase_line_total')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {item.products?.length ? item.products.map((product, index) => (
                                        <tr key={product.id ?? `${item.id}-${index}`} className="border-b last:border-b-0">
                                            <td className="px-3 py-3 text-muted-foreground">{index + 1}</td>
                                            <td className="px-3 py-3 font-medium">{product.name}</td>
                                            <td className="px-3 py-3 text-center">{formatNumber(product.quantity)}</td>
                                            <td className="px-3 py-3 text-right">{formatCurrency(product.unitPrice)}</td>
                                            <td className="px-3 py-3 text-right">{formatCurrency(product.discount)}</td>
                                            <td className="px-3 py-3 text-right font-semibold">{formatCurrency(product.lineTotal)}</td>
                                        </tr>
                                    )) : (
                                        <tr><td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">—</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-5 xl:grid-cols-[1fr_420px]">
                        <div className="rounded-lg border bg-background p-4">
                            <div className="mb-2 font-semibold">{t('operations.customer_interactions.purchase_internal_notes')}</div>
                            <DetailRow label={t('operations.customer_interactions.purchase_accounting_note')}>{item.accountingNotes}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_recon_note')}>{item.internalReconNote}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_recon_status')}>{item.reconciliationStatus}</DetailRow>
                        </div>
                        <div className="rounded-lg border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2 font-semibold">
                                <WalletCards className="size-4 text-[#3782dc]" />
                                {t('operations.customer_interactions.purchase_finance')}
                            </div>
                            <DetailRow label={t('operations.customer_interactions.purchase_subtotal')}>{formatCurrency(item.subtotal)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_discount')}>{formatCurrency(item.discount)}</DetailRow>
                            <DetailRow label="VAT">{formatCurrency(item.vat)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_shipping_fee')}>{formatCurrency(item.shippingFeeCollected)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_deposit')}>{formatCurrency(item.deposit)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_collect')}>{formatCurrency(item.amountToCollect)}</DetailRow>
                            <DetailRow label={t('operations.customer_interactions.purchase_total')} className="font-bold">{formatCurrency(item.total)}</DetailRow>
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}

export function CustomerPurchaseHistoryDialog({ order }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [summary, setSummary] = useState(null);
    const [orders, setOrders] = useState([]);
    const [limited, setLimited] = useState(false);
    const [expanded, setExpanded] = useState({});

    useEffect(() => {
        if (!open || loaded) return;

        let active = true;
        setLoading(true);

        apiGet(`/customers/orders/${order.id}/purchase-history`)
            .then((data) => {
                if (!active) return;
                const nextOrders = data.orders ?? [];
                setCustomer(data.customer ?? null);
                setSummary(data.summary ?? null);
                setOrders(nextOrders);
                setLimited(Boolean(data.limited));
                setExpanded(Object.fromEntries(nextOrders.filter((item) => item.isSelected).map((item) => [item.id, true])));
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
        if (nextOpen) setLoaded(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    className="text-sky-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950/40"
                    title={t('operations.customer_interactions.purchase_title')}
                    aria-label={t('operations.customer_interactions.purchase_title')}
                >
                    <ShoppingBag className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[92vh] max-w-[min(1500px,calc(100vw-1.5rem))] overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5 pr-14">
                    <DialogTitle>{t('operations.customer_interactions.purchase_title')}</DialogTitle>
                    <DialogDescription>
                        {(customer?.name ?? order.customerName ?? '—')} · {(customer?.phone ?? order.customerPhone ?? '—')}
                        {customer?.address ? ` · ${customer.address}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(92vh-96px)] overflow-y-auto p-5">
                    {loading ? (
                        <div className="flex min-h-72 items-center justify-center text-muted-foreground">
                            <Loader2 className="mr-2 size-5 animate-spin" />
                            {t('operations.customer_interactions.loading')}
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                <SummaryCard icon={ShoppingBag} label={t('operations.customer_interactions.purchase_order_count')} value={formatNumber(summary?.orderCount ?? 0)} />
                                <SummaryCard icon={PackageSearch} label={t('operations.customer_interactions.purchase_closed_count')} value={formatNumber(summary?.closedOrderCount ?? 0)} />
                                <SummaryCard icon={ShoppingBag} label={t('operations.customer_interactions.purchase_total_quantity')} value={formatNumber(summary?.totalQuantity ?? 0)} />
                                <SummaryCard icon={WalletCards} label={t('operations.customer_interactions.purchase_total_value')} value={formatCurrency(summary?.totalValue ?? 0)} />
                                <SummaryCard
                                    icon={PackageSearch}
                                    label={t('operations.customer_interactions.purchase_period')}
                                    value={summary?.orderCount ? formatDateTime(summary.latestOrderAt) : '—'}
                                    hint={summary?.firstOrderAt ? `${t('operations.customer_interactions.purchase_from')} ${formatDateTime(summary.firstOrderAt)}` : null}
                                />
                            </div>

                            <div className="mt-5 space-y-3">
                                {orders.length ? orders.map((item) => (
                                    <PurchaseOrderCard
                                        key={item.id}
                                        item={item}
                                        expanded={Boolean(expanded[item.id])}
                                        onToggle={() => setExpanded((current) => ({ ...current, [item.id]: !current[item.id] }))}
                                        t={t}
                                    />
                                )) : (
                                    <div className="rounded-lg border border-dashed p-12 text-center text-muted-foreground">
                                        {t('operations.customer_interactions.purchase_empty')}
                                    </div>
                                )}
                            </div>

                            {limited && (
                                <div className="mt-3 text-xs text-muted-foreground">
                                    {t('operations.customer_interactions.purchase_limited')}
                                </div>
                            )}
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
