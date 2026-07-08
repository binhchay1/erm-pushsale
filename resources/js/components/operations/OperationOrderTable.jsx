import { Copy, Heart } from 'lucide-react';

import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CloseOrderButton } from '@/components/operations/CloseOrderButton';
import { OperationCallButton } from '@/components/operations/OperationCallButton';
import { OperationStatusDialog } from '@/components/operations/OperationStatusDialog';
import { CustomerMessagesDialog } from '@/components/customers/CustomerMessagesDialog';
import { OrderOperationHistoryDialog } from '@/components/customers/OrderOperationHistoryDialog';
import { CustomerPurchaseHistoryDialog } from '@/components/customers/CustomerPurchaseHistoryDialog';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { deliveryTone } from '@/lib/status-tones';
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
    const baseCols = 15;

    const { sortedRows, sort, toggleSort } = useTableSort(rows ?? [], { defaultKey: 'dataArrivedAt', defaultDir: 'desc' });

    return (
        <ScrollDataTable>
            <table className="min-w-[2720px] w-full border-collapse">
                <thead className="bg-[#3782dc] text-white">
                    <tr>
                        <Th sortable sortKey="orderCode" sort={sort} onSort={toggleSort}>Mã đơn</Th>
                        <Th sortable sortKey="dataArrivedAt" sort={sort} onSort={toggleSort}>
                            <div>Nguồn dữ liệu</div>
                            <div className="font-normal text-xs mt-0.5">Ngày data về</div>
                        </Th>
                        <Th sortable sortKey="customerName" sort={sort} onSort={toggleSort}>
                            <div>Họ tên</div>
                            <div className="font-normal text-xs mt-0.5">Số điện thoại</div>
                        </Th>
                        <Th>
                            <div>Địa chỉ</div>
                            <div className="font-normal text-xs mt-0.5">Địa chỉ nhận hàng</div>
                        </Th>
                        <Th>
                            <div>Tin nhắn</div>
                            <div className="font-normal text-xs mt-0.5">Ghi chú khách hàng</div>
                        </Th>
                        <Th sortable sortKey="saleName" sort={sort} onSort={toggleSort}>
                            <div>Sale</div>
                            <div className="font-normal text-xs mt-0.5">Ngày nhận data</div>
                        </Th>
                        <Th sortable sortKey="currentOperation" sort={sort} onSort={toggleSort}>
                            <div>Tác nghiệp</div>
                            <div className="font-normal text-xs mt-0.5">Ngày chốt đơn</div>
                        </Th>
                        <Th>
                            <div>Kết quả</div>
                            <div className="font-normal text-xs mt-0.5">Ngày sale tác nghiệp</div>
                        </Th>
                        <Th>Sản phẩm - Số lượng - Đơn giá</Th>
                        <Th sortable sortKey="total" sort={sort} onSort={toggleSort} className="text-right">
                            <div>Thành tiền</div>
                            <div className="font-normal text-xs mt-0.5">CK/VAT</div>
                            <div className="font-normal text-xs mt-0.5">Phí VC/Tổng tiền</div>
                        </Th>
                        <Th>Khách đặt cọc</Th>
                        <Th>
                            <div>Kho</div>
                            <div className="font-normal text-xs mt-0.5">PTGH</div>
                            <div className="font-normal text-xs mt-0.5">Mã giao vận</div>
                        </Th>
                        <Th sortable sortKey="deliveryStatus" sort={sort} onSort={toggleSort}>
                            <div>Trạng thái giao hàng</div>
                            <div className="font-normal text-xs mt-0.5">Ngày muốn nhận hàng</div>
                        </Th>
                        <Th>ĐSNB</Th>

                        {enableSaleActions && <Th>Thao tác</Th>}
                        {enableCloseOrder && <Th>Đóng đơn</Th>}
                        {enableDeleteOrder && <Th />}
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.length ? (
                        sortedRows.map((row) => (
                            <tr key={row.id} className="align-middle hover:bg-muted/30 border-b">
                                <Td className="text-center text-sm">
                                    <div className="text-blue-500 hover:underline cursor-pointer">{row.sourceName}</div>
                                    <div className="text-muted-foreground mt-1 text-[11px]">{formatDateTime(row.dataArrivedAt)}</div>
                                </Td>

                                <Td>
                                    <div className="text-blue-500 font-medium">{row.customerName}</div>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className="text-blue-500">{row.customerPhone}</span>
                                        <Copy className="size-3.5 text-red-500 cursor-pointer" />
                                        {row.isReturningCustomer && <Heart className="size-3.5 fill-red-500 text-red-500" />}
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

                                <Td className="whitespace-normal text-sm">
                                    {row.products?.map((p) => (
                                        <div key={p.itemId ?? p.productName} className="flex justify-between items-center gap-4 border-b border-dashed border-gray-200 last:border-0 pb-1 mb-1 last:pb-0 last:mb-0">
                                            <span className="flex-1">{p.productName}</span>
                                            <span className="w-10 text-center">x{p.quantity}</span>
                                            <span className="w-20 text-right">{formatCurrency(p.unitPrice)}</span>
                                        </div>
                                    ))}
                                </Td>

                                <Td className="text-right text-sm font-medium">
                                    <div>{formatCurrency(row.subtotal)}</div>
                                    <div className="text-muted-foreground font-normal">
                                        {row.discount > 0 ? `-${formatCurrency(row.discount)}` : '0'}
                                    </div>
                                    <div className="text-muted-foreground font-normal">
                                        {row.shippingFeeCollected > 0 ? formatCurrency(row.shippingFeeCollected) : '0'}
                                    </div>
                                    <div className="font-bold mt-1 text-black">{formatCurrency(row.total)}</div>
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
                                    <Td><CloseOrderButton order={row} /></Td>
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
