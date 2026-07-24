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

export default function ConnectShops({ filters = {}, platforms = [], warehouses = [], marketingSources = [], rows = {}, routeUrl = '/admin/ecommerce/connect-shops', storeUrl = '/admin/ecommerce/connect-shops' }) {
    const { draft, set } = useDraft(filters);
    const dataRows = useRows(rows);
    const [modalOpen, setModalOpen] = useState(false);
    const [form, setForm] = useState(emptyForm(filters));
    const { toast, setToast } = useLocalToast();

    const search = () => router.get(routeUrl, draft, { preserveScroll: true, preserveState: true, replace: true });
    const openCreate = () => { setForm(emptyForm(draft)); setModalOpen(true); };
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
        setModalOpen(true);
    };
    const save = (event) => {
        event.preventDefault();
        const url = form.id ? `${storeUrl}/${form.id}` : storeUrl;
        const method = form.id ? 'patch' : 'post';
        router[method](url, form, { preserveScroll: true, onSuccess: () => setModalOpen(false) });
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
                            <th className="text-center no-wrap" style={{ width: 90 }}><button type="button" className="btn-icon ps-btn-link" onClick={openCreate}><i className="fa fa-plus" /> <span>Thêm</span></button></th>
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
                        <form className="modal-content" onSubmit={save}>
                            <div className="ps-popup-title"><strong>{form.platform === 'shopee' ? 'Kết nối shopee' : 'Kết nối tiktok'}</strong><button type="button" onClick={() => setModalOpen(false)}>×</button></div>
                            <div className="ps-ecommerce-connect-form">
                                <label>Loại sàn <span>*</span></label>
                                <select value={form.platform} onChange={(event) => setForm({ ...form, platform: event.target.value })}>{optionNodes(platforms)}</select>
                                <label>Kho <span>*</span></label>
                                <select value={form.warehouse_id} onChange={(event) => setForm({ ...form, warehouse_id: event.target.value })}>{optionNodes(warehouses, '--Chọn kho--')}</select>
                                <label>Nguồn dữ liệu <span>*</span></label>
                                <select value={form.marketing_source_id} onChange={(event) => setForm({ ...form, marketing_source_id: event.target.value })}>{optionNodes(marketingSources, '--Chọn nguồn dữ liệu--')}</select>
                                <label>Id Shop <span>*</span></label>
                                <input value={form.shop_id} onChange={(event) => setForm({ ...form, shop_id: event.target.value })} placeholder="Ví dụ: 749122001" />
                                <label>Tên shop <span>*</span></label>
                                <input value={form.shop_name} onChange={(event) => setForm({ ...form, shop_name: event.target.value })} placeholder="Tên shop hiển thị trên sàn" />
                                <label>Logo</label>
                                <input value={form.logo_url} onChange={(event) => setForm({ ...form, logo_url: event.target.value })} placeholder="URL logo" />
                                <label>Ghi chú</label>
                                <textarea value={form.note} onChange={(event) => setForm({ ...form, note: event.target.value })} rows={3} />
                            </div>
                            <div className="ps-ecommerce-modal-actions"><button type="submit" className="btn btn-sm btn-primary"><i className="fa fa-plus" /> {form.id ? 'Cập nhật' : 'Thêm mới'}</button></div>
                        </form>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}
