import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { optionNodes, PushsaleToast, SimplePagination, useDraft, useLocalToast, useRows } from './ecommerceUtils';

const emptyForm = (filters = {}) => ({
    id: null,
    platform: filters.platform || 'tiktok',
    warehouse_id: filters.warehouse_id || '',
    marketing_source_id: '',
    shop_id: '',
    shop_name: '',
    logo_url: '',
    note: '',
    logistics_mode: 0,
});

const REQUIRED_FIELDS = [
    ['platform', 'Loại sàn'],
    ['warehouse_id', 'Kho'],
    ['marketing_source_id', 'Nguồn dữ liệu'],
    ['shop_id', 'Id Shop'],
    ['shop_name', 'Tên shop'],
];

function Field({ label, required = false, error = '', children }) {
    return (
        <>
            <label className={error ? 'is-invalid-label' : undefined}>
                {label}
                {required ? <span>*</span> : null}
            </label>
            <div className="ps-ecommerce-field">
                {children}
                {error ? <small className="ps-field-error">{error}</small> : null}
            </div>
        </>
    );
}

export default function ConnectShops({ filters = {}, platforms = [], warehouses = [], marketingSources = [], rows = {}, routeUrl = '/admin/ecommerce/connect-shops', storeUrl = '/admin/ecommerce/connect-shops' }) {
    const { draft, set } = useDraft(filters);
    const dataRows = useRows(rows);
    const [modalOpen, setModalOpen] = useState(false);
    const [form, setForm] = useState(emptyForm(filters));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const { toast, setToast } = useLocalToast();

    const search = () => router.get(routeUrl, draft, { preserveScroll: true, preserveState: true, replace: true });
    const openCreate = () => {
        setForm(emptyForm(draft));
        setErrors({});
        setModalOpen(true);
    };
    const openEdit = (row) => {
        setForm({
            id: row.id,
            platform: row.platform,
            warehouse_id: row.warehouseId || '',
            marketing_source_id: row.marketingSourceId || '',
            shop_id: row.shopId || '',
            shop_name: row.shopName || '',
            logo_url: row.logoUrl || '',
            note: row.note || '',
            logistics_mode: 0,
        });
        setErrors({});
        setModalOpen(true);
    };

    const setField = (key, value) => {
        setForm((current) => ({ ...current, [key]: value }));
        if (errors[key]) setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const validateClient = () => {
        const next = {};
        REQUIRED_FIELDS.forEach(([key, label]) => {
            if (!String(form[key] ?? '').trim()) next[key] = `${label} bắt buộc.`;
        });
        return next;
    };

    const save = (event) => {
        event.preventDefault();
        const clientErrors = validateClient();
        if (Object.keys(clientErrors).length) {
            setErrors(clientErrors);
            return;
        }

        const url = form.id ? `${storeUrl}/${form.id}` : storeUrl;
        const method = form.id ? 'patch' : 'post';
        setSaving(true);
        router[method](url, form, {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                setErrors({});
            },
            onError: (serverErrors) => {
                const next = {};
                Object.entries(serverErrors || {}).forEach(([key, message]) => {
                    next[key] = Array.isArray(message) ? message.join(' ') : String(message);
                });
                setErrors(next);
            },
            onFinish: () => setSaving(false),
        });
    };

    const primaryFilters = (
        <div className="ps-ecommerce-filter-grid is-shop-list">
            <select value={draft.platform ?? 'tiktok'} onChange={(event) => set('platform', event.target.value)}>{optionNodes(platforms)}</select>
            <select value={draft.warehouse_id ?? ''} onChange={(event) => set('warehouse_id', event.target.value)}>{optionNodes(warehouses, '--Chọn kho--')}</select>
            <input value={draft.keyword ?? ''} onChange={(event) => set('keyword', event.target.value)} placeholder="Tên hoặc ID shop" />
        </div>
    );

    return (
        <AppLayout>
            <Head title="Danh sách kết nối sàn thương mại điện tử" />
            <PushsaleToast toast={toast} onClose={() => setToast(null)} />
            <PushsalePageShell
                title="Danh sách kết nối sàn thương mại điện tử"
                className="ps-ecommerce-page ps-ecommerce-shop-page"
                headerClassName="ps-ecommerce-page ps-ecommerce-shop-page"
                primaryFilters={primaryFilters}
                actions={<button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button>}
                collapsible={false}
            >
                <div className="ps-ecommerce-table-wrap">
                    <table className="table table-bordered ps-ecommerce-table">
                        <thead><tr>
                            <th className="text-center" style={{ width: 60 }}>STT</th>
                            <th className="text-center no-wrap">Loại sàn</th>
                            <th className="text-center no-wrap">Tên Kho</th>
                            <th className="text-center no-wrap">Id Shop</th>
                            <th className="text-center no-wrap">Tên shop</th>
                            <th className="text-center no-wrap">Logo</th>
                            <th className="text-center no-wrap">Ghi chú</th>
                            <th className="text-center no-wrap">Cập nhật</th>
                            <th className="text-center no-wrap ps-ecommerce-th-add-col">
                                <button type="button" className="ps-ecommerce-th-add" onClick={openCreate}>
                                    <i className="fa fa-plus" aria-hidden="true" />
                                    <span>Thêm</span>
                                </button>
                            </th>
                        </tr></thead>
                        <tbody>
                            {dataRows.length ? dataRows.map((row, index) => (
                                <tr key={row.id}>
                                    <td className="text-center">{rows.from ? rows.from + index : index + 1}</td>
                                    <td className="text-center">{row.platformLabel}</td>
                                    <td>{row.warehouseName}</td>
                                    <td className="text-center">{row.shopId || '—'}</td>
                                    <td>{row.shopName || '—'}</td>
                                    <td className="text-center">{row.logoUrl ? <img src={row.logoUrl} className="ps-ecommerce-logo" alt="logo" /> : '—'}</td>
                                    <td>{row.note || '—'}</td>
                                    <td className="text-center">{row.updatedAt || '—'}</td>
                                    <td className="text-center"><button type="button" className="btn-icon ps-action-icon" onClick={() => openEdit(row)}><i className="fa fa-edit" /></button></td>
                                </tr>
                            )) : <tr><td colSpan={9} className="text-center ps-empty">Không có dữ liệu.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <SimplePagination rows={rows} />
            </PushsalePageShell>

            {modalOpen ? (
                <div className="modal fade modal-common in ps-modal-backdrop" style={{ display: 'block' }}>
                    <div className="modal-dialog modal-lg ps-ecommerce-shop-modal">
                        <form className="modal-content" onSubmit={save} noValidate>
                            <div className="ps-popup-title"><strong>{form.platform === 'shopee' ? 'Kết nối shopee' : 'Kết nối tiktok'}</strong><button type="button" onClick={() => setModalOpen(false)}>×</button></div>
                            <div className="ps-ecommerce-connect-form">
                                <Field label="Loại sàn" required error={errors.platform}>
                                    <select className={errors.platform ? 'is-invalid' : undefined} value={form.platform} onChange={(event) => setField('platform', event.target.value)}>{optionNodes(platforms)}</select>
                                </Field>
                                <Field label="Kho" required error={errors.warehouse_id}>
                                    <select className={errors.warehouse_id ? 'is-invalid' : undefined} value={form.warehouse_id} onChange={(event) => setField('warehouse_id', event.target.value)}>{optionNodes(warehouses, '--Chọn kho--')}</select>
                                </Field>
                                <Field label="Nguồn dữ liệu" required error={errors.marketing_source_id}>
                                    <select className={errors.marketing_source_id ? 'is-invalid' : undefined} value={form.marketing_source_id} onChange={(event) => setField('marketing_source_id', event.target.value)}>{optionNodes(marketingSources, '--Chọn nguồn dữ liệu--')}</select>
                                </Field>
                                <Field label="Id Shop" required error={errors.shop_id}>
                                    <input className={errors.shop_id ? 'is-invalid' : undefined} value={form.shop_id} onChange={(event) => setField('shop_id', event.target.value)} placeholder="Ví dụ: 749122001" />
                                </Field>
                                <Field label="Tên shop" required error={errors.shop_name}>
                                    <input className={errors.shop_name ? 'is-invalid' : undefined} value={form.shop_name} onChange={(event) => setField('shop_name', event.target.value)} placeholder="Tên shop hiển thị trên sàn" />
                                </Field>
                                <Field label="Logo" error={errors.logo_url}>
                                    <input value={form.logo_url} onChange={(event) => setField('logo_url', event.target.value)} placeholder="URL logo" />
                                </Field>
                                <Field label="Ghi chú" error={errors.note}>
                                    <textarea value={form.note} onChange={(event) => setField('note', event.target.value)} rows={3} />
                                </Field>
                            </div>
                            <div className="ps-ecommerce-modal-actions">
                                <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
                                    <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-plus'}`} /> {form.id ? 'Cập nhật' : 'Thêm mới'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}
