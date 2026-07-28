import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

const NUMBER_TYPES = [
    { value: 'new', label: 'Số mới' },
    { value: 'old', label: 'Khách cũ' },
    { value: 'care', label: 'CSKH' },
];
const RECIPIENT_TYPES = [
    { value: 'sales', label: 'Sales' },
    { value: 'care', label: 'CSKH' },
    { value: 'both', label: 'Sales + CSKH' },
];
const ALLOCATION_METHODS = [
    { value: 'round_robin', label: 'Luân phiên' },
    { value: 'quota', label: 'Theo định mức' },
    { value: 'manual', label: 'Thủ công' },
];

const emptyForm = {
    name: '',
    number_type: 'new',
    recipient_type: 'sales',
    allocation_method: 'round_robin',
    product_ids: [],
    sale_user_ids: [],
    care_user_ids: [],
    is_active: true,
};

function currentFilters() {
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function formatDate(value) {
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

function flattenErrors(errors = {}) {
    return Object.values(errors).flatMap((value) => (Array.isArray(value) ? value : [value])).filter(Boolean);
}

function MultiSelect({ options = [], value = [], onChange, size = 5 }) {
    return (
        <select
            className="form-control"
            multiple
            size={size}
            value={value.map(String)}
            onChange={(event) => onChange([...event.target.selectedOptions].map((option) => option.value))}
        >
            {options.map((item) => (
                <option key={item.id} value={String(item.id)}>{item.label ?? item.name}</option>
            ))}
        </select>
    );
}

export default function LeadDistributionRules({
    rows = [],
    pagination,
    routeUrl = '/admin/hr/lead-distribution-rules',
    filterOptions = {},
}) {
    const { ask } = useConfirm();
    const params = currentFilters();
    const [search, setSearch] = useState(params.search ?? '');
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const products = filterOptions.products ?? [];
    const sales = filterOptions.sales ?? [];
    const careUsers = filterOptions.careUsers ?? [];
    const fieldError = (key) => form.errors[key] ?? form.errors[`payload.${key}`] ?? '';

    const runSearch = (event) => {
        event?.preventDefault?.();
        router.get(routeUrl, search.trim() ? { search: search.trim() } : {}, { replace: true, preserveState: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setEditorOpen(true);
    };

    const openEdit = (row) => {
        const payload = row._form ?? {};
        setEditingId(row._record_id);
        form.setData({
            name: payload.name ?? row.name ?? '',
            number_type: payload.number_type ?? 'new',
            recipient_type: payload.recipient_type ?? 'sales',
            allocation_method: payload.allocation_method ?? 'round_robin',
            product_ids: Array.isArray(payload.product_ids) ? payload.product_ids.map(String) : [],
            sale_user_ids: Array.isArray(payload.sale_user_ids) ? payload.sale_user_ids.map(String) : [],
            care_user_ids: Array.isArray(payload.care_user_ids) ? payload.care_user_ids.map(String) : [],
            is_active: payload.is_active !== false,
        });
        form.clearErrors();
        setEditorOpen(true);
    };

    const validateClient = () => {
        const next = {};
        if (!String(form.data.name ?? '').trim()) next.name = 'Tên cấu hình bắt buộc.';
        if (!form.data.number_type) next.number_type = 'Kiểu số bắt buộc.';
        if (!form.data.recipient_type) next.recipient_type = 'Người nhận bắt buộc.';
        if (!form.data.allocation_method) next.allocation_method = 'Cách chia bắt buộc.';
        return next;
    };

    const save = (event) => {
        event.preventDefault();
        form.clearErrors();
        const clientErrors = validateClient();
        if (Object.keys(clientErrors).length) {
            Object.entries(clientErrors).forEach(([key, message]) => form.setError(key, message));
            toast.error('Vui lòng nhập các trường bắt buộc.');
            return;
        }

        const payload = {
            name: String(form.data.name).trim(),
            number_type: form.data.number_type,
            recipient_type: form.data.recipient_type,
            allocation_method: form.data.allocation_method,
            product_ids: form.data.product_ids.map(Number),
            sale_user_ids: form.data.sale_user_ids.map(Number),
            care_user_ids: form.data.care_user_ids.map(Number),
            is_active: Boolean(form.data.is_active),
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setEditorOpen(false);
            },
            onError: (errors) => {
                Object.entries(errors ?? {}).forEach(([key, value]) => {
                    form.setError(key.replace(/^payload\./, ''), Array.isArray(value) ? value[0] : value);
                });
                toast.error(flattenErrors(errors).join(' · ') || 'Không lưu được cấu hình.');
            },
        };

        if (editingId) router.put(`${routeUrl}/records/${editingId}`, { payload }, options);
        else router.post(`${routeUrl}/records`, { payload }, options);
    };

    const destroy = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({
            title: 'Xóa cấu hình chia số',
            description: `Bạn chắc chắn muốn xóa cấu hình "${row.name || 'không tên'}"? Hành động này không thể hoàn tác.`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, {
            preserveScroll: true,
            onError: () => toast.error('Không xóa được cấu hình.'),
        });
    };

    return (
        <AppLayout>
            <Head title="Danh sách cấu hình chia số" />
            <PushsalePageShell
                title="Danh sách cấu hình chia số"
                pageCode="1.2.4"
                className="ps-hr-config-page ps-lead-dist-page"
                collapsible={false}
                actions={(
                    <form className="ps-hr-config-search" onSubmit={runSearch}>
                        <input
                            className="form-control"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Tìm tên cấu hình"
                        />
                        <PushsaleSearchButton type="submit" label="Tìm kiếm" />
                    </form>
                )}
                toolbar={(
                    <div className="ps-hr-config-toolbar">
                        <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                    </div>
                )}
            >
                <div className="ps-hr-config-table-wrap">
                    <table className="table table-bordered ps-hr-config-table">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: 56 }}>#</th>
                                <th className="text-center">Tên</th>
                                <th className="text-center">Kiểu số - Người nhận - Cách chia</th>
                                <th className="text-center">Sản phẩm</th>
                                <th className="text-center">Sales</th>
                                <th className="text-center">CSKH</th>
                                <th className="text-center" style={{ width: 140 }}>Cập nhật</th>
                                <th className="text-center ps-col-add" style={{ width: 88 }}>
                                    <button type="button" className="btn-icon ps-th-add" title="Thêm mới" onClick={openCreate}>
                                        <i className="fa fa-plus" /> <span>Thêm</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row._record_id ?? row.id}>
                                    <td className="text-center">{row.id}</td>
                                    <td>{row.name}</td>
                                    <td>{row.allocation_rule}</td>
                                    <td>{row.products || '—'}</td>
                                    <td>{row.sales || '—'}</td>
                                    <td>{row.care_users || '—'}</td>
                                    <td className="text-center no-wrap">{formatDate(row.updated_at)}</td>
                                    <td className="text-center">
                                        <div className="ps-hr-config-actions">
                                            <button type="button" className="btn-icon" title="Cập nhật" onClick={() => openEdit(row)}>
                                                <i className="fa fa-pencil" />
                                            </button>
                                            <button type="button" className="btn-icon" title="Xóa" onClick={() => destroy(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={8} className="text-center ps-hr-config-empty">Chưa có cấu hình chia số.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="cấu hình" />
            </PushsalePageShell>

            <PushsaleDialog
                open={editorOpen}
                onOpenChange={(open) => !open && setEditorOpen(false)}
                title={editingId ? 'Cập nhật cấu hình chia số' : 'Thêm mới cấu hình chia số'}
                width="720px"
                className="ps-report-access-dialog"
            >
                <form className="ps-report-access-form" onSubmit={save} noValidate style={{ maxWidth: 680 }}>
                    <div className="ps-report-access-row">
                        <label>Tên cấu hình <span className="required">(*)</span></label>
                        <div>
                            <input className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                            {fieldError('name') ? <div className="ps-report-access-error">{fieldError('name')}</div> : null}
                        </div>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Kiểu số <span className="required">(*)</span></label>
                        <select className="form-control" value={form.data.number_type} onChange={(event) => form.setData('number_type', event.target.value)}>
                            {NUMBER_TYPES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Người nhận <span className="required">(*)</span></label>
                        <select className="form-control" value={form.data.recipient_type} onChange={(event) => form.setData('recipient_type', event.target.value)}>
                            {RECIPIENT_TYPES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Cách chia <span className="required">(*)</span></label>
                        <select className="form-control" value={form.data.allocation_method} onChange={(event) => form.setData('allocation_method', event.target.value)}>
                            {ALLOCATION_METHODS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Sản phẩm</label>
                        <MultiSelect options={products} value={form.data.product_ids} onChange={(value) => form.setData('product_ids', value)} />
                    </div>
                    <div className="ps-report-access-row">
                        <label>Sales</label>
                        <MultiSelect options={sales} value={form.data.sale_user_ids} onChange={(value) => form.setData('sale_user_ids', value)} />
                    </div>
                    <div className="ps-report-access-row">
                        <label>CSKH</label>
                        <MultiSelect options={careUsers} value={form.data.care_user_ids} onChange={(value) => form.setData('care_user_ids', value)} />
                    </div>
                    <div className="ps-report-access-row">
                        <label>Đang áp dụng</label>
                        <label style={{ fontWeight: 400, margin: 0 }}>
                            <input type="checkbox" checked={Boolean(form.data.is_active)} onChange={(event) => form.setData('is_active', event.target.checked)} /> Áp dụng
                        </label>
                    </div>
                    <div className="ps-report-access-actions">
                        <button type="submit" className="btn btn-sm btn-primary" disabled={form.processing}>
                            <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : (editingId ? 'fa-save' : 'fa-plus')}`} />{' '}
                            {editingId ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
