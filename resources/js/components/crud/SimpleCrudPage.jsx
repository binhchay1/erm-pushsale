import { Head } from '@inertiajs/react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { currentFilters, formatDateTime } from '@/pages/Admin/Accounting/expenseShared';

/**
 * Thin CRUD page shell for name-centric catalogs (DRY #12).
 * Keep page-specific columns/form fields via render props.
 */
export function SimpleCrudPage({
    title,
    pageCode,
    routeUrl,
    rows = [],
    pagination,
    itemLabel = 'bản ghi',
    className = 'ps-expense-page',
    headerClassName = 'ps-expense-header',
    tableMinWidth = 520,
    columns,
    renderCells,
    crud,
    dialogTitle,
    dialogWidth = '520px',
    children,
    toolbarExtra = null,
}) {
    const { open, setOpen, openCreate, openEdit, destroy, save, form, errorText, isEditing } = crud;

    return (
        <AppLayout>
            <Head title={title} />
            <PushsalePageShell
                title={title}
                pageCode={pageCode}
                className={className}
                headerClassName={headerClassName}
                collapsible={false}
                toolbar={(
                    <div className="ps-expense-toolbar">
                        <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                        {toolbarExtra}
                    </div>
                )}
            >
                <div className="ps-expense-body">
                    <div className="ps-expense-table-scroll">
                        <table className="table table-bordered ps-expense-table" style={{ minWidth: tableMinWidth }}>
                            <thead>
                                <tr>
                                    {columns.map((column) => (
                                        <th key={column.key} className={column.className} style={column.style}>
                                            {column.label}
                                        </th>
                                    ))}
                                    <th className="ps-action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? index}>
                                        {renderCells(row, index, pagination)}
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
                                    <TableEmptyRow colSpan={columns.length + 1} message="Không có dữ liệu." />
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="ps-expense-footer">
                        <PushsalePagination
                            meta={pagination}
                            routeUrl={routeUrl}
                            filters={currentFilters()}
                            itemLabel={itemLabel}
                        />
                    </div>
                </div>
            </PushsalePageShell>

            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && setOpen(false)}
                title={typeof dialogTitle === 'function' ? dialogTitle(isEditing) : dialogTitle}
                width={dialogWidth}
                className="ps-expense-dialog"
            >
                <form className="ps-expense-form" onSubmit={save}>
                    {children({ form, errorText, isEditing })}
                    {errorText ? <div className="alert alert-danger">{errorText}</div> : null}
                    <div className="ps-dialog-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={() => setOpen(false)}>Đóng</button>
                        <button type="submit" className="btn btn-primary btn-sm" disabled={form.processing}>
                            <i className="fa fa-save" /> {isEditing ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}

export function crudIndexCell(index, pagination) {
    return <td className="text-center">{index + (pagination?.from ?? 1)}</td>;
}

export function crudUpdatedCell(row) {
    return <td className="text-center">{formatDateTime(row.updated_at)}</td>;
}

export function crudNameCell(row) {
    return <td>{row.name}</td>;
}
