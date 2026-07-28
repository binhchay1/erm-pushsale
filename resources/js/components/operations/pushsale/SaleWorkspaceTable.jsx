import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { CustomerSupplementPacketsDialog } from '@/components/customers/CustomerSupplementPacketsDialog';
import { OrderMoneyBreakdown, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';
import { useConfirm } from '@/hooks/use-confirm';
import { PushsalePagination } from './PushsalePagination';

const money = (value) => formatCurrency(Number(value ?? 0));
const externalHref = (url) => {
    const value = String(url ?? '').trim();
    if (!value) return null;
    if (/^(https?:)?\/\//i.test(value)) return value.startsWith('//') ? `https:${value}` : value;
    return `https://${value}`;
};
const dateTime = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date);
};

function TimeRemaining({ order }) {
    const [, tick] = useState(0);
    useEffect(() => {
        const timer = window.setInterval(() => tick((value) => value + 1), 60000);
        return () => window.clearInterval(timer);
    }, []);

    if (!order.nextOperationAt) return <span className="ps-time-empty">—</span>;
    const milliseconds = new Date(order.nextOperationAt).getTime() - Date.now();
    const absoluteMinutes = Math.floor(Math.abs(milliseconds) / 60000);
    const hours = Math.floor(absoluteMinutes / 60);
    const minutes = absoluteMinutes % 60;
    return (
        <span className={milliseconds < 0 ? 'ps-time-overdue' : 'ps-time-active'}>
            {milliseconds < 0 ? 'Quá ' : 'Còn '}{hours ? `${hours}h ` : ''}{minutes}p
        </span>
    );
}

function OperationNoteEditor({ order, actionBaseUrl, onMessages }) {
    const t = useT();
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);
    const textareaRef = useRef(null);

    useEffect(() => {
        const node = textareaRef.current;
        if (!node) return;
        node.style.height = '48px';
        node.style.height = `${Math.min(160, Math.max(48, node.scrollHeight))}px`;
    }, [value]);

    const save = () => {
        setSaving(true);
        router.patch(`${actionBaseUrl}/orders/${order.id}/operation-note`, { note: value }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('operations.sale_workspace.note_saved'));
                setValue('');
            },
            onError: (errors) => toast.error(errors.note ?? errors.order ?? t('operations.sale_workspace.note_save_failed')),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="ps-operation-note-editor area2">
            <span className="fb span-col ttgh7 ps-operation-stage-label">{order.currentOperation || t('operations.sale_workspace.default_stage')}</span>
            <div className="mof-container ps-note-mof">
                <button
                    type="button"
                    className="btn-icon aoh ps-note-ear ps-note-ear-left"
                    onClick={() => onMessages(order)}
                    title={t('operations.sale_workspace.internal_message')}
                >
                    <i className="fa fa-commenting-o" />
                </button>
                <button
                    type="button"
                    className="btn-icon aoh ps-note-ear ps-note-ear-right"
                    onClick={save}
                    disabled={saving}
                    title={t('operations.sale_workspace.save_note')}
                >
                    <i className="fa fa-save" />
                </button>
                <textarea
                    ref={textareaRef}
                    className="form-control txt-mof txt-dotted ps-note-inline-textarea"
                    maxLength={500}
                    rows={2}
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onKeyDown={(event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') save();
                    }}
                    aria-label={t('operations.sale_workspace.note_aria', { name: order.customerName ?? order.customerPhone })}
                    title={t('operations.sale_workspace.note_helper')}
                    placeholder=""
                />
            </div>
        </div>
    );
}

function CallButton({ order, actionBaseUrl }) {
    const call = () => router.post(`${actionBaseUrl}/orders/${order.id}/call`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã ghi nhận cuộc gọi.'),
        onError: (errors) => toast.error(errors.order ?? 'Không thể ghi nhận cuộc gọi.'),
    });
    return <button type="button" className="btn-icon" onClick={call} disabled={!order.canCall} title="Ghi nhận cuộc gọi"><i className="fa fa-phone" /></button>;
}

export function SaleWorkspaceTable({
    rows,
    meta,
    filters,
    routeUrl,
    actionBaseUrl,
    operationStatusOptions = [],
    onEdit,
    onHistory,
    onDataViewHistory,
    onMessages,
    onPurchaseHistory,
    onDuplicateOrders,
    onDesiredDate,
    onResult,
    onBulkClose,
}) {
    const { ask } = useConfirm();
    const [selected, setSelected] = useState([]);
    const checkAllRef = useRef(null);
    const rowIds = useMemo(() => rows.map((row) => String(row.id)), [rows]);
    const allSelected = rowIds.length > 0 && rowIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    useEffect(() => {
        setSelected((current) => current.filter((id) => rowIds.includes(id)));
    }, [rowIds.join('|')]);

    useEffect(() => {
        if (checkAllRef.current) checkAllRef.current.indeterminate = someSelected;
    }, [someSelected]);

    const toggleAll = () => setSelected(allSelected ? [] : rowIds);
    const toggle = (id) => setSelected((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
    const deleteData = async (order) => {
        const ok = await ask({
            description: `Bạn chắc chắn muốn xóa data của ${order.customerName || order.customerPhone || `#${order.id}`}?`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${actionBaseUrl}/orders/${order.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa data.'),
            onError: (errors) => toast.error(errors.order ?? 'Không thể xóa data.'),
        });
    };

    return (
        <>
            <div className="table-responsive ps-sale-table-wrap">
                <table className="table table-bordered table-striped table-sale ps-sale-operation-table">
                    <colgroup>
                        <col className="c-check" /><col className="c-code" /><col className="c-source" /><col className="c-sale" /><col className="c-customer" /><col className="c-message" /><col className="c-needed" /><col className="c-result" /><col className="c-next" /><col className="c-time" /><col className="c-products" /><col className="c-money" /><col className="c-deposit" /><col className="c-delivery" />
                    </colgroup>
                    <thead><tr>
                        <th><input ref={checkAllRef} type="checkbox" checked={allSelected} onChange={toggleAll} aria-label="Chọn tất cả" /></th>
                        <th>Mã đơn</th>
                        <th>Nguồn dữ liệu<br />Ngày data về</th>
                        <th>Sale<br />Ngày nhận data</th>
                        <th>Họ tên<br />Số điện thoại<br />Ngày muốn nhận hàng</th>
                        <th>Tin nhắn</th>
                        <th>TN cần</th>
                        <th>Kết quả</th>
                        <th>TN tiếp</th>
                        <th>Sau<br />Còn lại</th>
                        <th>Sản phẩm - Số lượng - Đơn giá</th>
                        <th>Thành tiền<br />CK / VAT SP<br />Phí VC / Tổng tiền</th>
                        <th>Đặt cọc</th>
                        <th>Trạng thái giao hàng<br />Ngày muốn nhận hàng</th>
                    </tr></thead>
                    <tbody>
                        {rows.map((order, index) => {
                            const id = String(order.id);
                            const isSelected = selected.includes(id);
                            return (
                                <tr key={order.id} className={`contact-row item${order.id} ${order.closedAt ? 'is-closed' : ''} ${isSelected ? 'row-selected' : ''}`}>
                                    <td className="text-center">
                                        <input type="checkbox" checked={isSelected} onChange={() => toggle(id)} aria-label={`Chọn ${order.customerName ?? order.customerPhone}`} />
                                        <div className="ps-row-number">{Number(meta?.from ?? 1) + index}</div>
                                    </td>
                                    <td className="text-center ps-code-cell">
                                        <div className="ps-order-code-stack">
                                            {order.orderCode ? (
                                                <button type="button" className="ps-order-code-link" onClick={() => onDataViewHistory(order)}>{order.orderCode}</button>
                                            ) : <span className="ps-order-code-empty" title="Mã đơn chỉ sinh sau khi chốt đơn">&nbsp;</span>}
                                            <button type="button" className="btn-icon ps-cell-action" onClick={() => onDataViewHistory(order)} title="Lịch sử xem thông tin số"><i className="fa fa-history" /></button>
                                        </div>
                                    </td>
                                    <td className="text-center">
                                        {externalHref(order.sourceUrl) ? (
                                            <a href={externalHref(order.sourceUrl)} target="_blank" rel="noopener noreferrer" title={order.sourceUrl}>
                                                {order.sourceName}
                                            </a>
                                        ) : (
                                            <span>{order.sourceName}</span>
                                        )}<br />
                                        <span className="small-tip">({dateTime(order.dataArrivedAt)})</span>
                                    </td>
                                    <td className="text-center ps-sale-cell area5">
                                        <div className="ps-sale-cell-inner">
                                            {order.canDeleteData ? (
                                                <button type="button" className="btn-icon aoh ps-sale-delete" onClick={() => deleteData(order)} title="Xóa data" aria-label="Xóa data">
                                                    <i className="fa fa-trash" />
                                                </button>
                                            ) : null}
                                            <div className="ps-sale-name-block">
                                                <b>{order.saleName}</b>
                                                {order.saleUsername ? <span className="small-tip">({order.saleUsername})</span> : null}
                                            </div>
                                            <div className="small-tip">({dateTime(order.assignedAt)})</div>
                                        </div>
                                    </td>
                                    <td className="ps-customer-cell area1 ps-contact-name-phone" title={`${order.id} | ${order.sourceType || ''}`}>
                                        <div className="text-right ps-customer-edit-wrap">
                                            <button type="button" className="btn-icon aoh ps-cell-action" onClick={() => onEdit(order, false)} title="Cập nhật đơn"><i className="fa fa-edit" /></button>
                                        </div>
                                        <button type="button" className="ps-customer-name-link" onClick={() => onPurchaseHistory(order)}>{order.customerName || '—'}</button>
                                        {(order.phoneCarrier || order.carrierLabel) ? (
                                            <span className={`nha-mang ps-carrier ps-carrier-${order.phoneCarrierKey || ''}`}>
                                                {order.carrierLabel || `[${order.phoneCarrier}]`}
                                            </span>
                                        ) : null}
                                        <div className="no-wrap ps-contact-phone-row">
                                            <div className="ps-phone-main">
                                                <button type="button" className="ps-phone-link" onClick={() => onDuplicateOrders(order)} title="Danh sách trùng số">{order.customerPhone}</button>
                                                <CallButton order={order} actionBaseUrl={actionBaseUrl} />
                                            </div>
                                            <OrderStatusFlags row={order} onDuplicate={onDuplicateOrders ? () => onDuplicateOrders(order) : null} className="ps-contact-flags" />
                                        </div>
                                        <div className="text-left khkn sline">{order.customerExtraNote || ''}</div>
                                        {order.desiredDeliveryAt && <span className="small-tip">{dateTime(order.desiredDeliveryAt)}</span>}
                                        {order.pendingSupplementCount > 0 && <CustomerSupplementPacketsDialog order={order} count={order.pendingSupplementCount} />}
                                    </td>
                                    <td className="ps-message-cell area1">
                                        <span className="td-message" title={order.customerNote || ''} onClick={() => onMessages(order)}>
                                            {order.customerNote || '—'}
                                        </span>
                                    </td>
                                    <td><OperationNoteEditor order={order} actionBaseUrl={actionBaseUrl} onMessages={onMessages} /></td>
                                    <td className="ps-result-cell">
                                        <div className="ps-result-cell-inner">
                                            <div className="ps-result-icon-row">
                                                <button type="button" className="btn-icon ps-cell-action" onClick={() => onHistory(order)} title="Lịch sử tác nghiệp"><i className="fa fa-history" /></button>
                                            </div>
                                            {order.canChangeStatus ? (
                                                <select className="form-control ps-result-select" value="" onChange={(event) => {
                                                    const option = operationStatusOptions.find((item) => item.value === event.target.value);
                                                    if (option) onResult(order, option);
                                                    event.target.value = '';
                                                }}>
                                                    <option value="">--Chọn--</option>
                                                    {operationStatusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                                </select>
                                            ) : (
                                                <b className="ps-result-label">{order.operationResult || order.closingStatusLabel || ''}</b>
                                            )}
                                        </div>
                                    </td>
                                    <td className="text-center ps-next-cell">
                                        <button type="button" className="btn-icon ps-cell-action" onClick={() => onDesiredDate(order)} title="Cập nhật tác nghiệp tiếp"><i className="fa fa-undo" /></button>
                                        {order.nextOperationAt ? <span className="small-tip">{dateTime(order.nextOperationAt)}</span> : '—'}
                                    </td>
                                    <td className="text-center"><TimeRemaining order={order} /></td>
                                    <td className="ps-order-products-cell">
                                        <OrderProductsBreakdown items={order.products ?? []} order={order} />
                                    </td>
                                    <td className="text-right ps-money-cell">
                                        <OrderMoneyBreakdown row={order} />
                                    </td>
                                    <td className="text-right">{money(order.deposit)}</td>
                                    <td className={`text-center ttgh ttgh-${order.deliveryStatusValue || 'none'}`}>
                                        <div className="ps-delivery-actions">
                                            {order.closedAt ? <button type="button" className="btn-icon" onClick={() => onHistory(order, 'accounting')} title="Lịch sử kế toán"><i className="fa fa-history" /></button> : null}
                                            <button type="button" className="btn-icon" onClick={() => onDesiredDate(order)} title="Cập nhật ngày muốn nhận hàng"><i className="fa fa-calendar" /></button>
                                        </div>
                                        <b>{order.deliveryStatus || '—'}</b><br />
                                        {order.trackingNumber && <span>{order.trackingNumber}</span>}
                                        {order.desiredDeliveryAt && <div className="small-tip">({dateTime(order.desiredDeliveryAt)})</div>}
                                    </td>
                                </tr>
                            );
                        })}
                        {!rows.length && <tr><td colSpan={14} className="ps-sale-empty">Không có dữ liệu phù hợp.</td></tr>}
                    </tbody>
                </table>
            </div>
            <PushsalePagination meta={meta} routeUrl={routeUrl} filters={filters} />
            {selected.length > 0 && (
                <div className="ps-sale-selection-bar">
                    <strong>Đã chọn {selected.length} dòng</strong>
                    <button type="button" className="btn btn-primary btn-sm" onClick={() => onBulkClose(selected)}><i className="fa fa-check-square-o" /> Chốt đơn nhiều</button>
                    <button type="button" className="btn btn-default btn-sm" onClick={() => setSelected([])}>Bỏ chọn</button>
                </div>
            )}
        </>
    );
}
