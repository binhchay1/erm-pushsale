import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';

import { RequiredMark, currentFilters, formErrorText, formatDateTime } from './expenseShared';

const emptyForm = { expense_group_id: '', name: '' };

export default function ExpenseCategories({
    schema,
    rows = [],
    pagination,
    filterOptions = {},
    routeUrl = '/admin/accounting/expense-categories',
}) {
    const title = schema?.title ?? 'Danh mục chi phí';
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);
    const groups = useMemo(
        () => (filterOptions.expenseGroups ?? []).map((item) => ({ id: String(item.id), label: item.label ?? item.name })),
        [filterOptions.expenseGroups],
    );
    const errorText = formErrorText(form.errors);

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id);
        form.setData({
            ...emptyForm,
            ...(row._form ?? {}),
            expense_group_id: row._form?.expense_group_id ?? '',
            name: row._form?.name ?? row.name ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        const request = form.transform((data) => ({
            payload: {
                ...data,
                expense_group_id: data.expense_group_id || null,
            },
        }));
        if (editingId) request.put(`${routeUrl}/records/${editingId}`, options);
        else request.post(`${routeUrl}/records`, options);
    };

    const destroy = (row) => {
        if (!row._record_id || !window.confirm(`Xóa danh mục "${row.name}"?`)) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={title} />
            <PushsalePageShell
                title={title}
                pageCode="6.2.2"
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
                        <table className="table table-bordered ps-expense-table" style={{ minWidth: 640 }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 60 }}>STT</th>
                                    <th>Nhóm chi phí</th>
                                    <th>Tên</th>
                                    <th>Cập nhật</th>
                                    <th className="ps-action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? index}>
                                        <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                        <td>{row.group || '—'}</td>
                                        <td>{row.name}</td>
                                        <td className="text-center">{formatDateTime(row.updated_at)}</td>
                                        <td className="ps-row-actions-cell">
                                            <button type="button" title="Cập nhật" onClick={() => openEdit(row)}><i className="fa fa-pencil-square-o" /></button>
                                            <button type="button" title="Xóa" onClick={() => destroy(row)}><i className="fa fa-trash" /></button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="5" className="text-center">Không có dữ liệu.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ps-expense-footer">
                        <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="danh mục" />
                    </div>
                </div>
            </PushsalePageShell>

            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && setOpen(false)}
                title={editingId ? 'Cập nhật danh mục chi phí' : 'Thêm danh mục chi phí'}
                width="560px"
                className="ps-expense-dialog"
            >
                <form className="ps-expense-form" onSubmit={save}>
                    <label>
                        <span>Nhóm chi phí</span>
                        <select className="form-control" value={String(form.data.expense_group_id ?? '')} onChange={(event) => form.setData('expense_group_id', event.target.value)}>
                            <option value="">-- Chọn nhóm --</option>
                            {groups.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
                        </select>
                    </label>
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
