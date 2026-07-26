import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const number = new Intl.NumberFormat('vi-VN');

function DialogShell({ open, onClose, children }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title="XUẤT / NHẬP KHO"
            width="900px"
            bodyClassName="ps-source-dialog-body"
        >
            {children}
        </PushsaleDialog>
    );
}

export default function Inventory({ report, filterOptions = {}, intakeUrl, exportUrl, approverOptions = [] }) {
    const f = report?.filters ?? {};
    const rows = report?.rows?.data ?? [];
    const routeUrl = typeof window !== 'undefined' ? window.location.pathname : '/admin/warehouse/inventory';
    const [filters, setFilters] = useState({
        search: f.search ?? '', warehouse_id: String(f.warehouse_id ?? ''), product_id: String(f.product_id ?? ''),
        location_code: f.location_code ?? '', batch_code: f.batch_code ?? '', business_status: f.business_status ?? '',
    });
    const [movementOpen, setMovementOpen] = useState(false);
    const [mode, setMode] = useState('intake');
    const importRef = useRef(null);
    const movement = useForm({ warehouse_id: '', product_id: '', quantity: 1, approved_by_user_id: '', note: '' });

    const search = (event) => {
        event?.preventDefault();
        router.get(routeUrl, Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')), { preserveState: true, replace: true });
    };

    const openMovement = (nextMode) => { setMode(nextMode); movement.reset(); setMovementOpen(true); };
    const submitMovement = (event) => {
        event.preventDefault();
        movement.post(mode === 'intake' ? intakeUrl : exportUrl, { preserveScroll: true, onSuccess: () => setMovementOpen(false) });
    };

    const openVoucherEntry = () => {
        const params = new URLSearchParams();
        if (filters.warehouse_id) params.set('warehouse_id', filters.warehouse_id);
        if (filters.product_id) params.set('product_id', filters.product_id);
        const query = params.toString();
        router.visit(`/admin/warehouse/vouchers/entry${query ? `?${query}` : ''}`);
    };

    const exportCsv = () => {
        const headers = ['ID', 'Kho', 'Sản phẩm', 'SKU', 'Đơn vị tính', 'Mã lô', 'Ngày hết hạn', 'Vị trí', 'Tồn kho', 'Chờ xuất', 'Ngừng KD'];
        const lines = rows.map((row) => [row.id, row.warehouseName, row.productName, row.sku, row.uom, row.batchCode, row.expiryDate, row.locationCode, row.stockQuantity, row.pendingSalesQuantity, row.isDiscontinued ? 'Có' : 'Không']);
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const blob = new Blob(['\ufeff' + [headers, ...lines].map((line) => line.map(quote).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob); const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'danh-sach-san-pham-kho.csv'; anchor.click(); URL.revokeObjectURL(url);
    };

    return (
        <AppLayout>
            <Head title="Danh sách sản phẩm kho" />
            <section className="ps-adminlte-page ps-inventory-page" data-page-code="5.2.2">
                <PageHeader
                    title="Danh sách sản phẩm kho"
                    pageCode="5.2.2"
                    actions={(
                        <form className="ps-header-search" onSubmit={search}>
                            <input className="form-control" placeholder="Tên sản phẩm" value={filters.search} onChange={(event) => setFilters((old) => ({ ...old, search: event.target.value }))} />
                            <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
                        </form>
                    )}
                    advanced={(
                        <form className="ps-filter-row ps-inventory-filters" onSubmit={search}>
                            <select className="form-control" value={filters.warehouse_id} onChange={(event) => setFilters((old) => ({ ...old, warehouse_id: event.target.value }))}><option value="">--Chọn kho--</option>{(filterOptions.warehouses ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                            <select className="form-control" value={filters.product_id} onChange={(event) => setFilters((old) => ({ ...old, product_id: event.target.value }))}><option value="">--Chọn sản phẩm--</option>{(filterOptions.products ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}{item.sku ? ` (${item.sku})` : ''}</option>)}</select>
                            <input className="form-control" placeholder="Mã vị trí" value={filters.location_code} onChange={(event) => setFilters((old) => ({ ...old, location_code: event.target.value }))} />
                            <input className="form-control" placeholder="Mã lô" value={filters.batch_code} onChange={(event) => setFilters((old) => ({ ...old, batch_code: event.target.value }))} />
                            <select className="form-control" value={filters.business_status} onChange={(event) => setFilters((old) => ({ ...old, business_status: event.target.value }))}><option value="">--Trạng thái kinh doanh--</option><option value="active">Đang kinh doanh</option><option value="stopped">Ngừng kinh doanh</option></select>
                        </form>
                    )}
                />
                <div className="box-body ps-toolbar">
                    <button type="button" className="btn btn-sm btn-primary" onClick={openVoucherEntry}><i className="fa fa-exchange" /> Xuất / nhập kho</button>
                    <button type="button" className="btn btn-sm btn-default" onClick={() => openMovement('intake')}><i className="fa fa-map-marker" /> Cập nhật vị trí</button>
                    <button type="button" className="btn btn-sm btn-default" onClick={exportCsv}><i className="fa fa-file-excel-o" /> Xuất Excel</button>
                    <input ref={importRef} type="file" hidden />
                </div>
                <div className="ps-table-scroll"><table className="table table-bordered ps-source-table ps-inventory-table"><thead><tr><th><input type="checkbox" /></th><th>#</th><th>Kho</th><th>Sản phẩm</th><th>Đơn vị tính</th><th>Mã lô</th><th>Ngày hết hạn</th><th>Vị trí</th><th>Tồn kho</th><th>Chờ xuất bán hàng</th><th>SL sắp hết hàng</th><th>Ngừng KD</th><th>Cập nhật</th><th /></tr></thead><tbody>
                    {rows.length ? rows.map((row) => <tr key={row.id}><td className="text-center"><input type="checkbox" /></td><td className="text-center">{row.id}</td><td>{row.warehouseName}</td><td><strong>{row.productName}</strong>{row.sku && <small>({row.sku})</small>}</td><td className="text-center">{row.uom}</td><td className="text-center">{row.batchCode}</td><td className="text-center">{row.expiryDate}</td><td className="text-center">{row.locationCode}</td><td className="text-center"><strong>{number.format(row.stockQuantity)}</strong></td><td className="text-center">{number.format(row.pendingSalesQuantity)}</td><td className="text-center" /><td className="text-center"><input type="checkbox" readOnly checked={Boolean(row.isDiscontinued)} /></td><td className="text-center" /><td className="text-center ps-row-actions"><button type="button" onClick={() => window.confirm(`Xóa dòng tồn kho ${row.productName}?`) && router.delete(`/admin/warehouse-inventories/${row.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button></td></tr>) : <tr><td colSpan="14" className="ps-empty">Chưa có dữ liệu tồn kho.</td></tr>}
                </tbody></table></div>
                <PushsalePagination meta={report.rows} routeUrl={routeUrl} filters={filters} itemLabel="dòng tồn kho" />
            </section>

            <DialogShell open={movementOpen} onClose={() => setMovementOpen(false)}>
                <form onSubmit={submitMovement} className="ps-form-grid ps-form-grid-2">
                    <div className="span-2 ps-tabs"><button type="button" className={mode === 'intake' ? 'active' : ''} onClick={() => setMode('intake')}>Nhập kho</button><button type="button" className={mode === 'export' ? 'active' : ''} onClick={() => setMode('export')}>Xuất kho</button></div>
                    <label>Kho (*)<select className="form-control" value={movement.data.warehouse_id} onChange={(event) => movement.setData('warehouse_id', event.target.value)} required><option value="">--Chọn kho--</option>{(filterOptions.warehouses ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label>Sản phẩm (*)<select className="form-control" value={movement.data.product_id} onChange={(event) => movement.setData('product_id', event.target.value)} required><option value="">--Chọn sản phẩm--</option>{(filterOptions.products ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label>Số lượng (*)<input className="form-control" type="number" min="1" value={movement.data.quantity} onChange={(event) => movement.setData('quantity', Number(event.target.value))} required /></label>
                    <label>Người duyệt (*)<select className="form-control" value={movement.data.approved_by_user_id} onChange={(event) => movement.setData('approved_by_user_id', event.target.value)} required><option value="">--Chọn người duyệt--</option>{approverOptions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label className="span-2">Ghi chú<textarea className="form-control" value={movement.data.note} onChange={(event) => movement.setData('note', event.target.value)} /></label>
                    {Object.keys(movement.errors).length > 0 && <div className="alert alert-danger span-2">{Object.values(movement.errors).join(' · ')}</div>}
                    <div className="span-2"><button className="btn btn-primary" disabled={movement.processing}><i className="fa fa-save" /> Lưu phiếu</button></div>
                </form>
            </DialogShell>
        </AppLayout>
    );
}
