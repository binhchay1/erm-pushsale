import { Heart } from 'lucide-react';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CloseOrderButton } from '@/components/operations/CloseOrderButton';
import { OperationCallButton } from '@/components/operations/OperationCallButton';
import { OperationStatusDialog } from '@/components/operations/OperationStatusDialog';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency } from '@/lib/format';
import { closingTone, deliveryTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

const CARRIER_STYLES = {
    viettel: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
    vinaphone: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    mobifone: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    vietnamobile: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
    gmobile: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    itel: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
};

function CarrierBadge({ carrier, carrierKey }) {
    if (!carrier) return null;
    const style = CARRIER_STYLES[carrierKey] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    return (
        <span className={`mt-0.5 inline-flex w-fit items-center rounded px-1.5 py-0.5 text-[10px] font-semibold ${style}`}>
            {carrier}
        </span>
    );
}

function formatDateTime(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Ho_Chi_Minh',
    });
}

export function OperationOrderTable({
    rows,
    enableCloseOrder = false,
    enableDeleteOrder = false,
    enableSaleActions = false,
    operationStatusOptions = [],
    carrierOptions = [],
    shippingServiceOptions = {},
    itemTypeOptions = ['product', 'combo', 'upsell', 'gift'],
    warehouseOptions = [],
    productOptions = [],
}) {
    const t = useT();
    const actionCols =
        (enableSaleActions ? 1 : 0) + (enableCloseOrder ? 1 : 0) + (enableDeleteOrder ? 1 : 0);
    const baseCols = 13;
    // Realtime-safe: sort recomputes on prop refresh, chosen column persists.
    const { sortedRows, sort, toggleSort } = useTableSort(rows ?? [], { defaultKey: 'dataArrivedAt', defaultDir: 'desc' });

    return (
        <ScrollDataTable>
            <table className="min-w-[2280px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th sortable sortKey="orderCode" sort={sort} onSort={toggleSort}>{t('operations.order_table.order_code')}</Th>
                        <Th sortable sortKey="dataArrivedAt" sort={sort} onSort={toggleSort}>{t('operations.order_table.source_date')}</Th>
                        <Th sortable sortKey="saleName" sort={sort} onSort={toggleSort}>{t('operations.order_table.sale_assigned')}</Th>
                        <Th sortable sortKey="customerName" sort={sort} onSort={toggleSort}>{t('operations.order_table.customer')}</Th>
                        <Th>{t('operations.order_table.message')}</Th>
                        <Th sortable sortKey="currentOperation" sort={sort} onSort={toggleSort}>{t('operations.order_table.operation')}</Th>
                        <Th sortable sortKey="operationResult" sort={sort} onSort={toggleSort}>{t('operations.order_table.result')}</Th>
                        <Th>{t('operations.order_table.products')}</Th>
                        <Th sortable sortKey="total" sort={sort} onSort={toggleSort}>{t('operations.order_table.finance')}</Th>
                        <Th>{t('operations.order_table.warehouse_shipping')}</Th>
                        <Th sortable sortKey="closingStatusLabel" sort={sort} onSort={toggleSort}>{t('operations.order_table.closing')}</Th>
                        <Th sortable sortKey="deliveryStatus" sort={sort} onSort={toggleSort}>{t('operations.order_table.delivery')}</Th>
                        <Th>{t('operations.order_table.internal_recon')}</Th>
                        {enableSaleActions && <Th>{t('operations.order_table.actions')}</Th>}
                        {enableCloseOrder && <Th>{t('operations.order_table.close')}</Th>}
                        {enableDeleteOrder && <Th />}
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.length ? (
                        sortedRows.map((row) => (
                            <tr key={row.id} className="align-top hover:bg-muted/30">
                                <Td className="font-mono text-primary">{row.orderCode}</Td>
                                <Td>
                                    <div className="font-medium">{row.sourceName}</div>
                                    <div className="text-muted-foreground">{row.dataArrivedAt?.slice(0, 10)}</div>
                                </Td>
                                <Td>
                                    <div>{row.saleName}</div>
                                    <div className="text-muted-foreground">{row.saleGroup}</div>
                                    <div className="text-[11px] text-muted-foreground">
                                        {t('operations.order_table.assigned')} {row.assignedAt?.slice(0, 10) ?? '—'}
                                    </div>
                                </Td>
                                <Td className="max-w-[220px] whitespace-normal">
                                    <div className="flex items-center gap-1 font-medium text-primary">
                                        <span>{row.customerName}</span>
                                        {row.isReturningCustomer && (
                                            <span title={t('operations.order_table.returning_customer')} className="inline-flex">
                                                <Heart className="size-3.5 shrink-0 fill-rose-500 text-rose-500" />
                                            </span>
                                        )}
                                    </div>
                                    <div>{row.customerPhone}</div>
                                    <CarrierBadge carrier={row.phoneCarrier} carrierKey={row.phoneCarrierKey} />
                                    {row.isReturningCustomer && (
                                        <div className="mt-0.5 text-[10px] font-medium text-rose-600 dark:text-rose-400">
                                            {t('operations.order_table.returning_customer')}
                                        </div>
                                    )}
                                    {(row.effectiveShippingAddress || row.shippingAddress) && (
                                        <div className="mt-1 text-[11px] text-muted-foreground">
                                            {row.effectiveShippingAddress || row.shippingAddress}
                                            {row.shippingAddress2 && (
                                                <span className="ml-1 rounded bg-emerald-100 px-1 text-[9px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                    {t('operations.order_table.address_confirmed')}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </Td>
                                <Td className="max-w-[200px] whitespace-normal text-muted-foreground">
                                    {row.customerNote || '—'}
                                </Td>
                                <Td>
                                    <span className="font-semibold text-destructive">{row.currentOperation}</span>
                                    {row.closedAt && (
                                        <div className="text-[11px] text-emerald-700 dark:text-emerald-400">
                                            {t('operations.order_table.closed_date')} {formatDateTime(row.closedAt)}
                                        </div>
                                    )}
                                    {row.nextOperationAt && (
                                        <div className="mt-1 text-[11px] text-amber-700 dark:text-amber-400">
                                            {t('operations.order_table.scheduled')} {formatDateTime(row.nextOperationAt)}
                                        </div>
                                    )}
                                    {row.contactCount > 0 && (
                                        <div className="text-[11px] text-muted-foreground">
                                            {t('operations.order_table.called', { count: row.contactCount })}
                                        </div>
                                    )}
                                </Td>
                                <Td className="whitespace-normal text-muted-foreground">
                                    {row.operationResult || '—'}
                                </Td>
                                <Td className="whitespace-normal">
                                    {row.products?.map((p) => (
                                        <div key={p.itemId ?? p.productName}>
                                            {p.productName} x{p.quantity} — {formatCurrency(p.unitPrice)}
                                            {p.itemType && p.itemType !== 'product' && (
                                                <span className="ml-1 text-[10px] uppercase text-muted-foreground">
                                                    [{p.itemType}]
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </Td>
                                <Td>
                                    <div>{t('operations.order_table.subtotal')} {formatCurrency(row.subtotal)}</div>
                                    {row.discount > 0 && (
                                        <div className="text-rose-600 dark:text-rose-400">
                                            {t('operations.order_table.discount')} {formatCurrency(row.discount)}
                                        </div>
                                    )}
                                    {row.vat > 0 && (
                                        <div className="text-muted-foreground">
                                            {t('operations.order_table.vat')} {formatCurrency(row.vat)}
                                        </div>
                                    )}
                                    <div>{t('operations.order_table.shipping_fee')} {formatCurrency(row.shippingFeeCollected)}</div>
                                    <div className="font-semibold">{t('operations.order_table.total')} {formatCurrency(row.total)}</div>
                                    {row.deposit > 0 && (
                                        <div className="text-muted-foreground">
                                            {t('operations.order_table.deposit')} {formatCurrency(row.deposit)}
                                        </div>
                                    )}
                                </Td>
                                <Td className="whitespace-normal">
                                    <div>{row.warehouseName || '—'}</div>
                                    {row.shippingProvider && (
                                        <div className="text-[11px] text-muted-foreground">{row.shippingProvider}</div>
                                    )}
                                    {row.trackingNumber && (
                                        <div className="font-mono text-[11px] text-primary">{row.trackingNumber}</div>
                                    )}
                                </Td>
                                <Td>
                                    <StatusBadge tone={closingTone(row.closingStatus)}>
                                        {row.closingStatusLabel}
                                    </StatusBadge>
                                </Td>
                                <Td>
                                    <StatusBadge tone={deliveryTone(row.deliveryStatusValue)}>
                                        {row.deliveryStatus}
                                    </StatusBadge>
                                    {row.desiredDeliveryAt && (
                                        <div className="mt-1 text-muted-foreground">{row.desiredDeliveryAt}</div>
                                    )}
                                </Td>
                                <Td className="max-w-[160px] whitespace-normal text-[11px] text-muted-foreground">
                                    {row.internalReconNote || '—'}
                                </Td>
                                {enableSaleActions && (
                                    <Td>
                                        <div className="flex flex-col gap-1.5">
                                            <OperationCallButton order={row} />
                                            <OperationStatusDialog
                                                order={row}
                                                options={operationStatusOptions}
                                                carrierOptions={carrierOptions}
                                                shippingServiceOptions={shippingServiceOptions}
                                                itemTypeOptions={itemTypeOptions}
                                                warehouseOptions={warehouseOptions}
                                                productOptions={productOptions}
                                            />
                                        </div>
                                    </Td>
                                )}
                                {enableCloseOrder && (
                                    <Td>
                                        <CloseOrderButton order={row} />
                                    </Td>
                                )}
                                {enableDeleteOrder && (
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
                            <Td
                                colSpan={baseCols + actionCols}
                                className="py-8 text-center text-muted-foreground"
                            >
                                {t('operations.order_table.no_data')}
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
