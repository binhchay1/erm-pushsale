import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const emptyWarehouse = {
    name: '',
    code: '',
    phone: '',
    pick_province: '',
    pick_district: '',
    pick_ward: '',
    address: '',
    manager_user_id: '',
    vtp_code: '',
    ghtk_pick_address_id: '',
};

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function DialogShell({ title, open, onClose, children, wide = false }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width={wide ? 'calc(100vw - 60px)' : '900px'}
            bodyClassName="ps-source-dialog-body"
        >
            {children}
        </PushsaleDialog>
    );
}

export default function WarehouseIndex({ warehouses, filters = {}, managers = [], provinces = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [manager, setManager] = useState(filters.manager_user_id ?? '');
    const [province, setProvince] = useState(filters.province ?? '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyWarehouse);
    const rows = warehouses?.data ?? [];

    const submitFilters = (event) => {
        event.preventDefault();
        router.get('/admin/warehouses', { search: search || null, manager_user_id: manager || null, province: province || null }, { preserveState: true, replace: true });
    };

    const openCreate = () => { setEditing(null); form.setData(emptyWarehouse); form.clearErrors(); setOpen(true); };
    const openEdit = (row) => { setEditing(row.id); form.setData({ name: row.name ?? '', phone: row.phone ?? '', pick_province: row.pick_province ?? '', pick_district: row.pick_district ?? '', pick_ward: row.pick_ward ?? '', address: row.address ?? '', manager_user_id: row.manager_user_id ?? '', vtp_code: row.vtp_code ?? '', ghtk_pick_address_id: row.ghtk_pick_address_id ?? '', code: row.code ?? '' }); form.clearErrors(); setOpen(true); };
    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/admin/warehouses/${editing}`, options); else form.post('/admin/warehouses', options);
    };

    const openVoucherEntry = (warehouseId = null) => {
        const query = warehouseId ? `?warehouse_id=${encodeURIComponent(warehouseId)}` : '';
        router.visit(`/admin/warehouse/vouchers/entry${query}`);
    };

    return (
        <AppLayout>
            <Head title="Danh sách kho" />
            <section className="ps-adminlte-page ps-warehouse-page" data-page-code="5.2.1">
                <form onSubmit={submitFilters} className="m-header-wrap">
                    <div className="m-header ps-warehouse-header">
                        <div className="ps-title">Danh sách kho</div>
                        <div className="ps-warehouse-filters">
                            <select className="form-control" value={province} onChange={(event) => setProvince(event.target.value)}><option value="">--Tỉnh/TP--</option>{provinces.map((name) => <option key={name} value={name}>{name}</option>)}</select>
                            <select className="form-control" value={manager} onChange={(event) => setManager(event.target.value)}><option value="">--Quản kho--</option>{managers.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                            <input className="form-control" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tên kho / Số điện thoại / Địa chỉ" />
                            <button className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <div className="box-body ps-toolbar">
                    <button type="button" className="btn btn-sm btn-default"><i className="fa fa-truck" /> Danh người gửi Viettel Post</button>
                    <button type="button" className="btn btn-sm btn-default"><i className="fa fa-truck" /> Danh sách kho Shippo</button>
                    <button type="button" className="btn btn-sm btn-default"><i className="fa fa-truck" /> Danh sách cửa hàng Giao hàng nhanh</button>
                    <button type="button" className="btn btn-sm btn-info" onClick={() => openVoucherEntry()}><i className="fa fa-exchange" /> Lập phiếu xuất / nhập</button>
                    <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}><i className="fa fa-plus" /> Thêm</button>
                </div>

                <div className="ps-table-scroll">
                    <table className="table table-bordered ps-source-table ps-warehouse-table">
                        <thead><tr><th>#</th><th>Tên kho</th><th>Số điện thoại</th><th>Tỉnh/TP</th><th>Quận/Huyện</th><th>Phường/Xã</th><th>Địa chỉ</th><th>Quản kho</th><th>Mã VTP</th><th>Mã GHN</th><th>Cập nhật</th><th /></tr></thead>
                        <tbody>{rows.length ? rows.map((row) => <tr key={row.id}>
                            <td className="text-center">{row.id}</td><td><strong>{row.name}</strong>{row.code && <small>({row.code})</small>}</td><td className="text-center">{row.phone}</td><td>{row.pick_province}</td><td>{row.pick_district}</td><td>{row.pick_ward}</td><td>{row.address}</td><td className="text-center">{row.manager_name}</td><td className="text-center">{row.vtp_code}</td><td className="text-center">{row.ghtk_pick_address_id}</td><td className="text-center">{row.updated_at}</td><td className="text-center ps-row-actions"><button type="button" title="Lập phiếu xuất / nhập kho" onClick={() => openVoucherEntry(row.id)}><i className="fa fa-exchange" /></button><button type="button" onClick={() => openEdit(row)}><i className="fa fa-pencil-square-o" /></button><button type="button" onClick={() => window.confirm(`Xóa kho ${row.name}?`) && router.delete(`/admin/warehouses/${row.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button></td>
                        </tr>) : <tr><td colSpan="12" className="ps-empty">Chưa có kho phù hợp.</td></tr>}</tbody>
                    </table>
                </div>
                <PushsalePagination meta={warehouses} routeUrl="/admin/warehouses" filters={currentFilters()} itemLabel="kho" />
            </section>

            <DialogShell open={open} onClose={() => setOpen(false)} title={editing ? 'CẬP NHẬT KHO' : 'THÊM MỚI KHO'}>
                <form onSubmit={save} className="ps-form-grid ps-form-grid-2">
                    <label>Tên kho (*)<input className="form-control" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                    <label>Mã kho<input className="form-control" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></label>
                    <label>Số điện thoại<input className="form-control" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                    <label>Quản kho<select className="form-control" value={form.data.manager_user_id} onChange={(event) => form.setData('manager_user_id', event.target.value)}><option value="">--Chọn quản kho--</option>{managers.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label>Tỉnh/TP<input className="form-control" value={form.data.pick_province} onChange={(event) => form.setData('pick_province', event.target.value)} /></label>
                    <label>Quận/Huyện<input className="form-control" value={form.data.pick_district} onChange={(event) => form.setData('pick_district', event.target.value)} /></label>
                    <label>Phường/Xã<input className="form-control" value={form.data.pick_ward} onChange={(event) => form.setData('pick_ward', event.target.value)} /></label>
                    <label>Địa chỉ<input className="form-control" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} /></label>
                    <label>Mã Viettel Post<input className="form-control" value={form.data.vtp_code} onChange={(event) => form.setData('vtp_code', event.target.value)} /></label>
                    <label>Mã GHN<input className="form-control" value={form.data.ghtk_pick_address_id} onChange={(event) => form.setData('ghtk_pick_address_id', event.target.value)} /></label>
                    {Object.keys(form.errors).length > 0 && <div className="alert alert-danger span-2">{Object.values(form.errors).join(' · ')}</div>}
                    <div className="span-2"><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button></div>
                </form>
            </DialogShell>
        </AppLayout>
    );
}
