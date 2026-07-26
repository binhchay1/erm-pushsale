import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CloseOrderButton } from '@/components/operations/CloseOrderButton';
import { OperationCallButton } from '@/components/operations/OperationCallButton';
import { OperationStatusDialog } from '@/components/operations/OperationStatusDialog';
import { CustomerMessagesDialog } from '@/components/customers/CustomerMessagesDialog';
import { OrderOperationHistoryDialog } from '@/components/customers/OrderOperationHistoryDialog';
import { CustomerPurchaseHistoryDialog } from '@/components/customers/CustomerPurchaseHistoryDialog';
import { CustomerSupplementPacketsDialog } from '@/components/customers/CustomerSupplementPacketsDialog';
import { OrderMoneyBreakdown, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { deliveryTone } from '@/lib/status-tones';
import { useT } from '@/providers/I18nProvider';

const externalHref = (url) => {
    const value = String(url ?? '').trim();
    if (!value) return null;
    if (/^(https?:)?\/\//i.test(value)) return value.startsWith('//') ? `https:${value}` : value;
    return `https://${value}`;
};

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
    actionBaseUrl = '/sales',
}) {
    const t = useT();
    const actionCols =
        (enableSaleActions ? 1 : 0) + (enableCloseOrder ? 1 : 0) + (enableDeleteOrder ? 1 : 0);
    const baseCols = 14;

    const { sortedRows, sort, toggleSort } = useTableSort(rows ?? [], { defaultKey: 'dataArrivedAt', defaultDir: 'desc' });

    return (
        <ScrollDataTable>
            <table className="min-w-[2720px] w-full border-collapse">
                <thead className="bg-[#3782dc] text-white">
                    <tr>
                        <Th className="text-center" sortable sortKey="orderCode" sort={sort} onSort={toggleSort}>Mã đơn</Th>
                        <Th className="text-center" sortable sortKey="dataArrivedAt" sort={sort} onSort={toggleSort}>
                            <div className="flex flex-col gap-1 text-center">
                                <div>Nguồn dữ liệu</div>
                                <div>Ngày data về</div>
                            </div>
                        </Th>
                        <Th className="text-center" sortable sortKey="customerName" sort={sort} onSort={toggleSort}>
                            <div className="flex flex-col gap-1 text-center">
                                <div>Họ tên</div>
                                <div>Số điện thoại</div>
                            </div>
                        </Th>
                        <Th className="text-center">
                            <div className="flex flex-col gap-1 text-center">
                                <div>Địa chỉ</div>
                                <div>Địa chỉ nhận hàng</div>
                            </div>
                        </Th>
                        <Th className="text-center">
                            <div className="flex flex-col gap-1 text-center">
                                <div>Tin nhắn</div>
                                <div>Ghi chú khách hàng</div>
                            </div>
                        </Th>
                        <Th className="text-center" sortable sortKey="saleName" sort={sort} onSort={toggleSort}>
                            <div className="flex flex-col gap-1 text-center">
                                <div>Sale</div>
                                <div>Ngày nhận data</div>
                            </div>
                        </Th>
                        <Th sortable sortKey="currentOperation" sort={sort} onSort={toggleSort}>
                            <div className="flex flex-col gap-1 text-center">
                                <div>Tác nghiệp</div>
                                <div>Ngày chốt đơn</div>
                            </div>
                        </Th>
                        <Th className="text-center">
                            <div className="flex flex-col gap-1 text-center">
                                <div>Kết quả</div>
                                <div>Ngày sale tác nghiệp</div>
                            </div>
                        </Th>
                        <Th className="text-center w-80">
                            <div>Sản phẩm - Số lượng - Đơn giá</div>
                        </Th>
                        <Th sortable sortKey="total" sort={sort} onSort={toggleSort} className="text-right">
                            <div className="flex flex-col gap-1 text-center">
                                <div>Thành tiền</div>
                                <div className="font-normal text-xs">CK/VAT</div>
                                <div className="font-normal text-xs">Phí VC/Tổng tiền</div>
                            </div>
                        </Th>
                        <Th className="text-center">Khách đặt cọc</Th>
                        <Th className="text-center">
                            <div className="flex flex-col gap-1 text-center">
                                <div>Kho</div>
                                <div className="font-normal text-xs">PTGH</div>
                                <div className="font-normal text-xs">Mã giao vận</div>
                            </div>
                        </Th>
                        <Th sortable sortKey="deliveryStatus" sort={sort} onSort={toggleSort}>
                            <div className="flex flex-col gap-1 text-center">
                                <div>Trạng thái giao hàng</div>
                                <div className="font-normal text-xs">Ngày muốn nhận hàng</div>
                            </div>
                        </Th>
                        <Th className="text-center">ĐSNB</Th>

                        {enableSaleActions && <Th>Thao tác</Th>}
                        {enableCloseOrder && <Th>Đóng đơn</Th>}
                        {enableDeleteOrder && <Th />}
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.length ? (
                        sortedRows.map((row) => (
                            <tr key={row.id} className="align-middle hover:bg-muted/30 border-b">

                                <Td className="text-center font-medium ps-code-cell">
                                    <div className="ps-order-code-stack">
                                        <div className="ps-order-code-text">{row.orderCode || '—'}</div>
                                    </div>
                                </Td>

                                <Td className="text-center text-sm">
                                    {externalHref(row.sourceUrl) ? (
                                        <a className="text-blue-500 hover:underline" href={externalHref(row.sourceUrl)} target="_blank" rel="noopener noreferrer" title={row.sourceUrl}>
                                            {row.sourceName}
                                        </a>
                                    ) : (
                                        <div className="text-blue-500">{row.sourceName}</div>
                                    )}
                                    <div className="text-muted-foreground mt-1 text-[11px]">{formatDateTime(row.dataArrivedAt)}</div>
                                </Td>

                                <Td>
                                    <div className="text-blue-500 font-medium">{row.customerName}</div>
                                    <div className="flex items-center gap-2 mt-1 ps-contact-phone-row">
                                        <span className="text-blue-500">{row.customerPhone}</span>
                                        {row.customerPhone ? (
                                            <a className="ps-contact-phone-icon" href={`tel:${row.customerPhone}`} title="Gọi khách hàng" aria-label={`Gọi ${row.customerPhone}`}>
                                                <i className="fa fa-phone" aria-hidden="true" />
                                            </a>
                                        ) : null}
                                        <OrderStatusFlags row={row} className="ps-contact-flags" />
                                        {row.pendingSupplementCount > 0 && (
                                            <CustomerSupplementPacketsDialog order={row} count={row.pendingSupplementCount} />
                                        )}
                                    </div>
                                    <CarrierBadge carrier={row.phoneCarrier} carrierKey={row.phoneCarrierKey} />
                                </Td>

                                <Td className="max-w-[320px] whitespace-normal text-sm leading-relaxed">
                                    <div className="font-medium text-foreground">
                                        {row.effectiveShippingAddress || row.shippingAddress || '—'}
                                    </div>
                                    {row.hasDifferentReceiver && (
                                        <div className="mt-1 text-[11px] text-muted-foreground">
                                            Người nhận: {[row.effectiveReceiverName, row.effectiveReceiverPhone].filter(Boolean).join(' · ')}
                                        </div>
                                    )}
                                </Td>

                                <Td className="max-w-[300px] whitespace-normal text-sm leading-relaxed">
                                    {row.customerNote || '—'}
                                </Td>

                                <Td className="text-center text-sm">
                                    <div className="font-medium">{row.saleName}</div>
                                    <div className="text-muted-foreground text-[11px] mt-1">{formatDateTime(row.assignedAt)}</div>
                                </Td>

                                <Td className="text-center text-sm">
                                    <div className="font-medium">{row.currentOperation || 'Khách mới'}</div>
                                    {row.closedAt && (
                                        <div className="text-muted-foreground text-[11px] mt-1">{formatDateTime(row.closedAt)}</div>
                                    )}
                                </Td>

                                <Td className="text-center">
                                    <div className="mb-1 flex items-center justify-center gap-1">
                                        <CustomerMessagesDialog order={row} />
                                        <OrderOperationHistoryDialog order={row} />
                                        <CustomerPurchaseHistoryDialog order={row} />
                                    </div>
                                    <div className="text-sm">{row.operationResult}</div>
                                    {row.nextOperationAt && (
                                        <div className="text-muted-foreground text-[11px] mt-1">{formatDateTime(row.nextOperationAt)}</div>
                                    )}
                                </Td>

                                <Td className="whitespace-normal text-sm ps-order-products-cell">
                                    <OrderProductsBreakdown items={row.products ?? []} order={row} />
                                </Td>

                                <Td className="text-right text-sm font-medium ps-order-money-cell">
                                    <OrderMoneyBreakdown row={row} />
                                </Td>

                                <Td className="text-center text-sm">
                                    {row.deposit > 0 ? formatCurrency(row.deposit) : ''}
                                </Td>

                                <Td className="whitespace-normal text-center text-sm">
                                    <div>{row.warehouseName}</div>
                                    {row.shippingProvider && <div className="text-muted-foreground">{row.shippingProvider}</div>}
                                    {row.trackingNumber && <div className="font-mono text-primary">{row.trackingNumber}</div>}
                                </Td>

                                <Td className="text-center">
                                    <StatusBadge tone={deliveryTone(row.deliveryStatusValue)}>
                                        {row.deliveryStatus}
                                    </StatusBadge>
                                    {row.desiredDeliveryAt && (
                                        <div className="mt-1 text-muted-foreground text-[11px]">{formatDateTime(row.desiredDeliveryAt, { withTime: false })}</div>
                                    )}
                                </Td>

                                <Td className="max-w-[120px] whitespace-normal text-center text-sm text-muted-foreground">
                                    {row.internalReconNote}
                                </Td>

                                {enableSaleActions && (
                                    <Td>
                                        <div className="flex flex-col gap-1.5">
                                            <OperationCallButton order={row} actionBaseUrl={actionBaseUrl} />
                                            <OperationStatusDialog
                                                order={row}
                                                actionBaseUrl={actionBaseUrl}
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
                                    <Td><CloseOrderButton order={row} actionBaseUrl={actionBaseUrl} /></Td>
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
                            <Td colSpan={baseCols + actionCols} className="py-8 text-center text-muted-foreground">
                                {t('operations.order_table.no_data')}
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
