import { Head, router } from '@inertiajs/react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { optionNodes, PushsaleToast, SimplePagination, useDraft, useLocalToast, useRows } from './ecommerceUtils';

export default function SyncErrors({ filters = {}, platforms = [], warehouses = [], shops = [], rows = {}, routeUrl = '/admin/ecommerce/sync-errors' }) {
    const { draft, set } = useDraft(filters);
    const dataRows = useRows(rows);
    const { toast, setToast } = useLocalToast();

    const search = () => router.get(routeUrl, draft, { preserveScroll: true, preserveState: true, replace: true });
    const fetchMissing = () => {
        if (!draft.shop_id) {
            setToast({ type: 'warning', message: 'Hãy chọn shop.' });
            return;
        }
        router.post('/admin/ecommerce/sync-errors/fetch-missing-orders', draft, { preserveScroll: true });
    };
    const exportCsv = () => window.location.href = '/admin/ecommerce/sync-errors/export';

    const primaryFilters = (
        <div className="ps-ecommerce-filter-grid is-error-list">
            <select value={draft.platform ?? 'tiktok'} onChange={(event) => set('platform', event.target.value)}>{optionNodes(platforms)}</select>
            <select value={draft.warehouse_id ?? ''} onChange={(event) => set('warehouse_id', event.target.value)}>{optionNodes(warehouses, '--Chọn kho--')}</select>
            <select value={draft.shop_id ?? ''} onChange={(event) => set('shop_id', event.target.value)}>{optionNodes(shops, '--Chọn shop--')}</select>
            <input value={draft.keyword ?? ''} onChange={(event) => set('keyword', event.target.value)} placeholder="Mã đơn hàng đối tác" />
        </div>
    );

    const advancedFilters = <div className="ps-ecommerce-filter-grid is-error-advanced"><input value={draft.date_range ?? ''} onChange={(event) => set('date_range', event.target.value)} /><button type="button" className="btn btn-sm btn-primary" onClick={fetchMissing}><i className="fa fa-search" /> Lấy danh sách đơn chưa có trên hệ thống</button></div>;

    return (
        <AppLayout>
            <Head title="Danh sách đơn hàng lỗi" />
            <PushsaleToast toast={toast} onClose={() => setToast(null)} />
            <PushsalePageShell
                title="Danh sách đơn hàng lỗi"
                className="ps-ecommerce-page ps-ecommerce-error-page"
                primaryFilters={primaryFilters}
                advancedFilters={advancedFilters}
                actions={<><button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button><button type="button" className="btn btn-sm btn-primary" onClick={exportCsv}><i className="fa fa-file-excel-o" /> Xuất Excel</button></>}
                defaultFiltersCollapsed={false}
            >
                <div className="ps-ecommerce-table-wrap">
                    <table className="table table-bordered ps-ecommerce-table" id="tblOrderError">
                        <thead><tr><th className="text-center" style={{ width: 60 }}>STT</th><th className="text-center no-wrap">Mã đơn đối tác</th><th className="text-center no-wrap">Mô tả lỗi</th><th className="text-center no-wrap">Cập nhật</th><th /></tr></thead>
                        <tbody>
                            {dataRows.length ? dataRows.map((row, index) => (
                                <tr key={row.id}><td className="text-center">{rows.from ? rows.from + index : index + 1}</td><td className="text-center">{row.partnerOrderId}</td><td>{row.errorDescription}</td><td className="text-center">{row.updatedAt || '—'}</td><td /></tr>
                            )) : <tr><td colSpan={5} className="text-center ps-empty">Không có dữ liệu.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <SimplePagination rows={rows} />
            </PushsalePageShell>
        </AppLayout>
    );
}
