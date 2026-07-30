import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function valueFromSearch(key, fallback = '') {
    if (typeof window === 'undefined') return fallback;
    return new URLSearchParams(window.location.search).get(key) ?? fallback;
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function toInputDate(value) {
    if (!value) return new Date().toISOString().slice(0, 10);
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    return date.toISOString().slice(0, 10);
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.email ?? option?.id ?? '';
}

const statuses = [
    { id: '-1', label: '--Chọn trạng thái--' },
    { id: 'updating', label: 'Đang cập nhật' },
    { id: 'closed', label: 'Đã chốt' },
];

function emptyForm(defaultSender = '') {
    return {
        manager_user_id: '',
        name: 'BIÊN BẢN BÀN GIAO VẬN ĐƠN',
        carrier: '',
        document_date: toInputDate(new Date()),
        sender_name: defaultSender,
        receiver_name: '',
        note: '',
        status: 'updating',
        order_count: 0,
        product_count: 0,
    };
}

function HandoverModal({ open, mode, form, providers, onChange, onClose, onSubmit, processing }) {
    if (!open) return null;

    const title = mode === 'edit' ? 'Cập nhật biên bản' : 'Biên bản bàn giao vận đơn';

    return (
        <div className="ps-handover-modal-backdrop" role="dialog" aria-modal="true">
            <div className="ps-handover-modal">
                <div className="ps-handover-modal-title">
                    <strong>{title}</strong>
                    <button type="button" aria-label="Đóng" onClick={onClose}>×</button>
                </div>

                <form className="ps-handover-form" onSubmit={onSubmit}>
                    <label>
                        <span>Tên biên bản<span className="text-red">(*)</span></span>
                        <input value={form.name ?? ''} onChange={(event) => onChange('name', event.target.value)} required />
                    </label>

                    <label>
                        <span>Đơn vị giao hàng<span className="text-red">(*)</span></span>
                        <select value={form.carrier ?? ''} onChange={(event) => onChange('carrier', event.target.value)} required>
                            <option value="">--Chọn đơn vị giao hàng--</option>
                            {providers.map((provider) => (
                                <option key={provider.id} value={provider.id}>{optionLabel(provider)}</option>
                            ))}
                        </select>
                    </label>

                    <label>
                        <span>Ngày biên bản<span className="text-red">(*)</span></span>
                        <input type="date" value={toInputDate(form.document_date)} onChange={(event) => onChange('document_date', event.target.value)} required />
                    </label>

                    <label>
                        <span>Bên giao<span className="text-red">(*)</span></span>
                        <input value={form.sender_name ?? ''} onChange={(event) => onChange('sender_name', event.target.value)} required />
                    </label>

                    <label>
                        <span>Bên nhận<span className="text-red">(*)</span></span>
                        <input value={form.receiver_name ?? ''} onChange={(event) => onChange('receiver_name', event.target.value)} required />
                    </label>

                    <label>
                        <span>Ghi chú<span className="text-red">(*)</span></span>
                        <input value={form.note ?? ''} onChange={(event) => onChange('note', event.target.value)} required />
                    </label>

                    <label>
                        <span>Trạng thái</span>
                        <select value={form.status ?? 'updating'} onChange={(event) => onChange('status', event.target.value)}>
                            <option value="updating">Đang cập nhật</option>
                            <option value="closed">Đã chốt</option>
                        </select>
                    </label>

                    <div className="ps-handover-modal-actions">
                        <button className="btn btn-primary btn-sm" type="submit" disabled={processing}>
                            <i className="fa fa-plus" /> {mode === 'edit' ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function StatusBadge({ value }) {
    const closed = String(value ?? '').toLowerCase().includes('chốt') || String(value ?? '').toLowerCase() === 'closed';
    return <span className={`ps-handover-status ${closed ? 'is-closed' : 'is-updating'}`}>{value || 'Đang cập nhật'}</span>;
}

export default function WarehouseDeliveryHandoverPage({ schema, rows = [], pagination = {}, filterOptions = {}, routeUrl, pageRuntimeError = null }) {
    const { ask } = useConfirm();
    const [filters, setFilters] = useState(() => ({
        handover_status: valueFromSearch('handover_status', '-1'),
        shipping_method: valueFromSearch('shipping_method', '-1'),
        search: valueFromSearch('search'),
        per_page: valueFromSearch('per_page', pagination?.per_page ?? 20),
    }));
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(() => emptyForm());
    const [processing, setProcessing] = useState(false);

    const providers = useMemo(() => filterOptions.shippingProviders ?? [], [filterOptions.shippingProviders]);
    const defaultSender = rows.find((row) => row?.sender_name)?.sender_name ?? '';

    const submitFilters = (event) => {
        event.preventDefault();
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '' || value === '-1') return;
            params.set(key, String(value));
        });
        router.get(routeUrl, Object.fromEntries(params.entries()), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
    };

    const setFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
    const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    const openCreate = () => {
        setEditingId(null);
        setForm(emptyForm(defaultSender));
        setModalOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id ?? row.id);
        setForm({
            ...emptyForm(defaultSender),
            ...(row._form ?? {}),
            document_date: toInputDate(row._form?.document_date ?? row.document_date),
            status: row._form?.status ?? row._status ?? (String(row.status ?? '').includes('chốt') ? 'closed' : 'updating'),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingId(null);
        setProcessing(false);
    };

    const submitModal = (event) => {
        event.preventDefault();
        setProcessing(true);
        const payload = { payload: form };
        const options = {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: closeModal,
        };

        if (editingId) {
            router.patch(`${routeUrl}/records/${editingId}`, payload, options);
            return;
        }

        router.post(`${routeUrl}/records`, payload, options);
    };

    const destroyRow = async (row) => {
        const id = row._record_id ?? row.id;
        if (!id) return;
        const ok = await ask({ description: 'Chắc chắn bạn muốn xóa biên bản này?', confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout activeMenuCode="5.4">
            <Head title={schema?.title ?? 'Danh sách biên bản'} />
            <form id="ps-handover-search-form" className="ps-handover-filter-form" onSubmit={submitFilters} hidden aria-hidden="true" />
            <PushsalePageShell
                className="ps-handover-page ps-template-page"
                pageCode="5.4"
                title={schema?.title ?? 'Danh sách biên bản'}
                headerClassName="ps-handover-header"
                defaultFiltersCollapsed={false}
                filters={(
                    <div className="ps-handover-filters">
                        <select form="ps-handover-search-form" name="handover_status" value={filters.handover_status} onChange={(event) => setFilter('handover_status', event.target.value)}>
                            {statuses.map((status) => <option key={status.id} value={status.id}>{status.label}</option>)}
                        </select>
                        <select form="ps-handover-search-form" name="shipping_method" value={filters.shipping_method} onChange={(event) => setFilter('shipping_method', event.target.value)}>
                            <option value="-1">--Chọn đơn vị giao hàng--</option>
                            {providers.map((provider) => <option key={provider.id} value={provider.id}>{optionLabel(provider)}</option>)}
                        </select>
                        <input
                            className="form-control"
                            form="ps-handover-search-form"
                            name="search"
                            value={filters.search}
                            onChange={(event) => setFilter('search', event.target.value)}
                            placeholder="Tìm kiếm"
                        />
                    </div>
                )}
                actions={(
                    <div className="ps-handover-actions-row">
                        <button type="submit" form="ps-handover-search-form" className="btn btn-primary btn-sm">
                            <i className="fa fa-search" /> Tìm kiếm
                        </button>
                        <button type="button" className="btn btn-primary btn-sm" onClick={openCreate}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                    </div>
                )}
            >
                {pageRuntimeError ? <div className="alert alert-warning">{pageRuntimeError}</div> : null}

                <div className="ps-handover-table-wrap">
                    <table className="table table-bordered ps-handover-table">
                        <thead>
                            <tr>
                                <th className="text-center">#</th>
                                <th className="text-center">Tên quản lý</th>
                                <th className="text-center">Tên biên bản</th>
                                <th className="text-center">Ngày biên bản</th>
                                <th className="text-center">Đơn vị giao hàng</th>
                                <th className="text-center">Số đơn</th>
                                <th className="text-center">Số sản phẩm</th>
                                <th className="text-center">Trạng thái</th>
                                <th className="text-center">Cập nhật</th>
                                <th className="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-center ps-empty-row">Không có dữ liệu.</td>
                                </tr>
                            ) : rows.map((row, index) => (
                                <tr key={row._record_id ?? row.id ?? index}>
                                    <td className="text-center">{pagination?.from ? pagination.from + index : index + 1}</td>
                                    <td>{row.manager || '—'}</td>
                                    <td>{row.name || '—'}</td>
                                    <td className="text-center">{formatDate(row.document_date)}</td>
                                    <td>{row.carrier || '—'}</td>
                                    <td className="text-center">{row.order_count ?? 0}</td>
                                    <td className="text-center">{row.product_count ?? 0}</td>
                                    <td className="text-center"><StatusBadge value={row.status} /></td>
                                    <td className="text-center">{formatDateTime(row.updated_at)}</td>
                                    <td className="text-center ps-handover-actions">
                                        <button type="button" className="btn-icon" aria-label="Chi tiết" onClick={() => openEdit(row)}><i className="fa fa-edit" /></button>
                                        <button type="button" className="btn-icon" aria-label="Xóa" onClick={() => destroyRow(row)}><i className="fa fa-trash" /></button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination
                    meta={pagination}
                    routeUrl={routeUrl}
                    filters={currentFilters()}
                    itemLabel="biên bản"
                    perPageOptions={[20, 50, 100, 200]}
                />

                <HandoverModal
                    open={modalOpen}
                    mode={editingId ? 'edit' : 'create'}
                    form={form}
                    providers={providers}
                    onChange={setField}
                    onClose={closeModal}
                    onSubmit={submitModal}
                    processing={processing}
                />
            </PushsalePageShell>
        </AppLayout>
    );
}
