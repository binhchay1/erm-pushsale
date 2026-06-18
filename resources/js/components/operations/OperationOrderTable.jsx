import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CloseOrderButton } from '@/components/operations/CloseOrderButton';
import { OperationCallButton } from '@/components/operations/OperationCallButton';
import { OperationStatusDialog } from '@/components/operations/OperationStatusDialog';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatCurrency } from '@/lib/format';
import { closingTone, deliveryTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

function formatDateTime(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function OperationOrderTable({
    rows,
    enableCloseOrder = false,
    enableDeleteOrder = false,
    enableSaleActions = false,
    operationStatusOptions = [],
}) {
    const t = useT();
    const actionCols =
        (enableSaleActions ? 1 : 0) + (enableCloseOrder ? 1 : 0) + (enableDeleteOrder ? 1 : 0);
    const baseCols = 10;

    return (
        <ScrollDataTable>
            <table className="min-w-[1800px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>{t('operations.order_table.order_code')}</Th>
                        <Th>{t('operations.order_table.source_date')}</Th>
                        <Th>{t('operations.order_table.sale_assigned')}</Th>
                        <Th>{t('operations.order_table.customer')}</Th>
                        <Th>{t('operations.order_table.message')}</Th>
                        <Th>{t('operations.order_table.operation')}</Th>
                        <Th>{t('operations.order_table.products')}</Th>
                        <Th>{t('operations.order_table.finance')}</Th>
                        <Th>{t('operations.order_table.closing')}</Th>
                        <Th>{t('operations.order_table.delivery')}</Th>
                        {enableSaleActions && <Th>{t('operations.order_table.actions')}</Th>}
                        {enableCloseOrder && <Th>{t('operations.order_table.close')}</Th>}
                        {enableDeleteOrder && <Th />}
                    </tr>
                </thead>
                <tbody>
                    {rows?.length ? (
                        rows.map((row) => (
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
                                <Td>
                                    <div className="font-medium text-primary">{row.customerName}</div>
                                    <div>{row.customerPhone}</div>
                                    {row.phoneCarrier && (
                                        <span className="text-[10px] text-muted-foreground">
                                            [{row.phoneCarrier}]
                                        </span>
                                    )}
                                </Td>
                                <Td className="max-w-[200px] whitespace-normal text-muted-foreground">
                                    {row.customerNote || row.shippingAddress}
                                </Td>
                                <Td>
                                    <span className="font-semibold text-destructive">{row.currentOperation}</span>
                                    <div className="text-muted-foreground">{row.operationResult || '—'}</div>
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
                                <Td className="whitespace-normal">
                                    {row.products?.map((p) => (
                                        <div key={p.productName}>
                                            {p.productName} x{p.quantity} — {formatCurrency(p.unitPrice)}
                                        </div>
                                    ))}
                                </Td>
                                <Td>
                                    <div>{t('operations.order_table.subtotal')} {formatCurrency(row.subtotal)}</div>
                                    <div>{t('operations.order_table.shipping_fee')} {formatCurrency(row.shippingFeeCollected)}</div>
                                    <div className="font-semibold">{t('operations.order_table.total')} {formatCurrency(row.total)}</div>
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
                                {enableSaleActions && (
                                    <Td>
                                        <div className="flex flex-col gap-1.5">
                                            <OperationCallButton order={row} />
                                            <OperationStatusDialog
                                                order={row}
                                                options={operationStatusOptions}
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
