import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency } from '@/lib/format';

export function OperationOrderTable({ rows }) {
    return (
        <ScrollDataTable>
            <table className="min-w-[1600px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>Mã đơn</Th>
                        <Th>Nguồn / Ngày data</Th>
                        <Th>Sale</Th>
                        <Th>Khách hàng</Th>
                        <Th>Tin nhắn</Th>
                        <Th>TN / Kết quả</Th>
                        <Th>Sản phẩm</Th>
                        <Th>Tài chính</Th>
                        <Th>Giao hàng</Th>
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
                                    <div>{row.deliveryStatus}</div>
                                    <div className="text-muted-foreground">{row.desiredDeliveryAt}</div>
                                </Td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <Td colSpan={9} className="py-8 text-center text-muted-foreground">
                                Không có dữ liệu
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
