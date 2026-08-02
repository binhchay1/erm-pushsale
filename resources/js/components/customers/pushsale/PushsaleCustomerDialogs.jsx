import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
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
    const [tab, setTab] = useState('internal');
    const [loading, setLoading] = useState(false);
    const [customer, setCustomer] = useState(null);
    const [messages, setMessages] = useState([]);
    const [canWrite, setCanWrite] = useState(false);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [pancake, setPancake] = useState({ loaded: false, loading: false, messages: [], canWrite: false, connected: false });

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
    }, [open, order?.id]);

    useEffect(() => {
        if (!open || tab !== 'pancake' || pancake.loaded || !order?.id) return;
        let active = true;
        setPancake((current) => ({ ...current, loading: true }));
        apiGet(`/customers/orders/${order.id}/pancake-messages`)
            .then((data) => {
                if (!active) return;
                setPancake({
                    loaded: true,
                    loading: false,
                    messages: data.messages ?? [],
                    canWrite: Boolean(data.canWrite),
                    connected: Boolean(data.connected),
                    source: data.source,
                    reason: data.reason,
                    pageId: data.pageId,
                    conversationId: data.conversationId,
                    realtime: data.realtime,
                });
            })
            .catch((error) => {
                if (!active) return;
                toast.error(error.message ?? t('operations.customer_interactions.load_failed'));
                setPancake((current) => ({ ...current, loaded: true, loading: false }));
            });
        return () => { active = false; };
    }, [open, order?.id, pancake.loaded, tab]);

    useEffect(() => {
        if (!open) {
            setTab('internal');
            setDraft('');
            setPancake({ loaded: false, loading: false, messages: [], canWrite: false, connected: false });
        }
    }, [open]);

    const currentMessages = tab === 'internal' ? messages : pancake.messages;
    const currentCanWrite = tab === 'internal' ? canWrite : pancake.canWrite;
    const currentLoading = tab === 'internal' ? loading : pancake.loading;

    const send = async () => {
        const content = draft.trim();
        if (!content || sending || !currentCanWrite) return;
        setSending(true);
        try {
            const endpoint = tab === 'internal'
                ? `/customers/orders/${order.id}/messages`
                : `/customers/orders/${order.id}/pancake-messages`;
            const data = await apiPost(endpoint, { message: content });
            if (tab === 'internal') {
                setMessages((current) => [...current, data.message]);
            } else {
                setPancake((current) => ({ ...current, messages: [...current.messages, data.message] }));
            }
            setDraft('');
        } catch (error) {
            toast.error(error.message ?? t('operations.customer_interactions.send_failed'));
        } finally {
            setSending(false);
        }
    };

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
                        <div className="ps-message-dialog-meta">{customer?.address ?? order?.effectiveShippingAddress ?? '—'}</div>
                    </div>
                    <div className="ps-message-dialog-meta text-right">
                        <div>{tab === 'pancake' ? t('operations.customer_interactions.pancake_messages_title') : t('operations.customer_interactions.internal_messages_title')}</div>
                        {pancake.source?.pageName ? <div>Page: {pancake.source.pageName}</div> : null}
                    </div>
                </div>

                <div className="ps-customer-tabs ps-message-tabs">
                    <button type="button" className={tab === 'internal' ? 'active' : ''} onClick={() => setTab('internal')}>{t('operations.customer_interactions.internal_tab')}</button>
                    <button type="button" className={tab === 'pancake' ? 'active' : ''} onClick={() => setTab('pancake')}>{t('operations.customer_interactions.pancake_tab')}</button>
                </div>

                {tab === 'pancake' && pancake.loaded && !pancake.connected ? (
                    <div className="alert alert-warning ps-compact-alert">{t('operations.customer_interactions.pancake_not_connected')}</div>
                ) : null}
                {tab === 'pancake' && pancake.loaded && pancake.connected && pancake.source === 'cache' ? (
                    <div className="alert alert-warning ps-compact-alert">{t('operations.customer_interactions.pancake_cache_notice')}</div>
                ) : null}
                {tab === 'pancake' && pancake.loaded && pancake.connected && pancake.source === 'error' ? (
                    <div className="alert alert-danger ps-compact-alert">{t('operations.customer_interactions.pancake_error_notice')}</div>
                ) : null}
                {tab === 'pancake' && pancake.loaded && pancake.connected && pancake.conversationId ? (
                    <div className="ps-pancake-thread-meta">
                        <span>Page ID: {pancake.pageId ?? '—'}</span>
                        <span>Conversation ID: {pancake.conversationId}</span>
                    </div>
                ) : null}

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
                        disabled={!currentCanWrite || sending}
                        placeholder={currentCanWrite ? (tab === 'pancake' ? t('operations.customer_interactions.pancake_message_placeholder') : t('operations.customer_interactions.message_placeholder')) : t('operations.customer_interactions.read_only')}
                    />
                    <button type="button" className="btn btn-primary" disabled={!currentCanWrite || !draft.trim() || sending} onClick={send}>
                        {sending ? <i className="fa fa-spinner fa-spin" /> : null} {t('operations.customer_interactions.send')}
                    </button>
                </div>

                <div className="ps-message-list">
                    {currentLoading ? <LoadingBlock /> : currentMessages.length ? currentMessages.map((message) => (
                        <div className={`ps-message-row ${messageDirectionClass(message)} ${tab === 'pancake' ? 'is-pancake' : 'is-internal'}`} key={`${tab}-${message.id ?? message.externalId ?? Math.random()}`}>
                            <div className="ps-message-meta">
                                <strong>{messageAuthor(message)}</strong>
                                {message.orderCode ? <span> · {message.orderCode}</span> : null}
                                <span className="pull-right">{formatDateTime(messageDate(message))}</span>
                            </div>
                            <div className="ps-message-text">{message.message ?? '—'}</div>
                        </div>
                    )) : <div className="ps-empty-message">{tab === 'pancake' ? t('operations.customer_interactions.pancake_messages_empty') : t('operations.customer_interactions.messages_empty')}</div>}
                </div>
            </div>

            <button type="button" className="ps-same-phone-link" onClick={() => setTab('internal')}>
                <i className="fa fa-link" /> {t('operations.customer_interactions.same_phone_link')}
            </button>
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
                                        <td className="text-center"><strong>{item.orderCode || '—'}</strong></td>
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
