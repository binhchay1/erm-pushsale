import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

const emptyForm = { phone: '', reason: '', order_id: '', creation_type: 'manual' };
const currentFilters = () => Object.fromEntries(new URLSearchParams(window.location.search).entries());

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
}

export default function Page1131({ schema, rows = [], pagination, routeUrl = '/admin/security/phone-blacklist', filterOptions = {} }) {
    const params = new URLSearchParams(window.location.search);
    const [keyword, setKeyword] = useState(params.get('search') ?? '');
    const [editorOpen, setEditorOpen] = useState(false);
    const [guideOpen, setGuideOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const search = (event) => {
        event.preventDefault();
        router.get(routeUrl, keyword.trim() ? { search: keyword.trim() } : {}, { replace: true, preserveState: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setEditorOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id);
        form.setData({ ...emptyForm, ...(row._form ?? {}), phone: row._form?.phone ?? row.phone ?? '', reason: row._form?.reason ?? row.reason ?? '' });
        form.clearErrors();
        setEditorOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditorOpen(false) };
        if (editingId) form.transform((data) => ({ payload: data })).put(`${routeUrl}/records/${editingId}`, options);
        else form.transform((data) => ({ payload: data })).post(`${routeUrl}/records`, options);
    };

    const destroy = (row) => {
        if (!row._record_id || !window.confirm(`Xóa số blacklist ${row.phone}?`)) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Quản lý số blacklist'} />
            <section className="ps-adminlte-page ps-blacklist-page" data-page-code="1.13.1">
                <form className="m-header-wrap" onSubmit={search}>
                    <div className="m-header ps-blacklist-header">
                        <div className="col-sm-8 form-group ps-blacklist-title">
                            <span className="text form-group">Quản lý số blacklist</span>
                            <button type="button" className="ps-guide-link" onClick={() => setGuideOpen(true)}>(xem hướng dẫn)</button>
                        </div>
                        <div className="col-sm-4 form-group ps-blacklist-search">
                            <input className="form-control" value={keyword} onChange={(event) => setKeyword(event.target.value)} />
                            <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <div className="box-body ps-blacklist-toolbar">
                    <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}><i className="fa fa-plus" /> <span>Thêm số blacklist</span></button>
                </div>

                <div className="box-body ps-blacklist-body">
                    <div className="ps-table-scroll">
                        <table className="table table-bordered table-multi-select ps-blacklist-table">
                            <thead><tr>
                                <th className="text-center" style={{ width: 30 }}>#</th>
                                <th className="text-center no-wrap">Số blacklist</th>
                                <th className="text-center no-wrap">Lý do</th>
                                <th className="text-center no-wrap">Đơn hàng</th>
                                <th className="text-center no-wrap">Kiểu tạo</th>
                                <th className="text-center no-wrap" style={{ width: 150 }}>Người tạo</th>
                                <th className="text-center no-wrap" style={{ width: 150 }}>Cập nhật</th>
                                <th className="text-center ps-action-col" style={{ width: 90 }} />
                            </tr></thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => <tr key={row._record_id ?? row.id}>
                                    <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                    <td className="text-center"><strong>{row.phone}</strong></td>
                                    <td>{row.reason}</td>
                                    <td className="text-center">{row.order_code}</td>
                                    <td className="text-center">{row.creation_type}</td>
                                    <td className="text-center">{row.creator}</td>
                                    <td className="text-center">{formatDate(row.updated_at)}</td>
                                    <td className="text-center ps-row-actions ps-row-actions-cell"><button type="button" title="Cập nhật" onClick={() => openEdit(row)}><i className="fa fa-pencil-square-o" /></button><button type="button" title="Xóa" onClick={() => destroy(row)}><i className="fa fa-trash" /></button></td>
                                </tr>) : <tr><td colSpan="8" className="text-center">Không có dữ liệu.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <div className="row ps-blacklist-note-row">
                        <div className="col-sm-6 text-left">* Khi Sale chốt đơn nếu số điện thoại của khách nằm trong blacklist, Sale sẽ nhận được cảnh báo</div>
                        <div className="col-sm-6 text-right"><PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="số" /></div>
                    </div>
                </div>
            </section>

            <PushsaleDialog open={editorOpen} onOpenChange={(open) => !open && setEditorOpen(false)} title={editingId ? 'Cập nhật số blacklist' : 'Thêm số blacklist'} width="600px" className="ps-blacklist-dialog">
                <form onSubmit={save} className="ps-blacklist-form">
                    <label><span>Số blacklist <b>(*)</b></span><input className="form-control" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} required /></label>
                    <label><span>Lý do</span><textarea className="form-control" rows="3" value={form.data.reason ?? ''} onChange={(event) => form.setData('reason', event.target.value)} /></label>
                    <label><span>Đơn hàng</span><select className="form-control" value={form.data.order_id ?? ''} onChange={(event) => form.setData('order_id', event.target.value)}><option value="">--Chọn đơn--</option>{(filterOptions.orders ?? []).map((order) => <option key={order.id} value={order.id}>{order.label}</option>)}</select></label>
                    <label><span>Kiểu tạo</span><select className="form-control" value={form.data.creation_type} onChange={(event) => form.setData('creation_type', event.target.value)}><option value="manual">Thủ công</option><option value="warehouse">Kho cảnh báo</option><option value="automatic">Tự động</option></select></label>
                    {Object.keys(form.errors).length ? <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div> : null}
                    <div className="ps-dialog-footer"><button type="button" className="btn btn-default btn-sm" onClick={() => setEditorOpen(false)}>Đóng</button><button className="btn btn-primary btn-sm" disabled={form.processing}><i className="fa fa-save" /> Cập nhật</button></div>
                </form>
            </PushsaleDialog>

            <PushsaleDialog open={guideOpen} onOpenChange={(open) => !open && setGuideOpen(false)} title="Hướng dẫn quản lý số blacklist" width="760px" className="ps-blacklist-guide-dialog">
                <div className="ps-guide-note">
                    <p><b>Mục đích:</b> lưu các số điện thoại rủi ro, bom hàng, hoàn nhiều lần hoặc khách không nên tiếp tục chốt đơn.</p>
                    <ul>
                        <li>Khi Sale chốt đơn, hệ thống kiểm tra số điện thoại đã chuẩn hóa với bảng blacklist.</li>
                        <li>Nếu trùng, Sale nhận cảnh báo và phải kiểm tra lý do trước khi tiếp tục.</li>
                        <li>Kho có thể đưa số vào blacklist từ luồng xử lý đơn; bản ghi vẫn quay về cùng bảng này để quản trị kiểm soát.</li>
                        <li>Không tự xóa lead/order cũ; blacklist chỉ là lớp cảnh báo nghiệp vụ khi tác nghiệp và chốt đơn.</li>
                    </ul>
                </div>
            </PushsaleDialog>
        </AppLayout>
    );
}
