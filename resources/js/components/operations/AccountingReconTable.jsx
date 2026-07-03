import { Heart, Copy, CheckCircle2, Circle } from 'lucide-react';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { deliveryTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

function Money({ value, className }) {
    if (!value) return <span className="text-muted-foreground">—</span>;
    return <span className={className}>{formatNumber(value)}</span>;
}

export function AccountingReconTable({ rows, totals, enableDeleteOrder = false }) {
    const t = useT();
    const { sortedRows, sort, toggleSort } = useTableSort(rows ?? [], {
        defaultKey: 'dataArrivedAt',
        defaultDir: 'desc',
    });

    const colCount = 18 + (enableDeleteOrder ? 1 : 0);

    return (
        <ScrollDataTable>
            <table className="min-w-[2200px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th className="text-center">{t('operations.recon_table.seq')}</Th>
                        <Th sortable sortKey="saleName" sort={sort} onSort={toggleSort}>
                            {t('operations.recon_table.sale')}
                        </Th>
                        <Th sortable sortKey="dataArrivedAt" sort={sort} onSort={toggleSort}>
                            {t('operations.recon_table.source_order')}
                        </Th>
                        <Th>{t('operations.recon_table.warehouse_carrier')}</Th>
                        <Th>{t('operations.recon_table.care')}</Th>
                        <Th sortable sortKey="deliveryStatus" sort={sort} onSort={toggleSort}>
                            {t('operations.recon_table.delivery')}
                        </Th>
                        <Th className="text-center">{t('operations.recon_table.recon')}</Th>
                        <Th>{t('operations.recon_table.products')}</Th>
                        <Th className="text-right" sortable sortKey="subtotal" sort={sort} onSort={toggleSort}>
                            {t('operations.recon_table.subtotal')}
                        </Th>
                        <Th className="text-right">{t('operations.recon_table.discount')}</Th>
                        <Th className="text-right">{t('operations.recon_table.vat')}</Th>
                        <Th className="text-right">{t('operations.recon_table.shipping_fee')}</Th>
                        <Th className="text-right" sortable sortKey="total" sort={sort} onSort={toggleSort}>
                            {t('operations.recon_table.total')}
                        </Th>
                        <Th className="text-right">{t('operations.recon_table.deposit')}</Th>
                        <Th className="text-right">{t('operations.recon_table.collect')}</Th>
                        <Th className="text-right">{t('operations.recon_table.carrier_fee')}</Th>
                        <Th className="text-right">{t('operations.recon_table.support_fee')}</Th>
                        <Th>{t('operations.recon_table.customer')}</Th>
                        <Th>{t('operations.recon_table.address')}</Th>
                        {enableDeleteOrder && <Th />}
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.length ? (
                        sortedRows.map((row, index) => (
                            <tr key={row.id} className="align-top hover:bg-muted/30">
                                <Td className="text-center text-muted-foreground">{index + 1}</Td>
                                <Td>
                                    <div className="font-medium">{row.saleName}</div>
                                    <div className="text-[11px] text-muted-foreground">{row.saleGroup}</div>
                                </Td>
                                <Td className="whitespace-nowrap">
                                    <div className="text-[11px] text-muted-foreground">
                                        {formatDateTime(row.dataArrivedAt)}
                                    </div>
                                    <div className="font-mono font-semibold text-primary">{row.orderCode}</div>
                                    <div className="text-[11px] text-muted-foreground">
                                        {row.closedAt ? formatDateTime(row.closedAt) : '—'}
                                    </div>
                                </Td>
                                <Td className="whitespace-nowrap">
                                    <div>{row.warehouseName || '—'}</div>
                                    <div className="font-semibold text-emerald-600 dark:text-emerald-400">
                                        {row.carrierName || row.shippingProvider || '—'}
                                    </div>
                                    <div className="text-[11px] text-amber-600 dark:text-amber-400">
                                        {row.trackingNumber || ''}
                                    </div>
                                </Td>
                                <Td className="max-w-[180px] whitespace-normal">
                                    <div className="text-fuchsia-600 dark:text-fuchsia-400">
                                        {row.carePersonName || ''}
                                    </div>
                                    <div className="text-muted-foreground">{row.accountingNotes || ''}</div>
                                </Td>
                                <Td>
                                    <StatusBadge tone={deliveryTone(row.deliveryStatusValue)}>
                                        {row.deliveryStatus}
                                    </StatusBadge>
                                </Td>
                                <Td className="text-center">
                                    {row.internalReconNote ? (
                                        <span
                                            className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                                            title={t('operations.recon_table.reconciled')}
                                        >
                                            <CheckCircle2 className="size-4" />
                                        </span>
                                    ) : (
                                        <span
                                            className="inline-flex items-center gap-1 text-muted-foreground/50"
                                            title={t('operations.recon_table.not_reconciled')}
                                        >
                                            <Circle className="size-4" />
                                        </span>
                                    )}
                                </Td>
                                <Td className="max-w-[260px] whitespace-normal">
                                    {row.products?.map((p) => (
                                        <div key={p.itemId ?? p.productName} className="flex justify-between gap-2 border-b border-dashed border-border/40 py-0.5 last:border-0">
                                            <span className="truncate">{p.productName}</span>
                                            <span className="whitespace-nowrap text-muted-foreground">x{p.quantity}</span>
                                            <span className="whitespace-nowrap text-right">{formatNumber(p.unitPrice)}</span>
                                        </div>
                                    ))}
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.subtotal} />
                                </Td>
                                <Td className="text-right">
                                    {row.discount ? (
                                        <span className="text-destructive">-{formatNumber(row.discount)}</span>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.vat} />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.shippingFeeCollected} />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.total} className="font-semibold" />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.deposit} />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.amountToCollect} className="font-semibold text-emerald-600 dark:text-emerald-400" />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.carrierServiceFee} />
                                </Td>
                                <Td className="text-right">
                                    <Money value={row.shippingSupportFee} />
                                </Td>
                                <Td className="max-w-[180px] whitespace-normal">
                                    <div className="truncate font-medium">{row.customerName}</div>
                                    <div className="flex items-center gap-1 text-muted-foreground">
                                        {row.customerPhone}
                                        {row.isReturningCustomer && (
                                            <Heart className="size-3 text-destructive" aria-label={t('operations.recon_table.returning_customer')} />
                                        )}
                                        {row.isDuplicatePhone && (
                                            <Copy className="size-3 text-destructive" aria-label={t('operations.recon_table.duplicate_phone')} />
                                        )}
                                    </div>
                                    {row.desiredDeliveryAt && (
                                        <div className="text-[11px] text-muted-foreground">{formatDate(row.desiredDeliveryAt)}</div>
                                    )}
                                </Td>
                                <Td className="max-w-[220px] whitespace-normal">
                                    <div>{row.shippingAddress}</div>
                                    {row.customerNote && (
                                        <div className="mt-0.5 text-[11px] text-fuchsia-600 dark:text-fuchsia-400">
                                            {row.customerNote}
                                        </div>
                                    )}
                                </Td>
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
                            <Td colSpan={colCount} className="py-8 text-center text-muted-foreground">
                                {t('operations.recon_table.no_data')}
                            </Td>
                        </tr>
                    )}
                </tbody>
                {sortedRows.length > 0 && totals && (
                    <tfoot>
                        <tr className="border-t-2 border-border bg-muted/60 font-semibold">
                            <Td colSpan={7} className="text-right">
                                {t('operations.recon_table.total_row')}
                            </Td>
                            <Td className="text-center">{formatNumber(totals.quantity)}</Td>
                            <Td className="text-right">{formatCurrency(totals.subtotal)}</Td>
                            <Td className="text-right text-destructive">
                                {totals.discount ? `-${formatNumber(totals.discount)}` : '—'}
                            </Td>
                            <Td className="text-right">{formatNumber(totals.vat)}</Td>
                            <Td className="text-right">{formatNumber(totals.shippingFeeCollected)}</Td>
                            <Td className="text-right">{formatCurrency(totals.total)}</Td>
                            <Td className="text-right">{formatNumber(totals.deposit)}</Td>
                            <Td className="text-right text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(totals.amountToCollect)}
                            </Td>
                            <Td className="text-right">{formatNumber(totals.carrierServiceFee)}</Td>
                            <Td className="text-right">{formatNumber(totals.shippingSupportFee)}</Td>
                            <Td colSpan={enableDeleteOrder ? 3 : 2} />
                        </tr>
                    </tfoot>
                )}
            </table>
        </ScrollDataTable>
    );
}
