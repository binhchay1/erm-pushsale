import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency } from '@/lib/format';
import { useConfirm } from '@/hooks/use-confirm';

import {
    RequiredMark,
    currentFilters,
    formErrorText,
    formatDateTime,
    monthOptions,
    valueFromSearch,
    yearOptions,
} from './expenseShared';

function emptyExpenseForm() {
    const now = new Date();
    return {
        name: '',
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        expense_group_id: '',
        expense_category_id: '',
        expense_unit_id: '',
        unit_price: '',
        quantity: '',
        invoice_number: '',
        note: '',
    };
}

function optionList(items = []) {
    return items.map((item) => ({
        id: String(item.id ?? item.value ?? ''),
        label: item.label ?? item.name ?? String(item.id ?? ''),
    })).filter((item) => item.id !== '');
}

export default function UnitExpenses({
    schema,
    rows = [],
    pagination,
    filterOptions = {},
    routeUrl = '/admin/accounting/expenses',
}) {
    const { ask } = useConfirm();
    const title = schema?.title ?? 'Quản lý chi phí đơn vị';
    const [keyword, setKeyword] = useState(valueFromSearch('search'));
    const [filters, setFilters] = useState(() => ({
        month: valueFromSearch('month', ''),
        year: valueFromSearch('year', ''),
        expense_group_id: valueFromSearch('expense_group_id', ''),
        expense_category_id: valueFromSearch('expense_category_id', ''),
    }));
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyExpenseForm());

    const groups = useMemo(() => optionList(filterOptions.expenseGroups), [filterOptions.expenseGroups]);
    const allCategories = useMemo(() => (filterOptions.expenseCategories ?? []).map((item) => ({
        id: String(item.id ?? ''),
        label: item.label ?? item.name ?? String(item.id ?? ''),
        expense_group_id: item.expense_group_id == null ? '' : String(item.expense_group_id),
    })).filter((item) => item.id !== ''), [filterOptions.expenseCategories]);
    const units = useMemo(() => optionList(filterOptions.expenseUnits), [filterOptions.expenseUnits]);
    const years = useMemo(() => yearOptions(), []);
    const months = useMemo(() => monthOptions(), []);
    const errorText = formErrorText(form.errors);

    const filterCategories = useMemo(() => {
        if (!filters.expense_group_id) return allCategories;
        return allCategories.filter((item) => item.expense_group_id === String(filters.expense_group_id));
    }, [allCategories, filters.expense_group_id]);

    const formCategories = useMemo(() => {
        if (!form.data.expense_group_id) return allCategories;
        return allCategories.filter((item) => item.expense_group_id === String(form.data.expense_group_id));
    }, [allCategories, form.data.expense_group_id]);

    const applyFilters = (event) => {
        event?.preventDefault?.();
        const next = {
            ...Object.fromEntries(
                Object.entries({
                    search: keyword.trim(),
                    month: filters.month,
                    year: filters.year,
                    expense_group_id: filters.expense_group_id,
                    expense_category_id: filters.expense_category_id,
                }).filter(([, value]) => value !== '' && value !== '-1'),
            ),
        };
        router.get(routeUrl, next, { replace: true, preserveState: true });
    };

    const exportExcel = () => {
        const params = new URLSearchParams({ ...currentFilters(), export: '1' });
        window.location.href = `${routeUrl}?${params.toString()}`;
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyExpenseForm());
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row._record_id);
        form.setData({
            ...emptyExpenseForm(),
            ...(row._form ?? {}),
            year: Number(row._form?.year ?? row.year ?? new Date().getFullYear()),
            month: Number(row._form?.month ?? row.month ?? new Date().getMonth() + 1),
            expense_group_id: row._form?.expense_group_id ?? '',
            expense_category_id: row._form?.expense_category_id ?? '',
            expense_unit_id: row._form?.expense_unit_id ?? '',
            unit_price: row._form?.unit_price ?? row.unit_price ?? '',
            quantity: row._form?.quantity ?? row.quantity ?? '',
            invoice_number: row._form?.invoice_number ?? row.invoice ?? '',
            note: row._form?.note ?? row.note ?? '',
            name: row._form?.name ?? row.name ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        const request = form.transform((data) => ({
            payload: {
                ...data,
                year: Number(data.year) || null,
                month: Number(data.month) || null,
                expense_group_id: data.expense_group_id || null,
                expense_category_id: data.expense_category_id || null,
                expense_unit_id: data.expense_unit_id || null,
                unit_price: data.unit_price === '' || data.unit_price == null ? null : Number(data.unit_price),
                quantity: data.quantity === '' || data.quantity == null ? null : Number(data.quantity),
            },
        }));

        if (editingId) request.put(`${routeUrl}/records/${editingId}`, options);
        else request.post(`${routeUrl}/records`, options);
    };

    const destroy = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({ description: `Xóa chi phí "${row.name}"?`, confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={title} />
            <PushsalePageShell
                title={title}
                pageCode="6.2.1"
                className="ps-expense-page"
                headerClassName="ps-expense-header"
                collapsible={false}
                defaultFiltersCollapsed={false}
                advancedFilters={(
                    <div className="ps-expense-filters">
                        <select
                            className="form-control"
                            value={filters.month}
                            onChange={(event) => setFilters((current) => ({ ...current, month: event.target.value }))}
                        >
                            <option value="">-- Tháng --</option>
                            {months.map((item) => (
                                <option key={item.id} value={item.id}>{item.label}</option>
                            ))}
                        </select>
                        <select
                            className="form-control"
                            value={filters.year}
                            onChange={(event) => setFilters((current) => ({ ...current, year: event.target.value }))}
                        >
                            <option value="">-- Năm --</option>
                            {years.map((item) => (
                                <option key={item.id} value={item.id}>{item.label}</option>
                            ))}
                        </select>
                        <select
                            className="form-control"
                            value={filters.expense_group_id}
                            onChange={(event) => setFilters((current) => ({ ...current, expense_group_id: event.target.value, expense_category_id: '' }))}
                        >
                            <option value="">-- Danh mục nhóm chi phí --</option>
                            {groups.map((item) => (
                                <option key={item.id} value={item.id}>{item.label}</option>
                            ))}
                        </select>
                        <select
                            className="form-control"
                            value={filters.expense_category_id}
                            onChange={(event) => setFilters((current) => ({ ...current, expense_category_id: event.target.value }))}
                        >
                            <option value="">-- Danh mục chi phí --</option>
                            {filterCategories.map((item) => (
                                <option key={item.id} value={item.id}>{item.label}</option>
                            ))}
                        </select>
                    </div>
                )}
                actions={(
                    <form className="ps-expense-search" onSubmit={applyFilters}>
                        <input
                            className="form-control"
                            placeholder="Số hóa đơn"
                            value={keyword}
                            onChange={(event) => setKeyword(event.target.value)}
                        />
                        <PushsaleSearchButton type="submit" label="Tìm kiếm" />
                        <button type="button" className="btn btn-sm btn-default" onClick={exportExcel}>
                            <i className="fa fa-file-excel-o" /> Xuất Excel
                        </button>
                    </form>
                )}
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
                        <table className="table table-bordered ps-expense-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>STT</th>
                                    <th>Tên</th>
                                    <th>Năm</th>
                                    <th>Tháng</th>
                                    <th>Danh mục nhóm chi phí</th>
                                    <th>Danh mục chi phí</th>
                                    <th>Đơn vị tính</th>
                                    <th>Đơn giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                    <th>Hóa đơn</th>
                                    <th>Ghi chú</th>
                                    <th>Cập nhật</th>
                                    <th className="ps-action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? `${row.name}-${index}`}>
                                        <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                        <td>{row.name}</td>
                                        <td className="text-center">{row.year}</td>
                                        <td className="text-center">{row.month}</td>
                                        <td>{row.group || '—'}</td>
                                        <td>{row.category || '—'}</td>
                                        <td className="text-center">{row.unit || '—'}</td>
                                        <td className="ps-money-cell">{formatCurrency(row.unit_price)}</td>
                                        <td className="text-center">{row.quantity ?? '—'}</td>
                                        <td className="ps-money-cell">{formatCurrency(row.total)}</td>
                                        <td>{row.invoice || '—'}</td>
                                        <td>{row.note || '—'}</td>
                                        <td className="text-center">{formatDateTime(row.updated_at)}</td>
                                        <td className="ps-row-actions-cell">
                                            <button type="button" title="Cập nhật" onClick={() => openEdit(row)}>
                                                <i className="fa fa-pencil-square-o" />
                                            </button>
                                            <button type="button" title="Xóa" onClick={() => destroy(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="14" className="text-center">Không có dữ liệu.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ps-expense-footer">
                        <PushsalePagination
                            meta={pagination}
                            routeUrl={routeUrl}
                            filters={currentFilters()}
                            itemLabel="chi phí"
                        />
                    </div>
                </div>
            </PushsalePageShell>

            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && setOpen(false)}
                title={editingId ? 'Cập nhật chi phí đơn vị' : 'Thêm chi phí đơn vị'}
                width="760px"
                className="ps-expense-dialog"
            >
                <form className="ps-expense-form" onSubmit={save}>
                    <div className="ps-expense-form-grid">
                        <label>
                            <span>Tên <RequiredMark /></span>
                            <input className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                        </label>
                        <label>
                            <span>Hóa đơn</span>
                            <input className="form-control" value={form.data.invoice_number ?? ''} onChange={(event) => form.setData('invoice_number', event.target.value)} />
                        </label>
                        <label>
                            <span>Năm <RequiredMark /></span>
                            <select className="form-control" value={String(form.data.year ?? '')} onChange={(event) => form.setData('year', Number(event.target.value))} required>
                                {years.map((item) => (
                                    <option key={item.id} value={item.id}>{item.label}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>Tháng <RequiredMark /></span>
                            <select className="form-control" value={String(form.data.month ?? '')} onChange={(event) => form.setData('month', Number(event.target.value))} required>
                                {months.map((item) => (
                                    <option key={item.id} value={item.id}>{item.label}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>Danh mục nhóm chi phí</span>
                            <select
                                className="form-control"
                                value={String(form.data.expense_group_id ?? '')}
                                onChange={(event) => {
                                    form.setData({
                                        ...form.data,
                                        expense_group_id: event.target.value,
                                        expense_category_id: '',
                                    });
                                }}
                            >
                                <option value="">-- Chọn nhóm --</option>
                                {groups.map((item) => (
                                    <option key={item.id} value={item.id}>{item.label}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>Danh mục chi phí</span>
                            <select className="form-control" value={String(form.data.expense_category_id ?? '')} onChange={(event) => form.setData('expense_category_id', event.target.value)}>
                                <option value="">-- Chọn danh mục --</option>
                                {formCategories.map((item) => (
                                    <option key={item.id} value={item.id}>{item.label}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>Đơn vị tính</span>
                            <select className="form-control" value={String(form.data.expense_unit_id ?? '')} onChange={(event) => form.setData('expense_unit_id', event.target.value)}>
                                <option value="">-- Chọn đơn vị --</option>
                                {units.map((item) => (
                                    <option key={item.id} value={item.id}>{item.label}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>Đơn giá</span>
                            <input className="form-control" type="number" min="0" value={form.data.unit_price ?? ''} onChange={(event) => form.setData('unit_price', event.target.value)} />
                        </label>
                        <label>
                            <span>Số lượng</span>
                            <input className="form-control" type="number" min="0" step="0.01" value={form.data.quantity ?? ''} onChange={(event) => form.setData('quantity', event.target.value)} />
                        </label>
                        <label className="span-2">
                            <span>Ghi chú</span>
                            <textarea className="form-control" rows="3" value={form.data.note ?? ''} onChange={(event) => form.setData('note', event.target.value)} />
                        </label>
                    </div>
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
