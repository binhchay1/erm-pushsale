import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';
import { optionNodes, PushsaleToast, SimplePagination, useDraft, useLocalToast, useRows } from './ecommerceUtils';

export default function ConnectProducts({ filters = {}, platforms = [], warehouses = [], shops = [], products = [], rows = {}, routeUrl = '/admin/ecommerce/connect-products' }) {
    const { ask } = useConfirm();
    const { draft, set } = useDraft(filters);
    const dataRows = useRows(rows);
    const { toast, setToast } = useLocalToast();
    const [editing, setEditing] = useState(null);

    const search = () => router.get(routeUrl, draft, { preserveScroll: true, preserveState: true, replace: true });
    const sync = async () => {
        if (!draft.shop_id) {
            setToast({ type: 'warning', message: 'Hãy chọn shop.' });
            return;
        }
        const ok = await ask({ description: 'Bạn muốn lấy danh sách sản phẩm từ sàn TMĐT?' });
        if (ok) {
            router.post('/admin/ecommerce/connect-products/sync', draft, { preserveScroll: true });
        }
    };
    const saveMapping = () => {
        router.patch(`/admin/ecommerce/connect-products/${editing.id}`, editing, { preserveScroll: true, onSuccess: () => setEditing(null) });
    };

    const primaryFilters = (
        <div className="ps-ecommerce-filter-grid is-product-list">
            <select value={draft.platform ?? 'tiktok'} onChange={(event) => set('platform', event.target.value)}>{optionNodes(platforms)}</select>
            <select value={draft.warehouse_id ?? ''} onChange={(event) => set('warehouse_id', event.target.value)}>{optionNodes(warehouses, '--Chọn kho--')}</select>
            <select value={draft.shop_id ?? ''} onChange={(event) => set('shop_id', event.target.value)}>{optionNodes(shops, '--Chọn shop--')}</select>
            <select value={draft.status ?? '-1'} onChange={(event) => set('status', event.target.value)}>
                <option value="-1">--Chọn trạng thái liên kết--</option><option value="0">Chưa liên kết</option><option value="1">Đã liên kết</option>
            </select>
            <input value={draft.keyword ?? ''} onChange={(event) => set('keyword', event.target.value)} placeholder="Tên hoặc Id sản phẩm" />
        </div>
    );

    return (
        <AppLayout>
            <Head title="Danh sách sản phẩm TMĐT" />
            <PushsaleToast toast={toast} onClose={() => setToast(null)} />
            <PushsalePageShell
                title="Danh sách sản phẩm TMĐT"
                className="ps-ecommerce-page ps-ecommerce-product-page"
                primaryFilters={primaryFilters}
                actions={<><button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button><button type="button" className="btn btn-sm btn-primary" onClick={sync}><i className="fa fa-refresh" /> Đồng bộ sàn TMĐT</button></>}
                collapsible={false}
            >
                <div className="ps-ecommerce-table-wrap">
                    <table className="table table-bordered ps-ecommerce-table ps-ecommerce-product-table">
                        <thead>
                            <tr><th rowSpan="2" className="text-center">STT</th><th colSpan="2" className="text-center">Sản phẩm trên sàn TMĐT</th><th colSpan="2" className="text-center">Sản phẩm trên hệ thống pushsale</th><th colSpan="1" className="text-center">Đồng bộ</th><th rowSpan="2" className="text-center">Trạng thái<br />kết nối</th><th rowSpan="2" className="text-center">Ghi chú</th><th rowSpan="2" /></tr>
                            <tr><th className="text-center">Id sản phẩm</th><th className="text-center">Tên sản phẩm<br />Sku (SkuId)<br />[Thuộc tính 1]+[Thuộc tính 2]+[Thuộc tính 3]</th><th className="text-center">Sku</th><th className="text-center">Tên sản phẩm</th><th className="text-center">Số lượng</th></tr>
                        </thead>
                        <tbody>
                            {dataRows.length ? dataRows.map((row, index) => (
                                <tr key={row.id}>
                                    <td className="text-center">{rows.from ? rows.from + index : index + 1}</td>
                                    <td className="text-center">{row.externalProductId}</td>
                                    <td><strong>{row.externalName}</strong><div className="text-muted">{row.externalSku} ({row.externalSkuId})</div><div>{row.externalAttributes}</div></td>
                                    <td className="text-center">{row.productSku || '—'}</td>
                                    <td>{row.productName || '—'}</td>
                                    <td className="text-center">{row.syncQuantity}</td>
                                    <td className="text-center"><span className={`ps-status ${row.connectionStatus === 'linked' ? 'is-ok' : 'is-warn'}`}>{row.connectionStatus === 'linked' ? 'Đã liên kết' : 'Chưa liên kết'}</span></td>
                                    <td>{row.note || '—'}</td>
                                    <td className="text-center"><button type="button" className="btn-icon ps-action-icon" onClick={() => setEditing({ id: row.id, product_id: row.productId || '', sync_quantity: row.syncQuantity || 0, note: row.note || '' })}><i className="fa fa-edit" /></button></td>
                                </tr>
                            )) : <tr><td colSpan={9} className="text-center ps-empty">Không có dữ liệu.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <SimplePagination rows={rows} />
            </PushsalePageShell>

            {editing ? (
                <div className="modal fade modal-common in ps-modal-backdrop" style={{ display: 'block' }}>
                    <div className="modal-dialog modal-lg ps-ecommerce-map-modal"><div className="modal-content">
                        <div className="ps-popup-title"><strong>Liên kết sản phẩm</strong><button type="button" onClick={() => setEditing(null)}>×</button></div>
                        <div className="ps-ecommerce-connect-form">
                            <label>Sản phẩm hệ thống</label><select value={editing.product_id} onChange={(event) => setEditing({ ...editing, product_id: event.target.value })}>{optionNodes(products, '--Chọn sản phẩm--')}</select>
                            <label>Số lượng đồng bộ</label><input type="number" value={editing.sync_quantity} onChange={(event) => setEditing({ ...editing, sync_quantity: event.target.value })} />
                            <label>Ghi chú</label><textarea rows={3} value={editing.note} onChange={(event) => setEditing({ ...editing, note: event.target.value })} />
                        </div>
                        <div className="ps-ecommerce-modal-actions"><button type="button" className="btn btn-sm btn-primary" onClick={saveMapping}><i className="fa fa-save" /> Cập nhật</button></div>
                    </div></div>
                </div>
            ) : null}
        </AppLayout>
    );
}
