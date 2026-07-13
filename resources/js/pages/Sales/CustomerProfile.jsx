import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { ReportPagination } from '@/components/reports/ReportPagination';
import { CustomerSupplementPacketsDialog } from '@/components/customers/CustomerSupplementPacketsDialog';
import {
    PushsaleCustomerMessagesModal,
    PushsaleDataViewHistoryModal,
    PushsaleOperationHistoryModal,
    PushsalePurchaseHistoryModal,
} from '@/components/customers/pushsale/PushsaleCustomerModals';
import { getCsrfToken } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';

const EMPTY_MODAL = { type: null, order: null };

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

function CustomerProfileTable({ rows, pagination, selected, setSelected, onOpenModal }) {
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
                                <td className="text-center">
                                    <span className="item-md">{row.orderCode}</span>
                                    <button type="button" className="btn-icon aoh" onClick={() => onOpenModal('view', row)} title="Xem lịch sử xem thông tin số">
                                        <i className="fa fa-history" />
                                    </button>
                                    {row.isSupplementalOrder ? <span className="ps-upsale-badge" title={`Đơn upsale từ ${row.supplementalOriginalOrderCode ?? 'đơn gốc'}`}>UPSALE</span> : null}
                                </td>
                                <td className="text-center">
                                    <span className="span-col span-col-width cancel-col">
                                        <a href="#" onClick={(event) => event.preventDefault()}>{safeText(row.sourceName)}</a>
                                    </span>
                                    <br />
                                    <span className="small-tip">{row.marketerEmail ? `(${row.marketerEmail})` : row.marketerName ? `(${row.marketerName})` : ''}</span>
                                    <br />
                                    <span className="small-tip">{dateLabel(row.dataArrivedAt)}</span>
                                </td>
                                <td className="text-left">
                                    <div className="span-col ps-customer-name">
                                        <a href={`/sales/workspace?order_id=${row.id}`}>{safeText(row.customerName)}</a>
                                    </div>
                                    <div className="no-wrap ps-phone-line">
                                        <button type="button" className="ps-phone-link" onClick={() => onOpenModal('purchase', row)}>{safeText(row.customerPhone)}</button>
                                        {row.isDuplicatePhone ? <i className="fa fa-clone text-danger" title="Số điện thoại trùng" /> : null}
                                        {row.isReturningCustomer ? <i className="fa fa-heart text-danger" title="Khách hàng cũ" /> : null}
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
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenModal('messages', row)} title="Tin nhắn nội bộ / Chat khách hàng"><i className="fa fa-commenting-o" /></button>
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenModal('operation', row)} title="Lịch sử tác nghiệp"><i className="fa fa-history" /></button>
                                        <button type="button" className="btn-icon aoh" onClick={() => onOpenModal('purchase', row)} title="Lịch sử mua hàng"><i className="fa fa-shopping-cart" /></button>
                                        {row.pendingSupplementCount > 0 ? <CustomerSupplementPacketsDialog order={row} count={row.pendingSupplementCount} /> : null}
                                    </div>
                                    <div>
                                        <span>{safeText(row.operationResult, '')}</span>
                                        <br />
                                        <span className="small-tip">{dateLabel(row.lastOperationAt ?? row.nextOperationAt)}</span>
                                    </div>
                                    {row.nextOperationAt ? <div className="item-noidung-other">Hẹn: {dateLabel(row.nextOperationAt)}</div> : null}
                                </td>
                                <td className="text-left">
                                    <table className="tb-in-sp"><tbody>
                                        {row.products?.length ? row.products.map((product) => (
                                            <tr className="row-sp" key={product.itemId ?? `${row.id}-${product.productName}`}>
                                                <td><span className="ten-sp">{product.productName}</span></td>
                                                <td className="text-center no-wrap">x{product.quantity}</td>
                                                <td className="text-right">{formatCurrency(product.unitPrice)}</td>
                                            </tr>
                                        )) : <tr><td>—</td><td></td><td></td></tr>}
                                    </tbody></table>
                                </td>
                                <td className="no-wrap area3 text-right">
                                    <table className="tb-in-sp"><tbody>
                                        <tr><td title="Thành tiền">{formatCurrency(row.subtotal)}</td></tr>
                                        <tr><td title="Chiết khấu">-{formatCurrency(row.discount)}</td></tr>
                                        <tr><td title="VAT">{formatCurrency(row.vat)}</td></tr>
                                        <tr><td title="Phí VC">{formatCurrency(row.shippingFeeCollected)}</td></tr>
                                        <tr><td className="ps-order-total" title="Tổng tiền đơn hàng">{formatCurrency(row.total)}</td></tr>
                                    </tbody></table>
                                </td>
                                <td className="text-right">{row.deposit ? formatCurrency(row.deposit) : ''}</td>
                                <td className="text-center area4">
                                    <span className="ps-warehouse-name">{safeText(row.warehouseName, '')}</span>
                                    {row.shippingMethod || row.shippingProvider ? <><br /><span className="small-tip">{row.shippingMethod || row.shippingProvider}</span></> : null}
                                    {row.trackingNumber ? <><br /><a className="item-mdgv" href="#" onClick={(event) => event.preventDefault()}>{row.trackingNumber}</a></> : null}
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

function FloatingActions({ selectedIds, permissions }) {
    const [open, setOpen] = useState(false);
    const hasSelection = selectedIds.length > 0;

    const exportCsv = (variant) => {
        const query = new URLSearchParams({ variant: String(variant) });
        selectedIds.forEach((id) => query.append('ids[]', id));
        window.location.assign(`/customers/export?${query.toString()}`);
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
            if (!response.ok) throw new Error(data.message ?? 'Không thực hiện được thao tác.');
            toast.success(data.message ?? 'Đã thực hiện thao tác.');
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message ?? 'Không thực hiện được thao tác.');
        }
    };

    return (
        <nav className={`action-container ${open ? 'is-open' : ''}`}>
            <div className="hidden-actions">
                <div className="icon-row">
                    <button type="button" className="n-button fam-primary" data-tooltip="Xuất Excel kiểu 1" onClick={() => exportCsv(1)}><i className="fa fa-file-excel-o" /></button>
                    <button type="button" className="n-button fam-success" data-tooltip="Xuất Excel kiểu 2" onClick={() => exportCsv(2)}><i className="fa fa-file-excel-o" /></button>
                    <button type="button" className="n-button fam-warning" data-tooltip="Xuất Excel kiểu 3" onClick={() => exportCsv(3)}><i className="fa fa-file-excel-o" /></button>
                    <button type="button" className="n-button fam-danger" data-tooltip="Xuất Excel Sandbox" onClick={() => exportCsv(4)}><i className="fa fa-file-excel-o" /></button>
                </div>
                {permissions?.canBulkManage ? (
                    <div className="icon-row">
                        <button type="button" className="n-button fam-warning" data-tooltip="Phân bổ lại ngay lập tức" onClick={() => submit('/customers/bulk/reallocate-now', 'POST', 'Bạn chắc chắn muốn phân bổ lại hồ sơ khách hàng?')}><i className="fa fa-retweet" /></button>
                        <button type="button" className="n-button fam-warning" data-tooltip="Chuyển phân bổ lại sau" onClick={() => submit('/customers/bulk/queue-reallocation', 'POST', 'Bạn chắc chắn muốn chuyển hồ sơ về danh sách phân bổ lại?')}><i className="fa fa-send" /></button>
                        <button type="button" className="n-button fam-danger" data-tooltip="Thu hồi số" onClick={() => submit('/customers/bulk/recall', 'POST', 'Bạn chắc chắn muốn thu hồi các hồ sơ đã chọn?')}><i className="fa fa-chain-broken" /></button>
                    </div>
                ) : null}
                {permissions?.canDeleteHistory ? (
                    <div className="icon-row">
                        <button type="button" className="n-button fam-danger" data-tooltip="Xóa lịch sử tác nghiệp" onClick={() => submit('/customers/bulk/operation-history', 'DELETE', 'Bạn chắc chắn muốn xóa lịch sử tác nghiệp?')}><i className="fa fa-trash" /></button>
                    </div>
                ) : null}
            </div>
            <button type="button" className="main-action" onClick={() => setOpen((current) => !current)} title="Thao tác"><i className="fa fa-bars" /></button>
            {hasSelection ? <span className="ps-action-count">{selectedIds.length}</span> : null}
        </nav>
    );
}

export default function CustomerProfile({ filters = {}, filterOptions = {}, report, routeUrl = '/customers', pageTitle = 'Hồ sơ khách hàng' }) {
    const rows = report?.rows?.data ?? [];
    const pagination = report?.rows?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0, from: 0, to: 0 };
    const [form, setForm] = useState(filters);
    const [filtersOpen, setFiltersOpen] = useState(true);
    const [selected, setSelected] = useState(new Set());
    const [modal, setModal] = useState(EMPTY_MODAL);

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
    const openModal = (type, order) => setModal({ type, order });
    const closeModal = () => setModal(EMPTY_MODAL);

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <section className="ps-customer-profile-page">
                <div className="ps-customer-titlebar">
                    <h1>{pageTitle}</h1>
                    <div className="ps-customer-title-actions">
                        <input
                            type="text"
                            className="form-control ps-header-search"
                            value={form.search ?? ''}
                            placeholder="Họ tên, số điện thoại"
                            onChange={(event) => setField('search', event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && search()}
                        />
                        <button type="button" className="btn btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button>
                        <button type="button" className="btn btn-default ps-filter-toggle" onClick={() => setFiltersOpen((current) => !current)} title={filtersOpen ? 'Thu gọn bộ lọc' : 'Mở bộ lọc'}>
                            <i className={`fa ${filtersOpen ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} />
                        </button>
                    </div>
                </div>

                {filtersOpen ? (
                    <div className="ps-customer-filter-panel">
                        <div className="ps-filter-grid">
                            <div className="ps-date-range-control">
                                <input type="date" className="form-control ps-filter-control" value={form.date_from ?? ''} onChange={(event) => setField('date_from', event.target.value)} />
                                <span>-</span>
                                <input type="date" className="form-control ps-filter-control" value={form.date_to ?? ''} onChange={(event) => setField('date_to', event.target.value)} />
                            </div>
                            <FilterSelect value={form.date_type} onChange={(value) => setField('date_type', value)} options={filterOptions.dateTypes} placeholder="--Kiểu ngày--" />
                            <FilterSelect value={form.care_status} onChange={(value) => setField('care_status', value)} options={filterOptions.careStatuses} placeholder="--Care đơn--" />
                            <FilterSelect value={form.closing_status} onChange={(value) => setField('closing_status', value)} options={filterOptions.closingStatuses} placeholder="--Trạng thái chốt đơn--" />
                            <FilterSelect value={form.source_id} onChange={(value) => setField('source_id', value)} options={filterOptions.sources} placeholder="--Nguồn dữ liệu--" />
                            <button type="button" className="btn btn-default ps-filter-reset" onClick={reset}><i className="fa fa-refresh" /> Đặt lại</button>

                            <FilterSelect value={form.sale_leader_id} onChange={(value) => setForm((current) => ({ ...current, sale_leader_id: value, sale_team_id: '', sale_id: '', page: 1 }))} options={filterOptions.saleLeaders} placeholder="--Trưởng nhóm sale--" />
                            <FilterSelect value={form.sale_team_id} onChange={(value) => setForm((current) => ({ ...current, sale_team_id: value, sale_id: '', page: 1 }))} options={saleTeams} placeholder="--Nhóm sale--" />
                            <FilterSelect value={form.sale_id} onChange={(value) => setField('sale_id', value)} options={sales} placeholder="--Sale--" />
                            <FilterSelect value={form.marketing_leader_id} onChange={(value) => setForm((current) => ({ ...current, marketing_leader_id: value, marketing_team_id: '', marketer_id: '', page: 1 }))} options={filterOptions.marketingLeaders} placeholder="--Trưởng nhóm marketing--" />
                            <FilterSelect value={form.marketing_team_id} onChange={(value) => setForm((current) => ({ ...current, marketing_team_id: value, marketer_id: '', page: 1 }))} options={marketingTeams} placeholder="--Nhóm marketing--" />
                            <FilterSelect value={form.marketer_id} onChange={(value) => setField('marketer_id', value)} options={marketers} placeholder="--Marketing--" />

                            <FilterSelect value={form.operation_stage} onChange={(value) => setField('operation_stage', value)} options={filterOptions.operationStages} placeholder="--Trạng thái tác nghiệp--" />
                            <FilterSelect value={form.operation_stage} onChange={(value) => setField('operation_stage', value)} options={filterOptions.operationStages} placeholder="--Tác nghiệp--" />
                            <FilterSelect value={form.operation_result} onChange={(value) => setField('operation_result', value)} options={filterOptions.operationResults} placeholder="--Kết quả tác nghiệp--" />
                            <FilterSelect value={form.delivery_status} onChange={(value) => setField('delivery_status', value)} options={filterOptions.deliveryStatuses} placeholder="--Trạng thái giao hàng--" />
                            <FilterSelect value={form.product_id} onChange={(value) => setField('product_id', value)} options={filterOptions.products} placeholder="--Sản phẩm--" />
                            <FilterSelect value={form.warehouse_id} onChange={(value) => setField('warehouse_id', value)} options={filterOptions.warehouses} placeholder="--Kho--" />

                            <FilterSelect value={form.reconciliation_status} onChange={(value) => setField('reconciliation_status', value)} options={filterOptions.reconciliationStatuses} placeholder="--Đối soát nội bộ--" />
                            <FilterSelect value={form.duplicate_status} onChange={(value) => setField('duplicate_status', value)} options={filterOptions.duplicateStatuses} placeholder="--Trùng số--" />
                            <FilterSelect value={form.customer_type} onChange={(value) => setField('customer_type', value)} options={filterOptions.customerTypes} placeholder="--Khách cũ / Khách mới--" />
                            <FilterSelect value={form.allocation_status} onChange={(value) => setField('allocation_status', value)} options={filterOptions.allocationStatuses} placeholder="--Phân bổ--" />
                            <FilterSelect value={form.shipping_method} onChange={(value) => setField('shipping_method', value)} options={filterOptions.shippingMethods} placeholder="--PTGH--" />
                            <button type="button" className="btn btn-primary ps-filter-search" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                ) : null}

                <CustomerProfileTable rows={rows} pagination={pagination} selected={selected} setSelected={setSelected} onOpenModal={openModal} />

                <ReportPagination routeUrl={routeUrl} filters={form} meta={pagination} scrollTargetId="customer-profile-table" />

                <FloatingActions selectedIds={[...selected]} permissions={filterOptions.permissions} />
            </section>

            <PushsaleCustomerMessagesModal order={modal.order} open={modal.type === 'messages'} onOpenChange={(open) => !open && closeModal()} />
            <PushsaleOperationHistoryModal order={modal.order} open={modal.type === 'operation'} onOpenChange={(open) => !open && closeModal()} />
            <PushsalePurchaseHistoryModal order={modal.order} open={modal.type === 'purchase'} onOpenChange={(open) => !open && closeModal()} />
            <PushsaleDataViewHistoryModal order={modal.order} open={modal.type === 'view'} onOpenChange={(open) => !open && closeModal()} />
        </AppLayout>
    );
}
