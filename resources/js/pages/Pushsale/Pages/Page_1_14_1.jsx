import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

const emptyForm = {
    account: '', password: '', invoice_type_code: '', tax_code: '', invoice_template_code: '', invoice_series: '',
    business_name: '', address: '', phone: '', fax: '', email: '', bank_name: '', bank_account: '', is_active: true,
};
const currentFilters = () => Object.fromEntries(new URLSearchParams(window.location.search).entries());
const fmt = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
};

export default function Page1141({ schema, rows = [], pagination, routeUrl = '/admin/unit/electronic-invoice-configs' }) {
    const params = new URLSearchParams(window.location.search);
    const [keyword, setKeyword] = useState(params.get('search') ?? '');
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const search = (event) => {
        event.preventDefault();
        router.get(routeUrl, keyword.trim() ? { search: keyword.trim() } : {}, { replace: true, preserveState: true });
    };
    const create = () => { setEditingId(null); form.setData(emptyForm); form.clearErrors(); setOpen(true); };
    const edit = (row) => { setEditingId(row._record_id); form.setData({ ...emptyForm, ...(row._form ?? {}) }); form.clearErrors(); setOpen(true); };
    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editingId) form.transform((data) => ({ payload: data })).put(`${routeUrl}/records/${editingId}`, options);
        else form.transform((data) => ({ payload: data })).post(`${routeUrl}/records`, options);
    };
    const destroy = (row) => {
        if (!row._record_id || !window.confirm(`Xóa cấu hình hóa đơn ${row.account}?`)) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Quản lý cấu hình hóa đơn'} />
            <section className="ps-adminlte-page ps-invoice-config-page" data-page-code="1.14.1">
                <form className="m-header-wrap" onSubmit={search}>
                    <div className="m-header ps-invoice-config-header">
                        <div className="col-sm-6 form-group ps-title">Quản lý cấu hình hóa đơn</div>
                        <div className="col-sm-3 form-group" />
                        <div className="col-sm-3 form-group ps-invoice-config-search">
                            <input className="form-control text-center" placeholder="Tên nhóm" value={keyword} onChange={(event) => setKeyword(event.target.value)} />
                            <button className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <div className="box-body ps-invoice-config-body">
                    <div className="ps-table-scroll">
                        <table className="table table-bordered ps-invoice-config-table">
                            <thead><tr>
                                <th className="text-center" style={{ width: 60 }}>STT</th>
                                <th className="text-center no-wrap">Tài khoản</th>
                                <th className="text-center no-wrap">Mã số thuế</th>
                                <th className="text-center no-wrap">Ký hiệu mẫu hóa đơn</th>
                                <th className="text-center no-wrap">Dãy ký hiệu hóa đơn</th>
                                <th className="text-center no-wrap">Tên đăng ký kinh doanh</th>
                                <th className="text-center no-wrap">Số điện thoại</th>
                                <th className="text-center no-wrap">Email</th>
                                <th className="text-center">Sử dụng</th>
                                <th className="text-center no-wrap">Cập nhật</th>
                                <th className="text-center no-wrap ps-action-col"><button type="button" className="btn-icon" onClick={create}><i className="fa fa-plus" /> <span>Thêm</span></button></th>
                            </tr></thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => <tr key={row._record_id ?? row.id}>
                                    <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                    <td>{row.account}</td>
                                    <td>{row.tax_code}</td>
                                    <td>{row.invoice_template_code}</td>
                                    <td>{row.invoice_series}</td>
                                    <td>{row.business_name}</td>
                                    <td>{row.phone}</td>
                                    <td>{row.email}</td>
                                    <td className="text-center">{row.is_active ? <i className="fa fa-check text-green" /> : ''}</td>
                                    <td className="text-center">{fmt(row.updated_at)}</td>
                                    <td className="text-center ps-row-actions ps-row-actions-cell"><button type="button" title="Cập nhật" onClick={() => edit(row)}><i className="fa fa-pencil-square-o" /></button><button type="button" title="Xóa" onClick={() => destroy(row)}><i className="fa fa-trash" /></button></td>
                                </tr>) : <tr><td colSpan="11" className="text-center">Không có dữ liệu.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="text-right"><PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="cấu hình" /></div>
                </div>
            </section>

            <PushsaleDialog open={open} onOpenChange={(next) => !next && setOpen(false)} title="Cập nhật thông tin cấu hình hóa đơn" width="80vw" className="ps-invoice-config-dialog">
                <form className="ps-invoice-config-form" onSubmit={save}>
                    <div className="ps-invoice-config-grid">
                        <label><span>Tài khoản <b>(*)</b></span><input className="form-control" value={form.data.account} onChange={(e) => form.setData('account', e.target.value)} required /></label>
                        <label><span>Điện thoại <b>(*)</b></span><input className="form-control" value={form.data.phone ?? ''} onChange={(e) => form.setData('phone', e.target.value)} /></label>
                        <label><span>Mật khẩu <b>(*)</b></span><input className="form-control" type="password" value={form.data.password ?? ''} onChange={(e) => form.setData('password', e.target.value)} /></label>
                        <label><span>Số fax</span><input className="form-control" value={form.data.fax ?? ''} onChange={(e) => form.setData('fax', e.target.value)} /></label>
                        <label><span>Mã loại hóa đơn <b>(*)</b></span><input className="form-control" value={form.data.invoice_type_code ?? ''} onChange={(e) => form.setData('invoice_type_code', e.target.value)} /></label>
                        <label><span>Email <b>(*)</b></span><input className="form-control" type="email" value={form.data.email ?? ''} onChange={(e) => form.setData('email', e.target.value)} /></label>
                        <label><span>Mã số thuế <b>(*)</b></span><input className="form-control" value={form.data.tax_code ?? ''} onChange={(e) => form.setData('tax_code', e.target.value)} required /></label>
                        <label><span>Tên ngân hàng <b>(*)</b></span><input className="form-control" value={form.data.bank_name ?? ''} onChange={(e) => form.setData('bank_name', e.target.value)} /></label>
                        <label><span>Mã mẫu hóa đơn <b>(*)</b></span><input className="form-control" value={form.data.invoice_template_code ?? ''} onChange={(e) => form.setData('invoice_template_code', e.target.value)} /></label>
                        <label><span>Tài khoản ngân hàng</span><input className="form-control" value={form.data.bank_account ?? ''} onChange={(e) => form.setData('bank_account', e.target.value)} /></label>
                        <label><span>Ký hiệu hóa đơn <b>(*)</b></span><input className="form-control" value={form.data.invoice_series ?? ''} onChange={(e) => form.setData('invoice_series', e.target.value)} /></label>
                        <label><span>Sử dụng</span><span className="ps-checkbox-inline"><input type="checkbox" checked={Boolean(form.data.is_active)} onChange={(e) => form.setData('is_active', e.target.checked)} /> Đang sử dụng</span></label>
                        <label><span>Tên doanh nghiệp <b>(*)</b></span><input className="form-control" value={form.data.business_name ?? ''} onChange={(e) => form.setData('business_name', e.target.value)} /></label>
                        <label className="span-right" />
                        <label><span>Địa chỉ <b>(*)</b></span><input className="form-control" value={form.data.address ?? ''} onChange={(e) => form.setData('address', e.target.value)} /></label>
                    </div>
                    {Object.keys(form.errors).length ? <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div> : null}
                    <div className="ps-dialog-footer ps-invoice-config-footer"><button className="btn btn-primary btn-sm" disabled={form.processing}><i className="fa fa-plus" /> {editingId ? 'Cập nhật' : 'Thêm mới'}</button></div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
