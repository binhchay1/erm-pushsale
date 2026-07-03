import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Eye, Loader2, PackageCheck, Printer, RotateCcw, Truck } from 'lucide-react';
import { toast } from 'sonner';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { ShippingOrderDetailModal } from '@/components/shipping/ShippingOrderDetailModal';
import { Button } from '@/components/ui/button';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { StatusBadge } from '@/components/ui/status-badge';
import { apiPost } from '@/lib/api';
import { formatCurrency } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';
import { deliveryTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Asia/Ho_Chi_Minh' });
}

function CreateShipmentButton({ row, apiBase }) {
    const t = useT();
    const [loading, setLoading] = useState(false);

    const create = async () => {
        setLoading(true);
        try {
            await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(t('operations.warehouse_table.shipment_created', { code: row.orderCode }));
            router.reload({ only: ['report'] });
        } catch (e) {
            toast.error(e.message);
        } finally {
            setLoading(false);
        }
    };

    if (row.hasInsufficientStock) {
        const missing = (row.stockWarnings ?? []).filter((w) => !w.sufficient);
        return (
            <div className="space-y-1">
                <Button size="sm" variant="destructive" disabled className="w-full">
                    <AlertTriangle className="size-3.5" />
                    {t('operations.warehouse_table.out_of_stock')}
                </Button>
                {missing.map((w) => (
                    <p key={w.productId} className="text-[11px] font-medium text-red-600 dark:text-red-400">
                        {t('operations.warehouse_table.stock_warning', {
                            product: w.productName,
                            required: w.required,
                            available: w.available,
                        })}
                    </p>
                ))}
            </div>
        );
    }

    return (
        <Button size="sm" onClick={create} disabled={loading} className="w-full">
            {loading ? <Loader2 className="size-3.5 animate-spin" /> : <Truck className="size-3.5" />}
            {t('operations.warehouse_table.create_waybill')}
        </Button>
    );
}

function ReceiveReturnButton({ row, apiBase }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState(row.returnReason ?? '');
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setLoading(true);
        try {
            const res = await apiPost(`${apiBase}/${row.id}/receive-return`, { reason });
            toast.success(
                res?.message ?? t('operations.warehouse_table.return_received_success', { code: row.orderCode }),
            );
            setOpen(false);
            router.reload({ only: ['report'] });
        } catch (e) {
            toast.error(e.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Button
                size="sm"
                variant="outline"
                className="w-full border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400"
                onClick={() => setOpen(true)}
            >
                <RotateCcw className="size-3.5" />
                {t('operations.warehouse_table.receive_return')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {t('operations.warehouse_table.return_dialog_title', { code: row.orderCode })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('operations.warehouse_table.return_dialog_desc')}
                            {row.inventoryDeducted ? '' : t('operations.warehouse_table.return_dialog_desc_no_deduct')}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <label className="text-sm font-medium" htmlFor={`return-reason-${row.id}`}>
                            {t('operations.warehouse_table.return_reason')}
                        </label>
                        <textarea
                            id={`return-reason-${row.id}`}
                            className="input-soft min-h-20 w-full resize-y"
                            placeholder={t('operations.warehouse_table.return_placeholder')}
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            maxLength={500}
                        />
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)} disabled={loading}>
                            {t('operations.warehouse_table.cancel')}
                        </Button>
                        <Button onClick={submit} disabled={loading}>
                            {loading ? <Loader2 className="size-3.5 animate-spin" /> : <RotateCcw className="size-3.5" />}
                            {t('operations.warehouse_table.confirm_return')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function WarehouseOrderTable({ rows, apiBase, canDeleteOrder = false }) {
    const t = useT();
    const [detailOrderId, setDetailOrderId] = useState(null);
    const baseCols = 6 + (canDeleteOrder ? 1 : 0);

    return (
        <>
            <ScrollDataTable>
                <table className="w-full min-w-[1100px] border-collapse">
                    <thead>
                        <tr>
                            <Th>{t('operations.warehouse_table.col_order')}</Th>
                            <Th>{t('operations.warehouse_table.col_customer')}</Th>
                            <Th>{t('operations.warehouse_table.col_products')}</Th>
                            <Th className="text-right">{t('operations.warehouse_table.col_cod')}</Th>
                            <Th>{t('operations.warehouse_table.col_shipping')}</Th>
                            <Th>{t('operations.warehouse_table.col_actions')}</Th>
                            {canDeleteOrder && <Th />}
                        </tr>
                    </thead>
                    <tbody>
                        {rows?.length ? (
                            rows.map((row) => (
                                <tr key={row.id} className="align-top">
                                    <Td className="font-mono text-primary">
                                        <div className="font-semibold">{row.orderCode}</div>
                                        <div className="font-sans text-[11px] text-muted-foreground">
                                            {t('operations.warehouse_table.closed_at')} {formatDate(row.closedAt)}
                                        </div>
                                        {row.warehouseName && (
                                            <div className="font-sans text-[11px] text-muted-foreground">
                                                {t('operations.warehouse_table.warehouse_label')} {row.warehouseName}
                                            </div>
                                        )}
                                    </Td>
                                    <Td className="max-w-[260px] whitespace-normal">
                                        <div className="font-medium">{row.customerName}</div>
                                        <div className="tabular-nums">{row.customerPhone}</div>
                                        <div className="mt-0.5 text-[11px] leading-snug text-muted-foreground">
                                            {row.shippingAddress || t('operations.warehouse_table.no_address')}
                                        </div>
                                    </Td>
                                    <Td className="whitespace-normal">
                                        {row.products?.length ? (
                                            row.products.map((p, idx) => (
                                                <div key={idx} className="flex items-baseline gap-1.5">
                                                    <span className="font-medium">{p.productName}</span>
                                                    {p.sku && (
                                                        <span className="rounded bg-muted px-1 font-mono text-[10px] text-muted-foreground">
                                                            {p.sku}
                                                        </span>
                                                    )}
                                                    <span className="font-semibold text-primary">x{p.quantity}</span>
                                                </div>
                                            ))
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                        {row.inventoryDeducted && (
                                            <StatusBadge tone="success" icon={PackageCheck} className="mt-1">
                                                {t('operations.warehouse_table.stock_deducted')}
                                            </StatusBadge>
                                        )}
                                    </Td>
                                    <Td className="text-right font-semibold tabular-nums">
                                        {formatCurrency(row.codAmount)}
                                    </Td>
                                    <Td>
                                        <StatusBadge tone={deliveryTone(row.deliveryStatusValue)}>
                                            {row.deliveryStatus}
                                        </StatusBadge>
                                        {row.trackingNumber && (
                                            <div className="mt-1 font-mono text-[11px]">{row.trackingNumber}</div>
                                        )}
                                        {row.shippingProviderLabel && (
                                            <div className="text-[11px] text-muted-foreground">
                                                {row.shippingProviderLabel}
                                            </div>
                                        )}
                                        {row.shipmentError && (
                                            <div className="mt-1 max-w-[180px] whitespace-normal text-[11px] text-red-600 dark:text-red-400">
                                                {row.shipmentError}
                                            </div>
                                        )}
                                        {row.returnReason && (
                                            <div className="mt-1 max-w-[180px] whitespace-normal text-[11px] text-amber-700 dark:text-amber-400">
                                                {t('operations.warehouse_table.return_reason_label')} {row.returnReason}
                                            </div>
                                        )}
                                        {row.returnRestockedAt && (
                                            <StatusBadge tone="success" icon={PackageCheck} className="mt-1">
                                                {t('operations.warehouse_table.return_received')}
                                            </StatusBadge>
                                        )}
                                    </Td>
                                    <Td>
                                        <div className="flex w-36 flex-col gap-1.5">
                                            {row.canCreateShipment && !row.isReturnFlow && (
                                                <CreateShipmentButton row={row} apiBase={apiBase} />
                                            )}
                                            {row.canReceiveReturn && (
                                                <ReceiveReturnButton row={row} apiBase={apiBase} />
                                            )}
                                            {row.canPrintLabel && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="w-full"
                                                    onClick={() =>
                                                        openShippingLabel(`${apiBase}/${row.id}/label`).catch(
                                                            (e) => toast.error(e.message)
                                                        )
                                                    }
                                                >
                                                    <Printer className="size-3.5" />
                                                    {t('operations.warehouse_table.print_label')}
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="w-full text-muted-foreground"
                                                onClick={() => setDetailOrderId(row.id)}
                                            >
                                                <Eye className="size-3.5" />
                                                {t('operations.warehouse_table.detail')}
                                            </Button>
                                        </div>
                                    </Td>
                                    {canDeleteOrder && (
                                        <Td>
                                            <DeleteRowButton
                                                url={`/admin/orders/${row.id}`}
                                                label={row.orderCode}
                                                confirmMessage={t('operations.delete_order_confirm', { code: row.orderCode })}
                                            />
                                        </Td>
                                    )}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <Td colSpan={baseCols} className="py-8 text-center text-muted-foreground">
                                    {t('operations.warehouse_table.empty')}
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </ScrollDataTable>

            <ShippingOrderDetailModal
                open={!!detailOrderId}
                onOpenChange={(open) => !open && setDetailOrderId(null)}
                orderId={detailOrderId}
                apiBase={apiBase}
            />
        </>
    );
}
