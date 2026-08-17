import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { apiGet, apiPost } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function LoadingBlock() {
    return <div className="ps-customer-dialog-loading"><i className="fa fa-spinner fa-spin" /> Đang tải dữ liệu…</div>;
}

function EmptyRow({ colSpan, text = 'Không có dữ liệu' }) {
    return <tr><td colSpan={colSpan} className="text-center ps-empty-cell">{text}</td></tr>;
}

function CustomerDialogShell({ open, onOpenChange, width = '800px', title, description, children, footer, className = '' }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            width={width}
            title={title}
            description={description}
            className={`ps-customer-dialog ${className}`.trim()}
            footer={footer}
        >
            {children}
        </PushsaleDialog>
    );
}

function messageAuthor(message) {
    return message.authorName ?? message.senderName ?? '—';
}

function messageDate(message) {
    return message.createdAt ?? message.sentAt;
}

function messageDirectionClass(message = {}) {
    const direction = String(message.direction ?? message.messageDirection ?? '').toLowerCase();
    if (direction === 'outbound' || direction === 'sale' || direction === 'agent') return 'is-outbound';
    return 'is-inbound';
}

export function PushsaleCustomerMessagesDialog({ order, open, onOpenChange }) {
    const t = useT();
    const [loading, setLoading] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [messages, setMessages] = useState([]);
    const [canWrite, setCanWrite] = useState(false);
    const [samePhone, setSamePhone] = useState(false);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);

    useEffect(() => {
        if (!open || !order?.id) return;
        let active = true;
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/messages`)
            .then((data) => {
                if (!active) return;
                setCustomer(data.customer ?? null);
                setMessages(data.messages ?? []);
                setCanWrite(Boolean(data.canWrite));
            })
            .catch((error) => active && toast.error(error.message ?? t('operations.customer_interactions.load_failed')))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id, t]);

    useEffect(() => {
        if (!open) {
            setDraft('');
            setSamePhone(false);
        }
    }, [open]);

    const send = async () => {
        const content = draft.trim();
        if (!content || sending || !canWrite) return;
        setSending(true);
        try {
            const data = await apiPost(`/customers/orders/${order.id}/messages`, { message: content });
            setMessages((current) => [...current, data.message]);
            setDraft('');
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.send_failed'));
        } finally {
            setSending(false);
        }
    };

    const visibleMessages = useMemo(() => {
        if (samePhone || !order?.id) return messages;
        return messages.filter((message) => !message.orderId || String(message.orderId) === String(order.id));
    }, [messages, samePhone, order?.id]);

    return (
        <CustomerDialogShell
            open={open}
            onOpenChange={onOpenChange}
            width="980px"
            title={`${t('operations.customer_interactions.messages_title')}: ${customer?.name ?? order?.customerName ?? '-'}`}
        >
            <div className="ps-message-dialog-card">
                <div className="ps-message-dialog-head">
                    <div>
                        <div className="ps-message-dialog-customer">{customer?.name ?? order?.customerName ?? '—'} / {customer?.phone ?? order?.customerPhone ?? '—'}</div>
                        <div className="ps-message-dialog-meta">{customer?.note ?? order?.messageDisplay ?? order?.customerNote ?? customer?.address ?? order?.effectiveShippingAddress ?? '—'}</div>
                    </div>
                    <div className="ps-message-dialog-meta text-right">
                        <div>{t('operations.customer_interactions.internal_messages_title')}</div>
                    </div>
                </div>

                <div className="ps-message-composer">
                    <input
                        type="text"
                        className="form-control"
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                send();
                            }
                        }}
                        disabled={!canWrite || sending}
                        placeholder={canWrite ? t('operations.customer_interactions.message_placeholder') : t('operations.customer_interactions.read_only')}
                    />
                    <button type="button" className="btn btn-primary" disabled={!canWrite || !draft.trim() || sending} onClick={send}>
                        {sending ? <i className="fa fa-spinner fa-spin" /> : null} {t('operations.customer_interactions.send')}
                    </button>
                </div>

                <div className="ps-message-list">
                    {loading ? <LoadingBlock /> : visibleMessages.length ? visibleMessages.map((message) => (
                        <div className={`ps-message-row ${messageDirectionClass(message)} is-internal`} key={message.id ?? message.externalId ?? `${messageAuthor(message)}-${messageDate(message)}`}>
                            <div className="ps-message-meta">
                                <strong>{messageAuthor(message)}</strong>
                                {message.orderCode ? <span> · {message.orderCode}</span> : null}
                                <span className="pull-right">{formatDateTime(messageDate(message))}</span>
                            </div>
                            <div className="ps-message-text">{message.message ?? '—'}</div>
                        </div>
                    )) : <div className="ps-empty-message">{t('operations.customer_interactions.messages_empty')}</div>}
                </div>

                <div className="ps-message-same-phone">
                    <button
                        type="button"
                        className="btn-link ps-same-phone-link"
                        onClick={() => setSamePhone((current) => !current)}
                    >
                        <i className="fa fa-arrow-down" aria-hidden="true" />
                        {' '}
                        {t(samePhone
                            ? 'operations.customer_interactions.same_phone_off'
                            : 'operations.customer_interactions.same_phone_link')}
                    </button>
                </div>
            </div>
        </CustomerDialogShell>
    );
}

export function PushsaleOperationHistoryDialog({ order, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);
    const [samePhone, setSamePhone] = useState(false);
    const [data, setData] = useState({ customer: null, histories: [], hasMore: false });

    useEffect(() => {
        if (!open || !order?.id) return;
        let active = true;
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/operation-history${samePhone ? '?same_phone=1' : ''}`)
            .then((result) => active && setData(result))
            .catch((error) => active && toast.error(error.message ?? 'Không tải được lịch sử tác nghiệp.'))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id, samePhone]);

    useEffect(() => {
        if (!open) setSamePhone(false);
    }, [open]);

    return (
        <CustomerDialogShell
            open={open}
            onOpenChange={onOpenChange}
            width="1728px"
            className="ps-operation-history-dialog"
            title={`Lịch sử tác nghiệp: ${data.customer?.name ?? order?.customerName ?? '-'}`}
            footer={<button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button>}
        >
            {loading ? <LoadingBlock /> : (
                <div className="table-responsive ps-history-table-wrap">
                    <table className="table table-bordered table-striped ps-history-table">
                        <thead>
                            <tr>
                                <th style={{ width: 42 }}>#</th>
                                <th>Hoạt động</th>
                                <th>Tác nghiệp cần</th>
                                <th>KQ tác nghiệp</th>
                                <th>Tác nghiệp tiếp</th>
                                <th>Ghi chú</th>
                                <th>Ngày cập nhật</th>
                                <th style={{ width: 30 }}>X</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.histories?.length ? data.histories.map((history, index) => (
                                <tr key={history.id}>
                                    <td className="text-center">{index + 1}</td>
                                    <td>
                                        <strong>{history.actionLabel}</strong><br />
                                        <span className="small-tip">{history.actorName}{history.actorRole ? ` (${history.actorRole})` : ''}</span>
                                    </td>
                                    <td>{history.action === 'note_updated' && history.note ? history.note : (history.stageBefore || '—')}</td>
                                    <td>{history.result || '—'}</td>
                                    <td>
                                        {history.stageAfter || '—'}
                                        {history.nextOperationAt ? <><br /><span className="small-tip">{formatDateTime(history.nextOperationAt)}</span></> : null}
                                    </td>
                                    <td>{history.note || '—'}</td>
                                    <td>{formatDateTime(history.createdAt)}</td>
                                    <td className="text-center"></td>
                                </tr>
                            )) : <EmptyRow colSpan={8} />}
                        </tbody>
                    </table>
                </div>
            )}
            <button type="button" className="ps-same-phone-link" onClick={() => setSamePhone((current) => !current)}>
                <i className="fa fa-link" /> {samePhone ? 'Chỉ xem lịch sử của đơn hiện tại' : 'Xem danh sách lịch sử các đơn cùng số điện thoại'}
            </button>
            {data.hasMore ? <div className="small-tip">Chỉ hiển thị 200 lịch sử gần nhất.</div> : null}
        </CustomerDialogShell>
    );
}

export function PushsalePurchaseHistoryDialog({ order, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState({ customer: null, summary: null, orders: [] });

    useEffect(() => {
        if (!open || !order?.id) return;
        let active = true;
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/purchase-history`)
            .then((result) => active && setData(result))
            .catch((error) => active && toast.error(error.message ?? 'Không tải được lịch sử mua hàng.'))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [open, order?.id]);

    const summary = data.summary ?? {};

    return (
        <CustomerDialogShell
            open={open}
            onOpenChange={onOpenChange}
            width="1480px"
            title={`Lịch sử mua hàng: ${data.customer?.name ?? order?.customerName ?? '-'}`}
            description={`${data.customer?.phone ?? order?.customerPhone ?? '—'} · ${data.customer?.address ?? order?.effectiveShippingAddress ?? ''}`}
            className="ps-purchase-history-dialog"
            footer={<button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button>}
        >
            {loading ? <LoadingBlock /> : (
                <>
                    <div className="ps-purchase-summary">
                        <div><span>Tổng đơn</span><strong>{summary.orderCount ?? 0}</strong></div>
                        <div><span>Đơn chốt</span><strong>{summary.closedOrderCount ?? 0}</strong></div>
                        <div><span>Số lượng SP</span><strong>{summary.totalQuantity ?? 0}</strong></div>
                        <div><span>Tổng giá trị</span><strong>{formatCurrency(summary.totalValue ?? 0)}</strong></div>
                    </div>
                    <div className="table-responsive ps-history-table-wrap">
                        <table className="table table-bordered table-striped ps-history-table ps-purchase-history-table">
                            <colgroup>
                                <col className="c-idx" /><col className="c-code" /><col className="c-date" /><col className="c-sale" />
                                <col className="c-ops" /><col className="c-products" /><col className="c-total" /><col className="c-wh" /><col className="c-status" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Mã đơn</th>
                                    <th>Ngày data về</th>
                                    <th>Sale</th>
                                    <th>Tác nghiệp / Kết quả</th>
                                    <th>Sản phẩm</th>
                                    <th>Tổng tiền</th>
                                    <th>Kho / Giao vận</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.orders?.length ? data.orders.map((item, index) => (
                                    <tr key={item.id} className={item.isSelected ? 'info is-selected' : ''}>
                                        <td className="text-center">{index + 1}</td>
                                        <td className="text-center">
                                            <div className="ps-purchase-code-cell">
                                                <strong>{item.orderCode || '—'}</strong>
                                                <OrderStatusFlags row={item} className="ps-contact-flags" showUpsell={false} />
                                            </div>
                                        </td>
                                        <td className="text-center">{formatDateTime(item.dataArrivedAt) || '—'}</td>
                                        <td>
                                            <div className="ps-stack-cell">
                                                <span className="ps-stack-primary">{item.saleName || '—'}</span>
                                                {item.teamName ? <span className="ps-stack-secondary">{item.teamName}</span> : null}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="ps-stack-cell">
                                                <span className="ps-stack-primary">{item.operationStage || '—'}</span>
                                                <span className="ps-stack-secondary">{item.operationResult || ''}</span>
                                            </div>
                                        </td>
                                        <td>
                                            {item.products?.length ? item.products.map((product, productIndex) => (
                                                <div className="ps-purchase-product-line" key={`${item.id}-${productIndex}`}>
                                                    <span className="ps-purchase-product-name" title={product.name}>{product.name || '—'}</span>
                                                    <span className="ps-purchase-product-qty">x{product.quantity ?? 0}</span>
                                                </div>
                                            )) : '—'}
                                        </td>
                                        <td className="text-right"><strong>{formatCurrency(item.total)}</strong></td>
                                        <td>
                                            <div className="ps-stack-cell">
                                                <span className="ps-stack-primary">{item.warehouseName || '—'}</span>
                                                <span className="ps-stack-secondary">{item.trackingNumber || item.shippingMethod || ''}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="ps-stack-cell">
                                                <span className="ps-stack-primary">{item.closingStatusLabel || '—'}</span>
                                                <span className="ps-stack-secondary">{item.deliveryStatus || ''}</span>
                                            </div>
                                        </td>
                                    </tr>
                                )) : <EmptyRow colSpan={9} />}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </CustomerDialogShell>
    );
}

export function PushsaleDataViewHistoryDialog({ order, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState({ logs: [], counts: [], users: [], filters: {} });
    const [filters, setFilters] = useState({ date_from: '', date_to: '', user_id: '' });

    const load = (nextFilters = filters) => {
        if (!order?.id) return;
        const query = new URLSearchParams(Object.entries(nextFilters).filter(([, value]) => value !== '' && value != null));
        setLoading(true);
        apiGet(`/customers/orders/${order.id}/data-view-history?${query.toString()}`)
            .then((result) => {
                setData(result);
                setFilters({
                    date_from: result.filters?.date_from ?? nextFilters.date_from ?? '',
                    date_to: result.filters?.date_to ?? nextFilters.date_to ?? '',
                    user_id: result.filters?.user_id ? String(result.filters.user_id) : (nextFilters.user_id ?? ''),
                });
            })
            .catch((error) => toast.error(error.message ?? 'Không tải được lịch sử xem data.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (open) load({ date_from: '', date_to: '', user_id: '' });
    }, [open, order?.id]);

    return (
        <CustomerDialogShell
            open={open}
            onOpenChange={onOpenChange}
            width="1728px"
            title={`Lịch sử xem data (${order?.id ?? '-'} - ${order?.orderCode ?? '-'})`}
            footer={<button type="button" className="btn btn-default" onClick={() => onOpenChange(false)}>Đóng</button>}
        >
            <div className="ps-view-log-filter form-inline">
                <input type="date" className="form-control" value={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} />
                <span> - </span>
                <input type="date" className="form-control" value={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} />
                <select className="form-control" value={filters.user_id} onChange={(event) => setFilters((current) => ({ ...current, user_id: event.target.value }))}>
                    <option value="">--Chọn username--</option>
                    {data.users?.map((user) => <option key={user.value} value={user.value}>{user.label}</option>)}
                </select>
                <button type="button" className="btn btn-primary" onClick={() => load()}><i className="fa fa-search" /> Tìm kiếm</button>
            </div>

            {loading ? <LoadingBlock /> : (
                <div className="row ps-view-log-grid">
                    <div className="col-md-9">
                        <div className="table-responsive">
                            <table className="table table-bordered table-striped ps-history-table">
                                <thead><tr><th>#</th><th>Mã đơn</th><th>Hành động xem</th><th>User</th><th>Ngày lọc</th></tr></thead>
                                <tbody>
                                    {data.logs?.length ? data.logs.map((log, index) => (
                                        <tr key={log.id}>
                                            <td className="text-center">{index + 1}</td><td>{log.orderCode || '—'}</td><td>{log.action}</td>
                                            <td>{log.userName}<br /><span className="small-tip">{log.userEmail || ''}</span></td><td>{formatDateTime(log.createdAt)}</td>
                                        </tr>
                                    )) : <EmptyRow colSpan={5} />}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className="col-md-3">
                        <div className="table-responsive">
                            <table className="table table-bordered table-striped ps-history-table">
                                <thead><tr><th>#</th><th>User</th><th>Count</th></tr></thead>
                                <tbody>
                                    {data.counts?.length ? data.counts.map((count, index) => (
                                        <tr key={`${count.userId}-${index}`}><td>{index + 1}</td><td>{count.userName}</td><td className="text-center">{count.count}</td></tr>
                                    )) : <EmptyRow colSpan={3} />}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
            <div className="text-danger ps-view-log-note">* Hệ thống chỉ lưu và hiển thị lịch sử xem data trong 30 ngày gần nhất.</div>
        </CustomerDialogShell>
    );
}
