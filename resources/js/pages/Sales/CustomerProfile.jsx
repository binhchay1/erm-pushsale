import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { ReportPagination } from '@/components/reports/ReportPagination';
import { OrderMoneyBreakdown, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { CustomerSupplementPacketsDialog } from '@/components/customers/CustomerSupplementPacketsDialog';
import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { ProductSearchSelect } from '@/components/filters/ProductSearchSelect';
import { PageHeader } from '@/components/layout/PageHeader';
import {
    PushsaleCustomerMessagesDialog,
    PushsaleDataViewHistoryDialog,
    PushsaleOperationHistoryDialog,
    PushsalePurchaseHistoryDialog,
} from '@/components/customers/pushsale/PushsaleCustomerDialogs';
import { getCsrfToken } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';

const EMPTY_DIALOG = { type: null, order: null };

function optionValue(option) {
    return String(option?.value ?? option?.id ?? '');
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? '—';
}

function FilterSelect({ value, onChange, options = [], placeholder, disabled = false }) {
    return (
        <select className="form-control ps-filter-control" value={value ?? ''} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={optionValue(option)} value={optionValue(option)}>{optionLabel(option)}</option>
            ))}
        </select>
    );
}

function dateLabel(value) {
    return value ? formatDateTime(value) : '';
}

function safeText(value, fallback = '—') {
    return value === null || value === undefined || value === '' ? fallback : value;
}

function appendQuery(url, params) {
    if (!url) return null;
    const query = new URLSearchParams(params);
    return `${url}${url.includes('?') ? '&' : '?'}${query.toString()}`;
}

function externalHref(url) {
    const value = String(url ?? '').trim();
    if (!value) return null;

    if (/^(https?:)?\/\//i.test(value)) {
        return value.startsWith('//') ? `https:${value}` : value;
    }

    return `https://${value}`;
}

function CustomerProfileTable({ rows, pagination, selected, setSelected, onOpenDialog, saleWorkspaceUrl = null, warehouseOperationsUrl = null }) {
    const allSelected = rows.length > 0 && rows.every((row) => selected.has(String(row.id)));
    const start = Number(pagination?.from ?? 1);

    const toggleAll = (checked) => {
        setSelected((current) => {
            const next = new Set(current);
            rows.forEach((row) => checked ? next.add(String(row.id)) : next.delete(String(row.id)));
            return next;
        });
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            checked ? next.add(String(id)) : next.delete(String(id));
            return next;
        });
    };

    return (
        <div id="customer-profile-table" className="ps-customer-table-scroll">
            <table className="table table-bordered table-multi-select table-sale ps-customer-profile-table">
                <thead>
                    <tr className="drags-area">
                        <th className="text-center ps-col-check">
                            <span className="chk-all">
                                <input id="customer-profile-check-all" type="checkbox" checked={allSelected} onChange={(event) => toggleAll(event.target.checked)} />
                                <label htmlFor="customer-profile-check-all" />
                            </span>
                        </th>
                        <th className="text-center ps-col-code">Mã đơn</th>
                        <th className="text-center no-wrap ps-col-source"><span>Nguồn dữ liệu</span><br />Ngày data về</th>
                        <th className="text-center no-wrap ps-col-customer"><span>Họ tên</span><br />Số điện thoại</th>
                        <th className="text-center no-wrap ps-col-message"><span>Tin nhắn</span></th>
                        <th className="text-center no-wrap ps-col-sale"><span>Sale</span><br />Ngày nhận data</th>
                        <th className="text-center no-wrap ps-col-operation"><span>Tác nghiệp</span><br />Ngày chốt đơn</th>
                        <th className="text-center no-wrap ps-col-result"><span>Kết quả</span><br />Ngày sale tác nghiệp</th>
                        <th className="text-center no-wrap ps-col-products"><span>Sản phẩm - Số lượng - Đơn giá</span></th>
                        <th className="text-center no-wrap area3 ps-col-money"><span>Thành tiền<br />CK/VAT<br />Phí VC/Tổng tiền</span></th>
                        <th className="text-center no-wrap ps-col-deposit">Khách đặt cọc</th>
                        <th className="text-center no-wrap ps-col-shipping"><span>Kho</span><br /><span title="Phương thức giao hàng">PTGH</span><br />Mã giao vận</th>
                        <th className="text-center ps-col-delivery">Trạng thái giao hàng<br /><span>Ngày muốn nhận hàng</span></th>
                        <th className="text-center ps-col-recon" title="Đối soát nội bộ">ĐSNB</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row, index) => {
                        const checked = selected.has(String(row.id));
                        const message = [row.customerNote, row.effectiveShippingAddress ? `Địa chỉ= ${row.effectiveShippingAddress}` : null]
                            .filter(Boolean)
                            .join('\n');

                        return (
                            <tr key={row.id} className={`contact-row ${checked ? 'ps-row-selected' : ''}`}>
                                <td className="text-center">
                                    <span className="chk-item">
                                        <input id={`customer-row-${row.id}`} type="checkbox" checked={checked} onChange={(event) => toggleOne(row.id, event.target.checked)} />
                                        <label htmlFor={`customer-row-${row.id}`}>{start + index}</label>
                                    </span>
                                </td>
                                <td className="text-center ps-code-cell">
                                    <div className="ps-order-code-stack">
                                        <span className="item-md ps-order-code-text">{row.orderCode || '—'}</span>
                                        <button type="button" className="btn-icon aoh ps-cell-action" onClick={() => onOpenDialog('view', row)} title="Xem lịch sử xem thông tin số">
                                            <i className="fa fa-history" />
                                        </button>
                                    </div>
                                </td>
                                <td className="text-center">
                                    <span className="span-col span-col-width cancel-col">
                                        {externalHref(row.sourceUrl) ? (
                                            <a href={externalHref(row.sourceUrl)} target="_blank" rel="noopener noreferrer" title={row.sourceUrl}>
                                                {safeText(row.sourceName)}
                                            </a>
                                        ) : (
                                            <span>{safeText(row.sourceName)}</span>
                                        )}
                                    </span>
                                    <br />
                                    <span className="small-tip">{row.marketerEmail ? `(${row.marketerEmail})` : row.marketerName ? `(${row.marketerName})` : ''}</span>
                                    <br />
                                    <span className="small-tip">{dateLabel(row.dataArrivedAt)}</span>
                                </td>
                                <td className="text-left">
                                    <div className="span-col ps-customer-name">
                                        {saleWorkspaceUrl ? (
                                            <a href={appendQuery(saleWorkspaceUrl, { order_id: row.id })}>{safeText(row.customerName)}</a>
                                        ) : (
                                            <span>{safeText(row.customerName)}</span>
                                        )}
                                    </div>
                                    <div className="no-wrap ps-phone-line ps-contact-phone-row">
                                        <button type="button" className="ps-phone-link" onClick={() => onOpenDialog('purchase', row)}>{safeText(row.customerPhone)}</button>
                                        {row.customerPhone ? (
                                            <a className="ps-contact-phone-icon" href={`tel:${row.customerPhone}`} title="Gọi khách hàng" aria-label={`Gọi ${row.customerPhone}`}>
                                                <i className="fa fa-phone" aria-hidden="true" />
                                            </a>
                                        ) : null}
                                        <OrderStatusFlags row={row} onDuplicate={() => onOpenDialog('purchase', row)} className="ps-contact-flags" showUpsell />
                                    </div>
                                    {row.phoneCarrier ? <span className={`ps-carrier ps-carrier-${row.phoneCarrierKey ?? 'other'}`}>{row.phoneCarrier}</span> : null}
                                </td>
                                <td className="text-left ps-message-cell">
                                    <span>{message || '—'}</span>
                                    {row.hasDifferentReceiver ? <span className="small-tip ps-receiver-note">Người nhận: {row.effectiveReceiverName} · {row.effectiveReceiverPhone}</span> : null}
                                </td>
                                <td className="text-center">
                                    <span>{safeText(row.saleName)}<br /><span className="small-tip">{row.saleEmail ? `(${row.saleEmail})` : ''}</span></span>
                                    <br />
                                    <span className="small-tip">{dateLabel(row.assignedAt)}</span>
                                </td>
                                <td className="text-center no-wrap">
                                    <span>{safeText(row.currentOperation, 'Khách mới')}</span>
                                    <br />
                                    <span className="small-tip">{dateLabel(row.closedAt)}</span>
                                </td>
                                <td className="text-center ps-result-cell">
                                    <div className="ps-row-actions">
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenDialog('messages', row)} title="Tin nhắn nội bộ / Chat khách hàng"><i className="fa fa-commenting-o" /></button>
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenDialog('operation', row)} title="Lịch sử tác nghiệp"><i className="fa fa-history" /></button>
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenDialog('purchase', row)} title="Lịch sử mua hàng"><i className="fa fa-shopping-cart" /></button>
                                        {row.pendingSupplementCount > 0 ? <CustomerSupplementPacketsDialog order={row} count={row.pendingSupplementCount} /> : null}
                                    </div>
                                    <div>
                                        <span>{safeText(row.operationResult, '')}</span>
                                        <br />
                                        <span className="small-tip">{dateLabel(row.lastOperationAt ?? row.nextOperationAt)}</span>
                                    </div>
                                    {row.nextOperationAt ? <div className="item-noidung-other">Hẹn: {dateLabel(row.nextOperationAt)}</div> : null}
                                </td>
                                <td className="text-left ps-order-products-cell">
                                    <OrderProductsBreakdown items={row.products ?? []} order={row} />
                                </td>
                                <td className="no-wrap area3 text-right ps-order-money-cell">
                                    <OrderMoneyBreakdown row={row} />
                                </td>
                                <td className="text-right">{row.deposit ? formatCurrency(row.deposit) : ''}</td>
                                <td className="text-center area4">
                                    <span className="ps-warehouse-name">{safeText(row.warehouseName, '')}</span>
                                    {row.shippingMethod || row.shippingProvider ? <><br /><span className="small-tip">{row.shippingMethod || row.shippingProvider}</span></> : null}
                                    {row.trackingNumber ? (
                                        <>
                                            <br />
                                            {warehouseOperationsUrl ? (
                                                <a
                                                    className="item-mdgv"
                                                    href={appendQuery(warehouseOperationsUrl, { search: row.trackingNumber, no_closing_date_limit: '1' })}
                                                    title="Mở tác nghiệp kho theo mã giao vận"
                                                >
                                                    {row.trackingNumber}
                                                </a>
                                            ) : (
                                                <span className="item-mdgv">{row.trackingNumber}</span>
                                            )}
                                        </>
                                    ) : null}
                                </td>
                                <td className="text-center area4">
                                    <span className={`ps-delivery-status ps-delivery-${row.deliveryStatusValue ?? 'unknown'}`}>{safeText(row.deliveryStatus, '')}</span>
                                    <br />
                                    <span className="small-tip">{row.desiredDeliveryAt ? formatDateTime(row.desiredDeliveryAt, { withTime: false }) : ''}</span>
                                </td>
                                <td className="text-center">{safeText(row.internalReconNote, '')}</td>
                            </tr>
                        );
                    }) : (
                        <tr><td colSpan={14} className="text-center ps-no-data">Không có dữ liệu phù hợp.</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

const actionButtonBaseStyle = {
    position: 'relative',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: 54,
    height: 54,
    minWidth: 54,
    minHeight: 54,
    border: 0,
    borderRadius: 999,
    color: '#fff',
    boxShadow: '0 4px 12px rgba(0,0,0,.25)',
    fontSize: 20,
    cursor: 'pointer',
};

const actionMainStyle = {
    ...actionButtonBaseStyle,
    width: 66,
    height: 66,
    minWidth: 66,
    minHeight: 66,
    background: '#0067bd',
    fontSize: 26,
};

const actionColors = {
    primary: '#0585e5',
    success: '#45a85a',
    warning: '#ff8c00',
    danger: '#c9332e',
};

function ActionBubble({ tone = 'primary', label, onClick, children }) {
    return (
        <button
            type="button"
            className={`ps-action-bubble ps-v87-round-action ps-action-bubble-${tone}`}
            title={label}
            aria-label={label}
            onClick={onClick}
            style={{ ...actionButtonBaseStyle, background: actionColors[tone] ?? actionColors.primary }}
        >
            {children}
            <span className="ps-action-tooltip" aria-hidden="true">{label}</span>
        </button>
    );
}

function FloatingActions({ selectedIds, permissions, apiBase = '/customers' }) {
    const [open, setOpen] = useState(false);
    const hasSelection = selectedIds.length > 0;

    const downloadBlob = (blob, filename) => {
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
    };

    const exportCsv = async (variant) => {
        if (!hasSelection) {
            toast.warning('Vui lòng tích chọn ít nhất một hồ sơ.');
            return;
        }

        try {
            const query = new URLSearchParams({ variant: String(variant) });
            selectedIds.forEach((id) => query.append('ids[]', id));
            const response = await fetch(`${apiBase}/export?${query.toString()}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/csv,application/json,text/plain,*/*',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const contentType = response.headers.get('content-type') ?? '';
            if (!response.ok) {
                const message = contentType.includes('application/json')
                    ? (await response.json().catch(() => ({}))).message
                    : await response.text().catch(() => '');
                throw new Error(message || `Không xuất được file (${response.status}).`);
            }
            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') ?? '';
            const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|\")?([^";]+)/i);
            const filename = filenameMatch ? decodeURIComponent(filenameMatch[1].replaceAll('"', '')) : `ho-so-khach-hang-kieu-${variant}.csv`;
            downloadBlob(blob, filename);
            toast.success('Đã xuất file.');
        } catch (error) {
            toast.error(error.message ?? 'Không xuất được file.');
        }
    };

    const submit = async (url, method, confirmMessage) => {
        if (!hasSelection) {
            toast.warning('Vui lòng tích chọn ít nhất một hồ sơ.');
            return;
        }
        if (confirmMessage && !window.confirm(confirmMessage)) return;

        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids: selectedIds }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message ?? `Không thực hiện được thao tác (${response.status}).`);
            toast.success(data.message ?? 'Đã thực hiện thao tác.');
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message ?? 'Không thực hiện được thao tác.');
        }
    };

    return (
        <nav className={`action-container ps-floating-action-menu ${open ? 'is-open' : ''}`} aria-label="Thao tác hồ sơ khách hàng">
            <div className="hidden-actions">
                <div className="icon-row">
                    <ActionBubble tone="primary" label="Xuất Excel kiểu 1" onClick={() => exportCsv(1)}><i className="fa fa-file-excel-o" /></ActionBubble>
                    <ActionBubble tone="success" label="Xuất Excel kiểu 2" onClick={() => exportCsv(2)}><i className="fa fa-file-excel-o" /></ActionBubble>
                    <ActionBubble tone="warning" label="Xuất Excel kiểu 3" onClick={() => exportCsv(3)}><i className="fa fa-file-excel-o" /></ActionBubble>
                    <ActionBubble tone="danger" label="Xuất Excel sandbox" onClick={() => exportCsv(4)}><i className="fa fa-file-excel-o" /></ActionBubble>
                </div>
                {permissions?.canBulkManage ? (
                    <div className="icon-row">
                        <ActionBubble tone="warning" label="Phân bổ lại ngay" onClick={() => submit(`${apiBase}/bulk/reallocate-now`, 'POST', 'Bạn chắc chắn muốn phân bổ lại hồ sơ khách hàng?')}><i className="fa fa-retweet" /></ActionBubble>
                        <ActionBubble tone="warning" label="Chuyển phân bổ lại sau" onClick={() => submit(`${apiBase}/bulk/queue-reallocation`, 'POST', 'Bạn chắc chắn muốn chuyển hồ sơ về danh sách phân bổ lại?')}><i className="fa fa-send" /></ActionBubble>
                        <ActionBubble tone="danger" label="Thu hồi số" onClick={() => submit(`${apiBase}/bulk/recall`, 'POST', 'Bạn chắc chắn muốn thu hồi các hồ sơ đã chọn?')}><i className="fa fa-chain-broken" /></ActionBubble>
                    </div>
                ) : null}
                {permissions?.canDeleteHistory ? (
                    <div className="icon-row">
                        <ActionBubble tone="danger" label="Xóa lịch sử tác nghiệp" onClick={() => submit(`${apiBase}/bulk/operation-history`, 'DELETE', 'Bạn chắc chắn muốn xóa lịch sử tác nghiệp?')}><i className="fa fa-trash" /></ActionBubble>
                    </div>
                ) : null}
            </div>
            <button type="button" className="main-action ps-v87-round-action" onClick={() => setOpen((current) => !current)} title="Thao tác" style={actionMainStyle}>
                <i className="fa fa-bars" />
            </button>
            {hasSelection ? <span className="ps-action-count">{selectedIds.length}</span> : null}
        </nav>
    );
}

export default function CustomerProfile({ filters = {}, filterOptions = {}, report, routeUrl = '/customers', saleWorkspaceUrl = null, warehouseOperationsUrl = null, pageTitle = 'Hồ sơ khách hàng', activeMenuCode = '4.2' }) {
    const rows = report?.rows?.data ?? [];
    const pagination = report?.rows?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0, from: 0, to: 0 };
    const [form, setForm] = useState(filters);
    const [filtersOpen, setFiltersOpen] = useState(true);
    const [selected, setSelected] = useState(new Set());
    const [dialog, setDialog] = useState(EMPTY_DIALOG);

    useEffect(() => {
        setSelected(new Set());
    }, [rows.map((row) => row.id).join(',')]);

    const saleTeams = useMemo(() => {
        const leaderId = String(form.sale_leader_id ?? '');
        return leaderId ? (filterOptions.saleTeams ?? []).filter((team) => String(team.leaderId ?? '') === leaderId) : (filterOptions.saleTeams ?? []);
    }, [filterOptions.saleTeams, form.sale_leader_id]);
    const sales = useMemo(() => {
        const teamId = String(form.sale_team_id ?? '');
        const leaderId = String(form.sale_leader_id ?? '');
        return (filterOptions.sales ?? []).filter((user) => (!teamId || String(user.teamId ?? '') === teamId) && (!leaderId || String(user.managerId ?? '') === leaderId || String(user.value) === leaderId));
    }, [filterOptions.sales, form.sale_leader_id, form.sale_team_id]);
    const marketingTeams = useMemo(() => {
        const leaderId = String(form.marketing_leader_id ?? '');
        return leaderId ? (filterOptions.marketingTeams ?? []).filter((team) => String(team.leaderId ?? '') === leaderId) : (filterOptions.marketingTeams ?? []);
    }, [filterOptions.marketingTeams, form.marketing_leader_id]);
    const marketers = useMemo(() => {
        const teamId = String(form.marketing_team_id ?? '');
        const leaderId = String(form.marketing_leader_id ?? '');
        return (filterOptions.marketers ?? []).filter((user) => (!teamId || String(user.teamId ?? '') === teamId) && (!leaderId || String(user.managerId ?? '') === leaderId || String(user.value) === leaderId));
    }, [filterOptions.marketers, form.marketing_leader_id, form.marketing_team_id]);

    const setField = (name, value) => setForm((current) => ({ ...current, [name]: value, page: 1 }));
    const search = () => router.get(routeUrl, { ...form, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    const reset = () => {
        const next = { date_from: filters.date_from, date_to: filters.date_to, date_type: 'data_arrival', per_page: filters.per_page ?? 20 };
        setForm(next);
        router.get(routeUrl, next, { preserveState: true, replace: true });
    };
    const openDialog = (type, order) => setDialog({ type, order });
    const closeDialog = () => setDialog(EMPTY_DIALOG);
    const customerActionBase = String(routeUrl || '/customers').split('?')[0].replace(/\/$/, '');

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <section className="ps-customer-profile-page">
                <PageHeader
                    title={pageTitle}
                    pageCode={activeMenuCode}
                    className="ps-customer-profile-header"
                    collapsible={false}
                    actions={(
                        <div className="ps-customer-title-actions">
                            <input
                                type="text"
                                className="form-control ps-header-search"
                                value={form.search ?? ''}
                                placeholder="Họ tên, số điện thoại"
                                onChange={(event) => setField('search', event.target.value)}
                                onKeyDown={(event) => event.key === 'Enter' && search()}
                            />
                            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                            <a
                                role="button"
                                tabIndex={0}
                                className="btn-icon ps-filter-toggle"
                                title={filtersOpen ? 'Thu gọn bộ lọc' : 'Mở bộ lọc'}
                                aria-expanded={filtersOpen}
                                onClick={() => setFiltersOpen((current) => !current)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault();
                                        setFiltersOpen((current) => !current);
                                    }
                                }}
                            >
                                <i className={`fa ${filtersOpen ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} />
                            </a>
                        </div>
                    )}
                    advanced={filtersOpen ? (
                        <div className="ps-adv-filter-panel ps-customer-filter-panel">
                            <div className="ps-adv-filter-row">
                                <DateRangeFilter
                                    className="ps-date-range-control ps-adv-date-cluster"
                                    inputClassName="ps-filter-control"
                                    from={form.date_from}
                                    to={form.date_to}
                                    withTimeLabel={false}
                                    onChange={({ date_from, date_to }) => setForm((current) => ({ ...current, date_from, date_to, page: 1 }))}
                                />
                                <FilterSelect value={form.date_type} onChange={(value) => setField('date_type', value)} options={filterOptions.dateTypes} placeholder="--Kiểu ngày--" />
                                <FilterSelect value={form.care_status} onChange={(value) => setField('care_status', value)} options={filterOptions.careStatuses} placeholder="--Care đơn--" />
                                <FilterSelect value={form.closing_status} onChange={(value) => setField('closing_status', value)} options={filterOptions.closingStatuses} placeholder="--Trạng thái chốt đơn--" />
                                <FilterSelect value={form.source_id} onChange={(value) => setField('source_id', value)} options={filterOptions.sources} placeholder="--Nguồn dữ liệu--" />
                                <FilterSelect value={form.sale_leader_id} onChange={(value) => setForm((current) => ({ ...current, sale_leader_id: value, sale_team_id: '', sale_id: '', page: 1 }))} options={filterOptions.saleLeaders} placeholder="--Trưởng nhóm sale--" />
                                <FilterSelect value={form.sale_team_id} onChange={(value) => setForm((current) => ({ ...current, sale_team_id: value, sale_id: '', page: 1 }))} options={saleTeams} placeholder="--Nhóm sale--" />
                                <FilterSelect value={form.sale_id} onChange={(value) => setField('sale_id', value)} options={sales} placeholder="--Sale--" />
                            </div>
                            <div className="ps-adv-filter-row">
                                <FilterSelect value={form.marketing_leader_id} onChange={(value) => setForm((current) => ({ ...current, marketing_leader_id: value, marketing_team_id: '', marketer_id: '', page: 1 }))} options={filterOptions.marketingLeaders} placeholder="--Trưởng nhóm marketing--" />
                                <FilterSelect value={form.marketing_team_id} onChange={(value) => setForm((current) => ({ ...current, marketing_team_id: value, marketer_id: '', page: 1 }))} options={marketingTeams} placeholder="--Nhóm marketing--" />
                                <FilterSelect value={form.marketer_id} onChange={(value) => setField('marketer_id', value)} options={marketers} placeholder="--Marketing--" />
                                <FilterSelect value={form.operation_stage} onChange={(value) => setField('operation_stage', value)} options={filterOptions.operationStages} placeholder="--Trạng thái tác nghiệp--" />
                                <FilterSelect value={form.operation_stage} onChange={(value) => setField('operation_stage', value)} options={filterOptions.operationStages} placeholder="--Tác nghiệp--" />
                                <FilterSelect value={form.operation_result} onChange={(value) => setField('operation_result', value)} options={filterOptions.operationResults} placeholder="--Kết quả tác nghiệp--" />
                                <FilterSelect value={form.delivery_status} onChange={(value) => setField('delivery_status', value)} options={filterOptions.deliveryStatuses} placeholder="--Trạng thái giao hàng--" />
                            </div>
                            <div className="ps-adv-filter-row">
                                <ProductSearchSelect products={filterOptions.products ?? []} value={form.product_id} onChange={(value) => setField('product_id', value)} placeholder="--Sản phẩm / gói sản phẩm--" showPrice={false} />
                                <FilterSelect value={form.warehouse_id} onChange={(value) => setField('warehouse_id', value)} options={filterOptions.warehouses} placeholder="--Kho--" />
                                <FilterSelect value={form.reconciliation_status} onChange={(value) => setField('reconciliation_status', value)} options={filterOptions.reconciliationStatuses} placeholder="--Đối soát nội bộ--" />
                                <FilterSelect value={form.duplicate_status} onChange={(value) => setField('duplicate_status', value)} options={filterOptions.duplicateStatuses} placeholder="--Trùng số--" />
                                <FilterSelect value={form.customer_type} onChange={(value) => setField('customer_type', value)} options={filterOptions.customerTypes} placeholder="--Khách cũ / Khách mới--" />
                                <FilterSelect value={form.allocation_status} onChange={(value) => setField('allocation_status', value)} options={filterOptions.allocationStatuses} placeholder="--Phân bổ--" />
                                <FilterSelect value={form.shipping_method} onChange={(value) => setField('shipping_method', value)} options={filterOptions.shippingMethods} placeholder="--PTGH--" />
                                <button type="button" className="btn btn-default ps-filter-reset" onClick={reset}><i className="fa fa-refresh" /> Đặt lại</button>
                            </div>
                        </div>
                    ) : null}
                />

                <CustomerProfileTable rows={rows} pagination={pagination} selected={selected} setSelected={setSelected} onOpenDialog={openDialog} saleWorkspaceUrl={saleWorkspaceUrl} warehouseOperationsUrl={warehouseOperationsUrl} />

                <ReportPagination routeUrl={routeUrl} filters={form} meta={pagination} scrollTargetId="customer-profile-table" />

                <FloatingActions selectedIds={[...selected]} permissions={filterOptions.permissions} apiBase={customerActionBase} />
            </section>

            <PushsaleCustomerMessagesDialog order={dialog.order} open={dialog.type === 'messages'} onOpenChange={(open) => !open && closeDialog()} />
            <PushsaleOperationHistoryDialog order={dialog.order} open={dialog.type === 'operation'} onOpenChange={(open) => !open && closeDialog()} />
            <PushsalePurchaseHistoryDialog order={dialog.order} open={dialog.type === 'purchase'} onOpenChange={(open) => !open && closeDialog()} />
            <PushsaleDataViewHistoryDialog order={dialog.order} open={dialog.type === 'view'} onOpenChange={(open) => !open && closeDialog()} />
        </AppLayout>
    );
}
