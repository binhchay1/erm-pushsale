import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { CloseOrderButton } from '@/components/operations/CloseOrderButton';
import { OperationCallButton } from '@/components/operations/OperationCallButton';
import { OperationStatusDialog } from '@/components/operations/OperationStatusDialog';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatCurrency } from '@/lib/format';
import { closingTone, deliveryTone } from '@/lib/status-tones';

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
    const actionCols =
        (enableSaleActions ? 1 : 0) + (enableCloseOrder ? 1 : 0) + (enableDeleteOrder ? 1 : 0);
    const baseCols = 10;

    return (
        <ScrollDataTable>
            <table className="min-w-[1800px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>Mã đơn</Th>
                        <Th>Nguồn / Ngày data</Th>
                        <Th>Sale / Nhận data</Th>
                        <Th>Khách hàng</Th>
                        <Th>Tin nhắn</Th>
                        <Th>TN / Kết quả</Th>
                        <Th>Sản phẩm</Th>
                        <Th>Tài chính</Th>
                        <Th>Chốt đơn</Th>
                        <Th>Giao hàng</Th>
                        {enableSaleActions && <Th>Hành động</Th>}
                        {enableCloseOrder && <Th>Chốt</Th>}
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
                                        Nhận: {row.assignedAt?.slice(0, 10) ?? '—'}
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
                                    <span className="font-medium text-destructive">{row.currentOperation}</span>
                                    <div className="text-muted-foreground">{row.operationResult || '—'}</div>
                                    {row.nextOperationAt && (
                                        <div className="mt-1 text-[11px] text-amber-700 dark:text-amber-400">
                                            Hẹn: {formatDateTime(row.nextOperationAt)}
                                        </div>
                                    )}
                                    {row.contactCount > 0 && (
                                        <div className="text-[11px] text-muted-foreground">
                                            Đã gọi: {row.contactCount} lần
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
                                    <div>TT: {formatCurrency(row.subtotal)}</div>
                                    <div>Phí VC: {formatCurrency(row.shippingFeeCollected)}</div>
                                    <div className="font-semibold">Tổng: {formatCurrency(row.total)}</div>
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
                                            confirmMessage={`Xóa đơn "${row.orderCode}"? Dữ liệu thống kê và kế toán liên quan sẽ bị gỡ.`}
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
                                Không có dữ liệu
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
