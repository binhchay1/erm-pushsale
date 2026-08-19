import { useEffect, useState } from 'react';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

/** Shared Pushsale ops table cells — icon layout matches legacy `text-right` / float pattern. */

export function OpsIconButton({ title, icon, onClick, disabled = false, className = '', style = undefined, hidden = false }) {
    if (hidden) return null;

    return (
        <button
            type="button"
            className={`btn-icon aoh ${className}`.trim()}
            title={title}
            onClick={onClick}
            disabled={disabled}
            style={style}
            aria-label={title}
        >
            <i className={`fa fa-${icon}`} aria-hidden="true" />
        </button>
    );
}

export function OpsTopRightIcons({ children, className = '' }) {
    if (!children) return null;

    return <div className={`text-right ${className}`.trim()}>{children}</div>;
}

export function formatOpsDateTime(value, { withSeconds = false } = {}) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    const pad = (n) => String(n).padStart(2, '0');
    const base = `${pad(date.getDate())} / ${pad(date.getMonth() + 1)} / ${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    return withSeconds ? `${base}:${pad(date.getSeconds())}` : base;
}

export function externalHref(url) {
    const value = String(url ?? '').trim();
    if (!value) return null;
    if (/^(https?:)?\/\//i.test(value)) return value.startsWith('//') ? `https:${value}` : value;
    return `https://${value}`;
}

export function OrderCodeCell({ orderCode, onHistory, emptyTitle = null }) {
    const t = useT();

    return (
        <td className="text-center">
            {orderCode ? (
                <button type="button" className="lnk-md ps-order-code-link" onClick={onHistory}>{orderCode}</button>
            ) : (
                <span className="lnk-md ps-order-code-empty" title={emptyTitle ?? t('operations.ops_table.order_code_empty')}>&nbsp;</span>
            )}
            <OpsIconButton title={t('operations.ops_table.data_view_history')} icon="history" onClick={onHistory} style={{ fontSize: 14 }} />
        </td>
    );
}

export function SourceDataCell({ sourceName, sourceUrl, dataArrivedAt, className = 'text-center area5 hidden-xs ps-source-cell' }) {
    const href = externalHref(sourceUrl);

    return (
        <td className={className}>
            <span className="span-col span-col-width cancel-col ps-source-name" style={{ minWidth: 130, maxWidth: 190, display: 'inline-block' }}>
                {href ? (
                    <a href={href} target="_blank" rel="noopener noreferrer" title={sourceUrl}>{sourceName || '—'}</a>
                ) : (
                    <span>{sourceName || '—'}</span>
                )}
            </span>
            <br />
            <span className="small-tip">({formatOpsDateTime(dataArrivedAt)})</span>
        </td>
    );
}

export function SaleAssigneeCell({
    saleName,
    saleUsername,
    assignedAt,
    canDelete = false,
    onDelete,
    className = 'text-center area5 hidden-xs',
}) {
    const t = useT();

    return (
        <td className={className}>
            <OpsTopRightIcons>
                {canDelete ? (
                    <OpsIconButton title={t('operations.ops_table.delete_data')} icon="trash" onClick={onDelete} className="ps-sale-delete" />
                ) : (
                    <a className="btn-icon invisible" aria-hidden="true">&nbsp;</a>
                )}
            </OpsTopRightIcons>
            <div style={{ minWidth: 100, maxWidth: 150, display: 'inline-block' }}>
                {saleName || '—'}
                {saleUsername ? <span className="small-tip">({saleUsername})</span> : null}
            </div>
            <div className="small-tip">
                ({formatOpsDateTime(assignedAt, { withSeconds: true })})
                <a className="btn-icon invisible" aria-hidden="true">&nbsp;</a>
            </div>
        </td>
    );
}

export function CustomerContactCell({
    order,
    onEdit,
    onDuplicateOrders,
    flags = null,
    supplement = null,
    className = 'area1 ps-customer-cell',
}) {
    const t = useT();
    const name = order.customerName || order.effectiveReceiverName || '—';
    const phone = order.customerPhone || order.effectiveReceiverPhone || '';
    const carrier = order.carrierLabel || (order.phoneCarrier ? `[${order.phoneCarrier}]` : '');

    return (
        <td className={className} title={`${order.id} | ${order.sourceType || ''}`}>
            {onEdit ? (
                <div className="text-right ps-customer-edit-wrap">
                    <OpsIconButton title={t('operations.ops_table.edit_order')} icon="edit" onClick={onEdit} className="ps-cell-action" />
                </div>
            ) : null}
            <div className="ps-customer-name-wrap" style={{ maxWidth: 170, textOverflow: 'ellipsis', overflow: 'hidden' }}>
                <span className="ps-customer-name-text">{name}</span>
            </div>
            {carrier ? <div className="nha-mang text-left ps-contact-carrier-line">{carrier}</div> : null}
            <div className="ps-contact-phone-row">
                {onDuplicateOrders ? (
                    <button
                        type="button"
                        className="ps-phone-link"
                        onClick={onDuplicateOrders}
                        title={t('operations.ops_table.duplicate_list')}
                    >
                        {phone || '—'}
                    </button>
                ) : (
                    <span className="ps-phone-text">{phone || '—'}</span>
                )}
                {flags}
            </div>
            <div className="text-left khkn sline">{order.customerExtraNote || ''}</div>
            {order.desiredDeliveryAt ? (
                <div className="small-tip">({formatOpsDateTime(order.desiredDeliveryAt)})</div>
            ) : (
                <div className="small-tip" />
            )}
            {supplement}
        </td>
    );
}

export function MessageCell({
    note,
    messageParts = null,
    onClick,
    className = 'area1 hidden-xs td-5715 ps-message-cell',
}) {
    // Form vận hành: địa chỉ khách để lại → combo khách mua / SP mua thêm → status_send.
    const lines = [
        messageParts?.address_line ?? null,
        messageParts?.note_line ?? null,
        messageParts?.status_send ?? null,
    ].filter(Boolean);
    const fallback = messageParts?.fallback ?? '';
    const plain = note || fallback || '';
    const title = lines.join('\n') || plain;

    return (
        <td className={className}>
            <span
                className="td-message ps-msg-block"
                title={title || ''}
                onClick={onClick}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') onClick?.(event);
                }}
                role={onClick ? 'button' : undefined}
                tabIndex={onClick ? 0 : undefined}
            >
                {lines.length ? lines.map((line, index) => (
                    <span className="ps-msg-line" key={`${index}-${line.slice(0, 24)}`}>{line}</span>
                )) : (plain || '—')}
            </span>
        </td>
    );
}

export function OperationResultCell({
    canChangeStatus,
    options = [],
    currentLabel = '',
    onHistory,
    onChange,
    className = 'area2 no-wrap fix_brower_continue_let_off',
}) {
    const t = useT();

    return (
        <td className={className}>
            <OpsTopRightIcons>
                {onHistory ? <OpsIconButton title={t('operations.ops_table.operation_history')} icon="history" onClick={onHistory} /> : null}
            </OpsTopRightIcons>
            <div style={{ maxWidth: 180 }}>
                {canChangeStatus ? (
                    <select
                        className="form-control txt-dotted ddlpb dis_val ps-result-select"
                        defaultValue=""
                        onChange={(event) => {
                            const option = options.find((item) => item.value === event.target.value);
                            if (option) onChange?.(option);
                            event.target.value = '';
                        }}
                    >
                        <option value="">{t('operations.ops_table.choose')}</option>
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                ) : (
                    <b className="ps-result-label">{currentLabel}</b>
                )}
            </div>
            <div className="small-tip text-left">
                <a className="btn-icon invisible" aria-hidden="true">&nbsp;</a>
            </div>
        </td>
    );
}

export function NextOperationCell({ nextOperationAt, onEdit, className = 'area2 hidden-xs' }) {
    const t = useT();

    return (
        <td className={className}>
            <OpsTopRightIcons>
                {onEdit ? <OpsIconButton title={t('operations.ops_table.next_operation_edit')} icon="undo" onClick={onEdit} /> : null}
            </OpsTopRightIcons>
            {nextOperationAt ? <span className="small-tip">{formatOpsDateTime(nextOperationAt)}</span> : null}
        </td>
    );
}

export function TimeRemainingCell({ nextOperationAt, className = 'text-center no-wrap area2 hidden-xs' }) {
    const t = useT();
    const [, tick] = useState(0);
    useEffect(() => {
        const timer = window.setInterval(() => tick((value) => value + 1), 60000);
        return () => window.clearInterval(timer);
    }, []);

    if (!nextOperationAt) {
        return (
            <td className={className}>
                <OpsTopRightIcons>
                    <a className="btn-icon invisible" aria-hidden="true">&nbsp;</a>
                </OpsTopRightIcons>
                <br />
                <span className="span-col small-tip" style={{ width: 'calc(100% - 20px)' }}>—</span>
            </td>
        );
    }

    const milliseconds = new Date(nextOperationAt).getTime() - Date.now();
    const absoluteMinutes = Math.floor(Math.abs(milliseconds) / 60000);
    const hours = Math.floor(absoluteMinutes / 60);
    const minutes = absoluteMinutes % 60;
    const duration = `${hours ? `${hours}${t('operations.ops_table.hours_short')} ` : ''}${minutes}${t('operations.ops_table.minutes_short')}`;
    const label = t(
        milliseconds < 0 ? 'operations.ops_table.time_overdue' : 'operations.ops_table.time_remaining',
        { time: duration },
    );

    return (
        <td className={className}>
            <OpsTopRightIcons>
                <a className="btn-icon invisible" aria-hidden="true">&nbsp;</a>
            </OpsTopRightIcons>
            <br />
            <span className={`span-col small-tip sau-bao-lau-con-lai ${milliseconds < 0 ? 'ps-time-overdue' : 'ps-time-active'}`} style={{ width: 'calc(100% - 20px)' }}>
                {label}
            </span>
        </td>
    );
}

export function DeliveryStatusCell({
    deliveryStatus,
    deliveryStatusValue,
    trackingNumber,
    desiredDeliveryAt,
    onCalendar,
    onHistory,
    onUnclose,
    canUnclose = false,
    uncloseLabel = null,
    showAccountingHistory = false,
    className = 'text-center area4',
}) {
    const t = useT();

    return (
        <td className={`${className} ttgh ttgh-${deliveryStatusValue || 'none'} ps-delivery-status-cell`.trim()}>
            <div className="ps-delivery-status-row">
                {showAccountingHistory && onHistory ? (
                    <OpsIconButton title={t('operations.ops_table.accounting_history')} icon="history" onClick={onHistory} />
                ) : null}
                <span className={`ps-delivery-status-label ttgh${deliveryStatusValue || 0}`}>{deliveryStatus || ''}</span>
            </div>
            {canUnclose && onUnclose ? (
                <button type="button" className="btn btn-warning btn-xs ps-sale-unclose-btn" onClick={onUnclose}>
                    <i className="fa fa-undo" /> {uncloseLabel ?? t('operations.sale_order.unclose')}
                </button>
            ) : null}
            <div className="small-tip ps-delivery-carrier-hint">()</div>
            {trackingNumber ? (
                <a className="lnk-mdgv" href="javascript:void(0)" style={{ color: 'darkorange' }}>{trackingNumber}</a>
            ) : null}
            {onCalendar ? (
                <div className="ps-delivery-calendar-wrap">
                    <OpsIconButton title={t('operations.ops_table.desired_at_edit')} icon="calendar" onClick={onCalendar} />
                </div>
            ) : null}
            {desiredDeliveryAt ? (
                <div style={{ color: 'green' }}>{formatOpsDateTime(desiredDeliveryAt)}</div>
            ) : null}
        </td>
    );
}

export function moneyDisplay(value) {
    const amount = Number(value ?? 0);
    if (!amount) return '';
    return formatCurrency(amount);
}
