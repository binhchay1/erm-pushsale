import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiGet } from '@/lib/api';

const money = (value) => new Intl.NumberFormat('vi-VN').format(Number(value ?? 0));

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'medium' }).format(date);
}

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function HistoryNote({ history }) {
    const snapshot = history.metadata?.order_snapshot;
    if (!snapshot) return <div className="ps-history-note-text">{history.note || '—'}</div>;

    const products = snapshot.products ?? [];
    return (
        <div className="ps-history-order-snapshot">
            {history.note ? <div className="ps-history-note-text"><b>Ghi chú:</b> {history.note}</div> : null}
            <div><b>Khách hàng:</b> {snapshot.customer_name || '—'} - {snapshot.customer_phone || '—'}</div>
            <div><b>Người nhận:</b> {snapshot.receiver_name || '—'} - {snapshot.receiver_phone || '—'}</div>
            <div><b>Địa chỉ:</b> {snapshot.address || '—'}</div>
            <div><b>Sale / Nguồn:</b> {snapshot.sale || '—'} / {snapshot.source || '—'}</div>
            <div><b>Kho / Giao vận:</b> {snapshot.warehouse || '—'} / {snapshot.carrier_name || snapshot.shipping_method || 'Thủ công'}</div>
            {snapshot.shipping_notes ? <div><b>Ghi chú giao hàng:</b> {snapshot.shipping_notes}</div> : null}
            {products.length ? (
                <div className="ps-history-products">
                    <b>Sản phẩm:</b> {products.map((item) => `${item.name} x${item.quantity} (${money(item.unit_price)})`).join(' | ')}
                </div>
            ) : null}
            <div><b>Tiền hàng:</b> {money(snapshot.subtotal)} · <b>CK:</b> {money(snapshot.discount)} · <b>VAT:</b> {money(snapshot.vat)} · <b>VC:</b> {money(snapshot.shipping_fee_collected)}</div>
            <div><b>Tổng tiền:</b> {money(snapshot.total)} · <b>Đặt cọc:</b> {money(snapshot.deposit)} · <b>Phải thu:</b> {money(snapshot.amount_to_collect)}</div>
        </div>
    );
}

export function DesiredDeliveryDialog({ order, open, onOpenChange, actionBaseUrl }) {
    const [date, setDate] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => setDate(toDateTimeLocal(order?.desiredDeliveryAt)), [order?.desiredDeliveryAt, open]);
    if (!order) return null;

    const submit = (event) => {
        event.preventDefault();
        setProcessing(true);
        router.patch(`${actionBaseUrl}/orders/${order.id}/desired-delivery-date`, { desired_delivery_at: date }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật ngày muốn nhận hàng.');
                onOpenChange(false);
            },
            onError: (errors) => toast.error(errors.desired_delivery_at ?? errors.order ?? 'Không thể cập nhật.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-modal ps-desired-date-modal" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-modal-header"><DialogTitle>Cập nhật ngày muốn giao hàng</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="ps-desired-date-body">
                    <table className="table table-bordered table-striped">
                        <tbody>
                            <tr><td>Mã đơn</td><td className="ps-order-code">{order.orderCode || 'Đơn chưa chốt'}</td></tr>
                            <tr><td>Họ tên khách hàng</td><td><b>{order.customerName || '—'}</b></td></tr>
                            <tr><td>Số điện thoại</td><td><b>{order.customerPhone || '—'}</b></td></tr>
                            <tr><td>Đơn vị giao vận</td><td><b>{order.carrierName || order.shippingMethod || 'Thủ công'}</b></td></tr>
                            <tr><td>Mã giao vận</td><td>{order.trackingNumber || '—'}</td></tr>
                            <tr><td>Trạng thái giao hàng hiện tại</td><td><b>{order.deliveryStatus || '—'}</b></td></tr>
                            <tr>
                                <td>Ngày muốn nhận hàng</td>
                                <td><input className="form-control" type="datetime-local" required value={date} onChange={(event) => setDate(event.target.value)} /></td>
                            </tr>
                            <tr><td /><td><button className="btn btn-primary" disabled={processing}><i className="fa fa-floppy-o" /> Cập nhật</button></td></tr>
                        </tbody>
                    </table>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function OperationResultDialog({ order, result, open, onOpenChange, actionBaseUrl, onCloseOrder }) {
    const [nextAt, setNextAt] = useState('');
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setNextAt(toDateTimeLocal(order?.nextOperationAt));
            setNote('');
        }
    }, [open, order?.nextOperationAt]);

    if (!order || !result) return null;
    const needsDate = result.value === 'callback_scheduled';

    const submit = (event) => {
        event.preventDefault();
        if (result.value === 'closed_success') {
            onOpenChange(false);
            onCloseOrder(order);
            return;
        }
        setProcessing(true);
        router.post(`${actionBaseUrl}/orders/${order.id}/operation-status`, {
            operation_result: result.value,
            next_operation_at: needsDate ? nextAt : null,
            note,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật kết quả tác nghiệp.');
                onOpenChange(false);
            },
            onError: (errors) => toast.error(errors.next_operation_at ?? errors.operation_result ?? errors.order ?? 'Không thể cập nhật tác nghiệp.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-modal ps-operation-result-modal" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-modal-header"><DialogTitle>Cập nhật tác nghiệp</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="ps-operation-result-body">
                    <div className="form-group"><label>Khách hàng</label><div className="ps-static-value">{order.customerName} - {order.customerPhone}</div></div>
                    <div className="form-group"><label>Tác nghiệp cần</label><div className="ps-static-value">{order.currentOperation || 'Gọi lần 1'}</div></div>
                    <div className="form-group"><label>Kết quả tác nghiệp</label><div className="ps-static-value"><b>{result.label}</b></div></div>
                    {needsDate && <div className="form-group"><label>Tác nghiệp tiếp (*)</label><input required className="form-control" type="datetime-local" value={nextAt} onChange={(event) => setNextAt(event.target.value)} /></div>}
                    <div className="form-group"><label>Ghi chú</label><textarea className="form-control" rows={4} maxLength={1000} value={note} onChange={(event) => setNote(event.target.value)} /></div>
                    <div className="ps-sale-modal-footer"><button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button><button className="btn btn-primary" disabled={processing}><i className="fa fa-floppy-o" /> Cập nhật</button></div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function SaleOperationHistoryDialog({ order, context = 'sale', open, onOpenChange }) {
    const [loading, setLoading] = useState(false);
    const [samePhone, setSamePhone] = useState(false);
    const [data, setData] = useState({ customer: null, histories: [], hasMore: false });

    useEffect(() => {
        if (!open || !order) return;
        let active = true;
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/operation-history${samePhone ? '?same_phone=1' : ''}`)
            .then((payload) => active && setData(payload))
            .catch((error) => active && toast.error(error.message ?? 'Không tải được lịch sử tác nghiệp.'))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id, samePhone]);

    useEffect(() => { if (!open) setSamePhone(false); }, [open]);
    if (!order) return null;

    const histories = context === 'accounting'
        ? (data.histories ?? []).filter((history) => ['order_closed', 'order_updated', 'desired_delivery_updated', 'initial_snapshot'].includes(history.action))
        : (data.histories ?? []);
    const dialogTitle = context === 'accounting' ? 'Lịch sử kế toán' : 'Lịch sử tác nghiệp';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-modal ps-operation-history-modal" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-modal-header"><DialogTitle>{dialogTitle}: <span className="ps-history-title-code">{order.orderCode || `#${order.id}`}</span></DialogTitle></DialogHeader>
                <div className="ps-history-customer-line">
                    <b>{data.customer?.name ?? order.customerName}</b> · {data.customer?.phone ?? order.customerPhone} · {order.orderCode || `Đơn chưa chốt #${order.id}`}
                </div>
                <div className="table-responsive ps-history-table-wrap">
                    <table className="table table-bordered ps-history-table">
                        <thead><tr><th>#</th><th>Hoạt động</th><th>Tác nghiệp cần</th><th>KQ tác nghiệp</th><th>Tác nghiệp tiếp</th><th>Ghi chú</th><th>Ngày cập nhật</th><th /></tr></thead>
                        <tbody>
                            {loading ? <tr><td colSpan={8} className="text-center">Đang tải...</td></tr> : null}
                            {!loading && histories.map((history, index) => {
                                const autoRow = ['initial_snapshot', 'order_closed'].includes(history.action);
                                return (
                                    <tr key={history.id} className={autoRow ? 'ps-history-auto-row' : 'ps-history-manual-row'}>
                                        <td className="text-center">{index + 1}</td>
                                        <td><b>{history.actionLabel}</b><br /><span>{history.actorName}</span>{history.actorRole ? <><br /><small>{history.actorRole}</small></> : null}</td>
                                        <td>{history.stageBefore || history.stageAfter || '—'}</td>
                                        <td>{history.result || '—'}</td>
                                        <td>{history.stageAfter || '—'}{history.nextOperationAt ? <><br /><small>{formatDateTime(history.nextOperationAt)}</small></> : null}</td>
                                        <td className="ps-history-note"><HistoryNote history={history} /></td>
                                        <td>{formatDateTime(history.createdAt)}</td>
                                        <td className="text-center"><i className="fa fa-times text-danger" /></td>
                                    </tr>
                                );
                            })}
                            {!loading && !histories.length ? <tr><td colSpan={8} className="text-center">Chưa có lịch sử tác nghiệp.</td></tr> : null}
                        </tbody>
                    </table>
                </div>
                <div className="ps-history-footer-actions">
                    <button type="button" className="btn btn-link ps-same-phone-link" onClick={() => setSamePhone((current) => !current)}><i className="fa fa-link" /> {samePhone ? 'Chỉ xem đơn hiện tại' : 'Xem lịch sử các đơn cùng số điện thoại'}</button>
                    <button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button>
                </div>
                {data.hasMore && <div className="small-tip">Chỉ hiển thị các tác nghiệp gần nhất.</div>}
            </DialogContent>
        </Dialog>
    );
}

export function DuplicatePhoneOrdersDialog({ order, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState({ customer: null, summary: null, orders: [] });

    useEffect(() => {
        if (!open || !order?.id) return;
        let active = true;
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/purchase-history`)
            .then((payload) => active && setData(payload))
            .catch((error) => active && toast.error(error.message ?? 'Không tải được danh sách trùng số.'))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id]);

    if (!order) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-modal ps-duplicate-phone-modal" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-modal-header">
                    <DialogTitle>Danh sách trùng số: <span className="ps-history-title-code">{data.customer?.phone ?? order.customerPhone}</span></DialogTitle>
                </DialogHeader>
                <div className="ps-duplicate-phone-body">
                    <div className="ps-duplicate-summary">
                        <span>Khách hàng: <b>{data.customer?.name ?? order.customerName ?? '—'}</b></span>
                        <span>Tổng bản ghi: <b>{data.summary?.orderCount ?? 0}</b></span>
                        <span>Đã chốt: <b>{data.summary?.closedOrderCount ?? 0}</b></span>
                    </div>
                    <div className="table-responsive">
                        <table className="table table-bordered table-striped ps-duplicate-table">
                            <thead><tr><th>#</th><th>Mã đơn</th><th>Ngày data về</th><th>Nguồn</th><th>Sale / Nhóm</th><th>Tác nghiệp</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                {loading ? <tr><td colSpan={9} className="text-center">Đang tải...</td></tr> : null}
                                {!loading && (data.orders ?? []).map((item, index) => (
                                    <tr key={item.id} className={item.isSelected ? 'info' : ''}>
                                        <td className="text-center">{index + 1}</td>
                                        <td><b>{item.orderCode || `#${item.id}`}</b></td>
                                        <td>{formatDateTime(item.dataArrivedAt)}</td>
                                        <td>{item.sourceName || '—'}</td>
                                        <td>{item.saleName || '—'}<br /><span className="small-tip">{item.teamName || ''}</span></td>
                                        <td>{item.operationStage || '—'}<br /><span className="small-tip">{item.operationResult || ''}</span></td>
                                        <td>{item.products?.map((product) => `${product.name} x${product.quantity}`).join(' | ') || '—'}</td>
                                        <td className="text-right"><b>{money(item.total)}</b></td>
                                        <td>{item.closingStatusLabel || '—'}<br /><span className="small-tip">{item.deliveryStatus || ''}</span></td>
                                    </tr>
                                ))}
                                {!loading && !(data.orders ?? []).length ? <tr><td colSpan={9} className="text-center">Không có bản ghi cùng số điện thoại.</td></tr> : null}
                            </tbody>
                        </table>
                    </div>
                    <div className="ps-sale-modal-footer"><button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button></div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function BulkCloseDialog({ orderIds = [], rows = [], actionBaseUrl, open, onOpenChange }) {
    const [processing, setProcessing] = useState(false);
    const [confirmStock, setConfirmStock] = useState(false);
    const selectedRows = useMemo(() => rows.filter((row) => orderIds.includes(String(row.id))), [rows, orderIds]);

    useEffect(() => { if (!open) setConfirmStock(false); }, [open]);

    const submit = () => {
        setProcessing(true);
        router.post(`${actionBaseUrl}/orders/bulk-close`, {
            order_ids: orderIds,
            confirm_insufficient_stock: confirmStock,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã xử lý chốt các đơn được chọn.');
                onOpenChange(false);
            },
            onError: (errors) => toast.error(errors.order_ids ?? errors.order ?? 'Không thể chốt đơn hàng loạt.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-modal ps-bulk-close-modal" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-modal-header"><DialogTitle>Chốt đơn nhiều</DialogTitle></DialogHeader>
                <div className="ps-bulk-close-body">
                    <div className="alert alert-info">Đã chọn <b>{orderIds.length}</b> đơn. Mã đơn sẽ chỉ được sinh cho các đơn chốt thành công.</div>
                    <div className="table-responsive"><table className="table table-bordered table-striped"><thead><tr><th>#</th><th>Khách hàng</th><th>Điện thoại</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead><tbody>
                        {selectedRows.map((order, index) => <tr key={order.id}><td>{index + 1}</td><td>{order.customerName || '—'}</td><td>{order.customerPhone || '—'}</td><td>{order.products?.map((item) => `${item.productName} x${item.quantity}`).join(' | ') || '—'}</td><td className="text-right">{money(order.total)}</td><td>{order.closedAt ? 'Đã chốt' : 'Chưa chốt'}</td></tr>)}
                    </tbody></table></div>
                    <label className="ps-bulk-stock-confirm"><input type="checkbox" checked={confirmStock} onChange={(event) => setConfirmStock(event.target.checked)} /> Xác nhận tiếp tục nếu tồn kho không đủ</label>
                    <div className="ps-sale-modal-footer"><button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button><button type="button" className="btn btn-primary" disabled={processing || !orderIds.length} onClick={submit}><i className="fa fa-check-square-o" /> Chốt đơn</button></div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
