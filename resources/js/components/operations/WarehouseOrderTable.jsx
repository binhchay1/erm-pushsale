import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    AlertTriangle, Ban, CalendarClock, CircleDollarSign, Eye, FilePenLine, History,
    Loader2, PackageCheck, Printer, RotateCcw, Scissors, Truck, UserRoundCheck,
} from 'lucide-react';
import { toast } from 'sonner';

import { ShippingOrderDetailModal } from '@/components/shipping/ShippingOrderDetailModal';
import { WarehouseActionDialogs } from '@/components/operations/WarehouseActionDialogs';
import { apiPost, apiRequest } from '@/lib/api';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';

function Badge({ children, tone = 'default' }) { return <span className={`ps-wh-badge ${tone}`}>{children}</span>; }
function tone(status) {
    if (['paid','delivered','delivery_complete'].includes(status)) return 'success';
    if (['returned','returning','refund','cannot_deliver'].includes(status)) return 'danger';
    if (['delivering','picking_up','posted','partial_delivery'].includes(status)) return 'warning';
    return 'default';
}
function ActionButton({ title, onClick, children, disabled = false, className = '' }) {
    return <button type="button" className={`ps-wh-action ${className}`} title={title} onClick={onClick} disabled={disabled}>{children}</button>;
}

function ProductList({ items = [], upsell = false }) {
    if (!items.length) return <span>—</span>;
    return <div className="ps-wh-products">{items.map((item) => <div key={item.id} className={item.isUpsell ? 'upsell' : ''}><b>{item.productName}</b>{item.sku && <small>{item.sku}</small>}<span>x{item.quantity}</span>{item.isUpsell && <em>UPSALE</em>}</div>)}</div>;
}

function ShipmentButton({ row, apiBase }) {
    const [loading, setLoading] = useState(false);
    const create = async () => {
        setLoading(true);
        try {
            await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(`Đã tạo vận đơn cho ${row.orderCode}. Tồn kho được trừ tự động.`);
            router.reload({ only: ['report'] });
        } catch (error) { toast.error(error.message); }
        finally { setLoading(false); }
    };
    return <ActionButton title={row.hasInsufficientStock ? 'Không đủ tồn kho' : 'Tạo vận đơn'} onClick={create} disabled={loading || row.hasInsufficientStock} className={row.hasInsufficientStock ? 'danger' : 'primary'}>{loading ? <Loader2 className="animate-spin" /> : row.hasInsufficientStock ? <AlertTriangle /> : <Truck />}</ActionButton>;
}

export function WarehouseOrderTable({ rows = [], apiBase, actionApiBase, filterOptions = {}, canDeleteOrder = false }) {
    const [action, setAction] = useState(null);
    const [detailOrderId, setDetailOrderId] = useState(null);
    const printLabel = async (row) => {
        try {
            await apiRequest(`${actionApiBase}/${row.id}/printed`, { method: 'POST', body: {} });
            if (row.canPrintLabel) openShippingLabel(`${apiBase}/${row.id}/label`);
            else window.print();
            router.reload({ only: ['report'] });
        } catch (error) { toast.error(error.message); }
    };

    return (
        <>
            <div className="ps-wh-table-wrap">
                <table className="ps-wh-table">
                    <thead><tr>
                        <th rowSpan="2">STT</th><th rowSpan="2">Ngày các dữ liệu</th><th rowSpan="2">Mã đơn / trạng thái</th>
                        <th rowSpan="2">Sale / Care kho</th><th rowSpan="2">Khách hàng / địa chỉ</th>
                        <th colSpan="2">Sản phẩm</th><th colSpan="4">Giá trị đơn (VND)</th>
                        <th colSpan="4">Giao vận và dòng tiền (VND)</th><th rowSpan="2">Kho / tồn</th><th rowSpan="2">Tác nghiệp</th>
                    </tr><tr>
                        <th>Sản phẩm chính</th><th>Upsale / quà</th>
                        <th>Tạm tính</th><th>CK / VAT</th><th>Ship thu khách</th><th>Tổng đơn</th>
                        <th>COD dự kiến / đã thu</th><th>Phí giao / COD</th><th>Phí hoàn / khác</th><th>Thực thu / doanh thu ròng</th>
                    </tr></thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => (
                            <tr key={row.id} className={row.isReturnFlow ? 'return-row' : ''}>
                                <td className="center">{index + 1}</td>
                                <td className="ps-wh-dates">
                                    <span><b>Lead:</b> {formatDateTime(row.dataArrivedAt, { withSeconds: false })}</span>
                                    <span><b>Chốt:</b> {formatDateTime(row.closedAt, { withSeconds: false })}</span>
                                    <span><b>Hẹn giao:</b> {formatDateTime(row.desiredDeliveryAt, { withSeconds: false })}</span>
                                    <span><b>VC cập nhật:</b> {formatDateTime(row.lastDeliveryEventAt, { withSeconds: false })}</span>
                                </td>
                                <td>
                                    <button className="ps-wh-order-code" onClick={() => setDetailOrderId(row.id)}>{row.orderCode}</button>
                                    <div className="ps-wh-status-stack">
                                        <Badge tone={tone(row.deliveryStatusValue)}>{row.deliveryStatus}</Badge>
                                        {row.reconciliationStatus && <Badge tone={row.reconciliationStatus === 'settled' ? 'success' : 'default'}>{row.reconciliationStatus}</Badge>}
                                        {row.printedAt && <Badge tone="info">Đã in</Badge>}
                                    </div>
                                    <small>{row.shippingProviderLabel || 'Chưa chọn hãng'}</small>
                                    <strong className="ps-wh-tracking">{row.trackingNumber || 'Chưa có mã vận đơn'}</strong>
                                    {row.shipmentError && <p className="ps-wh-error">{row.shipmentError}</p>}
                                </td>
                                <td>
                                    <div><b>Sale:</b> {row.saleName || '—'}</div>
                                    <div><b>MKT:</b> {row.marketerName || '—'}</div>
                                    <div><b>Care:</b> {row.warehouseCareName || '—'}</div>
                                    <Badge tone={row.warehouseCareStatus === 'completed' ? 'success' : 'default'}>{row.warehouseCareStatus || 'Chưa care'}</Badge>
                                    {row.warehouseCareNote && <p className="ps-wh-note">{row.warehouseCareNote}</p>}
                                </td>
                                <td>
                                    <b>{row.effectiveReceiverName || row.customerName}</b>
                                    <a href={`tel:${row.effectiveReceiverPhone || row.customerPhone}`}>{row.effectiveReceiverPhone || row.customerPhone}</a>
                                    <p>{row.shippingAddress || 'Chưa có địa chỉ giao'}</p>
                                    {row.customerNote && <small>KH: {row.customerNote}</small>}
                                    {row.shippingNotes && <small>Giao: {row.shippingNotes}</small>}
                                </td>
                                <td><ProductList items={row.mainProducts} /></td>
                                <td><ProductList items={row.upsellProducts} upsell /></td>
                                <td className="money">{formatCurrency(row.subtotal)}</td>
                                <td className="money"><span>-{formatCurrency(row.discount)}</span><span>VAT {formatCurrency(row.vat)}</span></td>
                                <td className="money">{formatCurrency(row.shippingFeeCollected)}</td>
                                <td className="money strong">{formatCurrency(row.total)}</td>
                                <td className="money"><span>{formatCurrency(row.codAmount)}</span><span className="success">{formatCurrency(row.settledCodAmount)}</span><small>Cọc {formatCurrency(row.deposit)}</small></td>
                                <td className="money"><span>{formatCurrency(row.carrierServiceFee)}</span><span>{formatCurrency(row.codFee)}</span></td>
                                <td className="money"><span className={row.carrierReturnFee ? 'danger-text' : ''}>{formatCurrency(row.carrierReturnFee)}</span><span>{formatCurrency(row.carrierOtherFee)}</span><small className="success">Bồi hoàn {formatCurrency(row.carrierCompensationAmount)}</small></td>
                                <td className="money"><span>{formatCurrency(row.netCash)}</span><strong>{formatCurrency(row.netRevenue)}</strong></td>
                                <td>
                                    <b>{row.warehouseName || 'Chưa chọn kho'}</b>
                                    <div>{row.inventoryDeducted ? <Badge tone="success">Đã xuất kho</Badge> : <Badge>Chưa xuất kho</Badge>}</div>
                                    <small>SL: {formatNumber(row.totalQuantity)}</small>
                                    {row.returnRestockedAt && <Badge tone="info">Đã nhập hoàn</Badge>}
                                    {row.hasInsufficientStock && <p className="ps-wh-error">Không đủ tồn kho</p>}
                                </td>
                                <td>
                                    <div className="ps-wh-actions">
                                        <ActionButton title="Xem chi tiết giao vận" onClick={() => setDetailOrderId(row.id)}><Eye /></ActionButton>
                                        <ActionButton title="Lịch sử webhook" onClick={() => setAction({ type: 'timeline', row })}><History /></ActionButton>
                                        <ActionButton title="Cập nhật ngày giao" onClick={() => setAction({ type: 'date', row })}><CalendarClock /></ActionButton>
                                        <ActionButton title="Cập nhật care đơn" onClick={() => setAction({ type: 'care', row })}><UserRoundCheck /></ActionButton>
                                        <ActionButton title="Cập nhật trạng thái giao hàng" onClick={() => setAction({ type: 'delivery', row })}><PackageCheck /></ActionButton>
                                        <ActionButton title="Cập nhật đơn" onClick={() => setAction({ type: 'edit', row })}><FilePenLine /></ActionButton>
                                        <ActionButton title="Tách đơn" disabled={!row.canSplit} onClick={() => setAction({ type: 'split', row })}><Scissors /></ActionButton>
                                        <ActionButton title="Thêm SĐT blacklist" onClick={() => setAction({ type: 'blacklist', row })} className="danger"><Ban /></ActionButton>
                                        {row.canCreateShipment && <ShipmentButton row={row} apiBase={apiBase} />}
                                        <ActionButton title="In đơn / nhãn" onClick={() => printLabel(row)}><Printer /></ActionButton>
                                        {(row.isReturnFlow || row.canReceiveReturn) && <ActionButton title="Nhập hàng hoàn" onClick={() => setAction({ type: 'return', row })} className="warning"><RotateCcw /></ActionButton>}
                                    </div>
                                </td>
                            </tr>
                        )) : <tr><td colSpan="17" className="ps-wh-empty">Không có đơn phù hợp bộ lọc.</td></tr>}
                    </tbody>
                </table>
            </div>

            <WarehouseActionDialogs action={action} onClose={() => setAction(null)} actionApiBase={actionApiBase} filterOptions={filterOptions} />
            <ShippingOrderDetailModal orderId={detailOrderId} open={Boolean(detailOrderId)} onOpenChange={(open) => !open && setDetailOrderId(null)} apiBase={apiBase} />
        </>
    );
}
