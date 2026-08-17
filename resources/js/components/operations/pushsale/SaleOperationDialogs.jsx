import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOrderInteractionLock } from '@/hooks/useOrderInteractionLock';
import { apiGet } from '@/lib/api';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

const money = (value) => formatCurrency(Number(value ?? 0));

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
    const [formError, setFormError] = useState('');
    const [processing, setProcessing] = useState(false);
    const ordersBase = `${String(actionBaseUrl || '/sales').replace(/\/$/, '')}/orders`;
    const { token: lockToken, error: lockError, ready: lockReady } = useOrderInteractionLock({
        orderId: order?.id,
        actionApiBase: ordersBase,
        action: 'desired_delivery',
        enabled: Boolean(open && order?.id),
    });

    useEffect(() => {
        setDate(toDateTimeLocal(order?.desiredDeliveryAt));
        setFormError('');
    }, [order?.desiredDeliveryAt, open]);

    useEffect(() => {
        if (lockError) {
            toast.error(lockError);
            onOpenChange(false);
        }
    }, [lockError, onOpenChange]);

    if (!order) return null;

    const submit = (event) => {
        event.preventDefault();
        if (!date) {
            setFormError('Vui lòng chọn ngày muốn nhận hàng.');
            return;
        }
        if (!lockToken) {
            setFormError(lockError || 'Chưa lấy được quyền thao tác đơn.');
            return;
        }
        setFormError('');
        setProcessing(true);
        router.patch(`${ordersBase}/${order.id}/desired-delivery-date`, {
            desired_delivery_at: date,
            interaction_lock_token: lockToken,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật ngày muốn nhận hàng.');
                onOpenChange(false);
            },
            onError: (errors) => setFormError(errors.desired_delivery_at ?? errors.order ?? 'Không thể cập nhật.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-dialog ps-sale-modal ps-desired-date-modal ps-desired-date-dialog" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>Cập nhật ngày muốn giao hàng</DialogTitle></DialogHeader>
                {!lockReady && open ? (
                    <div className="ps-desired-date-body">Đang khóa đơn để thao tác…</div>
                ) : (
                <form onSubmit={submit} className="ps-desired-date-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
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
                            <tr><td /><td><button className="btn btn-primary" disabled={processing || !lockToken}><i className="fa fa-floppy-o" /> Cập nhật</button></td></tr>
                        </tbody>
                    </table>
                </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export function OperationResultDialog({ order, result, open, onOpenChange, actionBaseUrl, onCloseOrder }) {
    const t = useT();
    const [nextAt, setNextAt] = useState('');
    const [note, setNote] = useState('');
    const [formError, setFormError] = useState('');
    const [processing, setProcessing] = useState(false);
    const ordersBase = `${String(actionBaseUrl || '/sales').replace(/\/$/, '')}/orders`;
    const { token: lockToken, error: lockError, ready: lockReady } = useOrderInteractionLock({
        orderId: order?.id,
        actionApiBase: ordersBase,
        action: 'operation_status',
        enabled: Boolean(open && order?.id && result),
    });

    useEffect(() => {
        if (open) {
            setNextAt(toDateTimeLocal(order?.nextOperationAt));
            setNote('');
            setFormError('');
        }
    }, [open, order?.nextOperationAt]);

    useEffect(() => {
        if (lockError) {
            toast.error(lockError);
            onOpenChange(false);
        }
    }, [lockError, onOpenChange]);

    if (!order || !result) return null;
    const needsDate = result.value === 'callback_scheduled';

    const submit = (event) => {
        event.preventDefault();
        if (result.value === 'closed_success') {
            onOpenChange(false);
            onCloseOrder(order);
            return;
        }
        if (needsDate && !nextAt) {
            setFormError('Vui lòng chọn thời gian tác nghiệp tiếp.');
            return;
        }
        if (!lockToken) {
            setFormError(lockError || 'Chưa lấy được quyền thao tác đơn.');
            return;
        }
        setFormError('');
        setProcessing(true);
        router.post(`${ordersBase}/${order.id}/operation-status`, {
            operation_result: result.value,
            next_operation_at: needsDate ? nextAt : null,
            note,
            interaction_lock_token: lockToken,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật kết quả tác nghiệp.');
                onOpenChange(false);
            },
            onError: (errors) => setFormError(errors.next_operation_at ?? errors.operation_result ?? errors.order ?? 'Không thể cập nhật tác nghiệp.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-dialog ps-sale-modal ps-operation-result-modal ps-operation-result-dialog" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>Cập nhật tác nghiệp</DialogTitle></DialogHeader>
                {!lockReady && open ? (
                    <div className="ps-operation-result-body">Đang khóa đơn để thao tác…</div>
                ) : (
                <form onSubmit={submit} className="ps-operation-result-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
                    <div className="form-group"><label>Khách hàng</label><div className="ps-static-value">{order.customerName} - {order.customerPhone}</div></div>
                    <div className="form-group"><label>Tác nghiệp cần</label><div className="ps-static-value ps-operation-needed-static"><b>{order.currentOperation || 'Gọi lần 1'}</b>{order.saleOperationNote ? <span>{order.saleOperationNote}</span> : <small>{t('operations.sale_workspace.note_empty_hint')}</small>}</div></div>
                    <div className="form-group"><label>Kết quả tác nghiệp</label><div className="ps-static-value"><b>{result.label}</b></div></div>
                    {needsDate && <div className="form-group"><label>Tác nghiệp tiếp (*)</label><input required className="form-control" type="datetime-local" value={nextAt} onChange={(event) => setNextAt(event.target.value)} /></div>}
                    <div className="form-group"><label>Ghi chú</label><textarea className="form-control" rows={4} maxLength={1000} value={note} onChange={(event) => setNote(event.target.value)} /></div>
                    <div className="ps-sale-dialog-footer"><button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button><button className="btn btn-primary" disabled={processing || !lockToken}><i className="fa fa-floppy-o" /> Cập nhật</button></div>
                </form>
                )}
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
            <DialogContent className="ps-sale-dialog ps-sale-modal ps-operation-history-modal ps-operation-history-dialog ps-dialog-surface" aria-describedby={undefined} style={{ '--ps-dialog-width': '1600px' }}>
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>{dialogTitle}: <span className="ps-history-title-code">{order.orderCode || `#${order.id}`}</span></DialogTitle></DialogHeader>
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
                                        <td>{history.action === 'note_updated' && history.note ? history.note : (history.stageBefore || history.stageAfter || '—')}</td>
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

export function DuplicatePhoneOrdersDialog({ order, open, onOpenChange, initialClosedOnly = false }) {
    const [loading, setLoading] = useState(false);
    const [closedOnly, setClosedOnly] = useState(initialClosedOnly);
    const [data, setData] = useState({ customer: null, summary: null, orders: [] });

    useEffect(() => {
        if (open) setClosedOnly(initialClosedOnly);
    }, [open, initialClosedOnly, order?.id]);

    useEffect(() => {
        if (!open || !order?.id) return;
        let active = true;
        setLoading(true);
        const query = closedOnly ? '?closed_only=1' : '';
        apiGet(`/customers/orders/${order.id}/purchase-history${query}`)
            .then((payload) => active && setData(payload))
            .catch((error) => active && toast.error(error.message ?? 'Không tải được danh sách cùng số điện thoại.'))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id, closedOnly]);

    if (!order) return null;

    const orders = data.orders ?? [];
    const summary = data.summary ?? {};

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showClose
                className="ps-sale-dialog ps-sale-modal ps-duplicate-phone-modal ps-duplicate-phone-dialog"
                aria-describedby={undefined}
            >
                <DialogHeader className="ps-sale-dialog-header ps-duplicate-phone-header">
                    <DialogTitle>Danh sách đơn cùng số điện thoại</DialogTitle>
                    <label className="ps-duplicate-closed-filter">
                        <input type="checkbox" checked={closedOnly} onChange={(event) => setClosedOnly(event.target.checked)} />
                        <span>Đã chốt đơn</span>
                    </label>
                </DialogHeader>
                <div className="ps-duplicate-phone-body">
                    <div className="ps-duplicate-summary">
                        <span>
                            Tổng đơn:
                            {' '}
                            <b>{summary.orderCount ?? orders.length}</b>
                        </span>
                        <span>
                            Đã chốt:
                            {' '}
                            <b>{summary.closedOrderCount ?? 0}</b>
                        </span>
                        <span>
                            Doanh số:
                            {' '}
                            <b>{money(summary.totalValue ?? 0)}</b>
                        </span>
                        {data.customer?.phone ? (
                            <span>
                                SĐT:
                                {' '}
                                <b>{data.customer.phone}</b>
                            </span>
                        ) : null}
                    </div>
                    <div className="ps-duplicate-table-wrap">
                        <table className="table table-bordered table-striped ps-duplicate-table">
                            <thead>
                                <tr>
                                    <th className="text-center">#</th>
                                    <th className="text-center">Mã đơn</th>
                                    <th className="text-center">Nguồn dữ liệu / Ngày data về</th>
                                    <th className="text-center">Sale / Ngày nhận data</th>
                                    <th className="text-center">Họ tên / Số điện thoại</th>
                                    <th className="text-center">Tin nhắn</th>
                                    <th className="text-center">Tác nghiệp / Ngày chốt</th>
                                    <th className="text-center">Kết quả</th>
                                    <th className="text-center">Sản phẩm - SL - Đơn giá</th>
                                    <th className="text-center">Thành tiền / CK / Tổng</th>
                                    <th className="text-center">Đặt cọc</th>
                                    <th className="text-center">Trạng thái GH / Ngày muốn nhận</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? <tr><td colSpan={12} className="text-center">Đang tải...</td></tr> : null}
                                {!loading && orders.map((item, index) => (
                                    <tr key={item.id} className={item.isSelected ? 'info' : ''}>
                                        <td className="text-center">{index + 1}</td>
                                        <td className="text-center"><b>{item.orderCode || '—'}</b></td>
                                        <td className="text-center">
                                            <b>{item.sourceName || '—'}</b>
                                            <br />
                                            <span className="small-tip">{formatDateTime(item.dataArrivedAt)}</span>
                                        </td>
                                        <td className="text-center">
                                            <b>{item.saleName || '—'}</b>
                                            {item.teamName ? <><br /><span className="small-tip">({item.teamName})</span></> : null}
                                            <br />
                                            <span className="small-tip">{formatDateTime(item.assignedAt)}</span>
                                        </td>
                                        <td>
                                            <span className="ps-customer-name-text">{item.customerName || '—'}</span>
                                            <br />
                                            <span className="ps-phone-text">{item.customerPhone || '—'}</span>
                                        </td>
                                        <td className="ps-duplicate-message">{item.customerNote || item.shippingNotes || '—'}</td>
                                        <td className="text-center">
                                            {item.operationStage || '—'}
                                            <br />
                                            <span className="small-tip">{item.closedAt ? formatDateTime(item.closedAt) : '—'}</span>
                                        </td>
                                        <td className="text-center">{item.operationResult || item.closingStatusLabel || '—'}</td>
                                        <td className="text-left">
                                            {(item.products ?? []).length
                                                ? (item.products ?? []).map((product) => (
                                                    <div key={product.id || `${product.name}-${product.quantity}`}>
                                                        {product.name}
                                                        {' '}
                                                        x
                                                        {product.quantity}
                                                        {' · '}
                                                        {money(product.unitPrice)}
                                                    </div>
                                                ))
                                                : '—'}
                                        </td>
                                        <td className="text-right ps-ops-money-cell">
                                            <div className="ps-order-money-breakdown">
                                                {item.subtotal ? <div className="ps-order-money-line">{money(item.subtotal)}</div> : null}
                                                {item.discount ? <div className="ps-order-money-line">-{money(item.discount)}</div> : null}
                                                <div className="ps-order-money-line is-total">{money(item.total)}</div>
                                            </div>
                                        </td>
                                        <td className="text-right">{money(item.deposit)}</td>
                                        <td className="text-center">
                                            {item.deliveryStatus || '—'}
                                            <br />
                                            <span className="item-mdgv">{item.trackingNumber || '—'}</span>
                                            <br />
                                            <span className="small-tip">{item.desiredDeliveryAt ? formatDateTime(item.desiredDeliveryAt) : ''}</span>
                                        </td>
                                    </tr>
                                ))}
                                {!loading && !orders.length ? <tr><td colSpan={12} className="text-center">Không có đơn cùng số điện thoại.</td></tr> : null}
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function BulkCloseDialog({ orderIds = [], rows = [], actionBaseUrl, open, onOpenChange }) {
    const [processing, setProcessing] = useState(false);
    const [confirmStock, setConfirmStock] = useState(false);
    const [formError, setFormError] = useState('');
    const selectedRows = useMemo(() => rows.filter((row) => orderIds.includes(String(row.id))), [rows, orderIds]);

    useEffect(() => {
        if (!open) {
            setConfirmStock(false);
            setFormError('');
        }
    }, [open]);

    const submit = () => {
        if (!orderIds.length) {
            setFormError('Chưa chọn đơn nào để chốt.');
            return;
        }
        setFormError('');
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
            onError: (errors) => setFormError(errors.order_ids ?? errors.order ?? 'Không thể chốt đơn hàng loạt.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-dialog ps-sale-modal ps-bulk-close-modal ps-bulk-close-dialog" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>Chốt đơn nhiều</DialogTitle></DialogHeader>
                <div className="ps-bulk-close-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
                    <div className="alert alert-info">Đã chọn <b>{orderIds.length}</b> đơn. Mã đơn sẽ chỉ được sinh cho các đơn chốt thành công.</div>
                    <div className="table-responsive"><table className="table table-bordered table-striped"><thead><tr><th>#</th><th>Khách hàng</th><th>Điện thoại</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead><tbody>
                        {selectedRows.map((order, index) => <tr key={order.id}><td>{index + 1}</td><td>{order.customerName || '—'}</td><td>{order.customerPhone || '—'}</td><td>{order.products?.map((item) => `${item.productName} x${item.quantity}`).join(' | ') || '—'}</td><td className="text-right">{money(order.total)}</td><td>{order.closedAt ? 'Đã chốt' : 'Chưa chốt'}</td></tr>)}
                    </tbody></table></div>
                    <label className="ps-bulk-stock-confirm"><input type="checkbox" checked={confirmStock} onChange={(event) => setConfirmStock(event.target.checked)} /> Xác nhận tiếp tục nếu tồn kho không đủ</label>
                    <div className="ps-sale-dialog-footer"><button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button><button type="button" className="btn btn-primary" disabled={processing || !orderIds.length} onClick={submit}><i className="fa fa-check-square-o" /> Chốt đơn</button></div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
