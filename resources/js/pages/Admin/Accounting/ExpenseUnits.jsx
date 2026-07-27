import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';

import { RequiredMark, currentFilters, formErrorText, formatDateTime } from './expenseShared';

const emptyForm = { name: '' };

export default function ExpenseUnits({
    schema,
    rows = [],
    pagination,
    routeUrl = '/admin/accounting/expense-units',
}) {
    const title = schema?.title ?? 'Danh mục đơn vị tính';
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);
    const errorText = formErrorText(form.errors);

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id);
        form.setData({ name: row._form?.name ?? row.name ?? '' });
        form.clearErrors();
        setOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        const request = form.transform((data) => ({ payload: data }));
        if (editingId) request.put(`${routeUrl}/records/${editingId}`, options);
        else request.post(`${routeUrl}/records`, options);
    };

    const destroy = (row) => {
        if (!row._record_id || !window.confirm(`Xóa đơn vị tính "${row.name}"?`)) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={title} />
            <PushsalePageShell
                title={title}
                pageCode="6.2.4"
                className="ps-expense-page"
                headerClassName="ps-expense-header"
                collapsible={false}
                toolbar={(
                    <div className="ps-expense-toolbar">
                        <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                    </div>
                )}
            >
                <div className="ps-expense-body">
                    <div className="ps-expense-table-scroll">
                        <table className="table table-bordered ps-expense-table" style={{ minWidth: 520 }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 60 }}>STT</th>
                                    <th>Tên</th>
                                    <th>Cập nhật</th>
                                    <th className="ps-action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? index}>
                                        <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                        <td>{row.name}</td>
                                        <td className="text-center">{formatDateTime(row.updated_at)}</td>
                                        <td className="ps-row-actions-cell">
                                            <button type="button" title="Cập nhật" onClick={() => openEdit(row)}><i className="fa fa-pencil-square-o" /></button>
                                            <button type="button" title="Xóa" onClick={() => destroy(row)}><i className="fa fa-trash" /></button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="4" className="text-center">Không có dữ liệu.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ps-expense-footer">
                        <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="đơn vị" />
                    </div>
                </div>
            </PushsalePageShell>

            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && setOpen(false)}
                title={editingId ? 'Cập nhật đơn vị tính' : 'Thêm đơn vị tính'}
                width="520px"
                className="ps-expense-dialog"
            >
                <form className="ps-expense-form" onSubmit={save}>
                    <label>
                        <span>Tên <RequiredMark /></span>
                        <input className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </label>
                    {errorText ? <div className="alert alert-danger">{errorText}</div> : null}
                    <div className="ps-dialog-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={() => setOpen(false)}>Đóng</button>
                        <button type="submit" className="btn btn-primary btn-sm" disabled={form.processing}>
                            <i className="fa fa-save" /> {editingId ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
