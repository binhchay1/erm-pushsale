import { useMemo } from 'react';

import { crudIndexCell, crudNameCell, crudUpdatedCell, SimpleCrudPage } from '@/components/crud/SimpleCrudPage';
import { useRecordCrud } from '@/hooks/useRecordCrud';

import { RequiredMark } from './expenseShared';

const emptyForm = { expense_group_id: '', name: '' };

export default function ExpenseCategories({
    schema,
    rows = [],
    pagination,
    filterOptions = {},
    routeUrl = '/admin/accounting/expense-categories',
}) {
    const title = schema?.title ?? 'Danh mục chi phí';
    const groups = useMemo(
        () => (filterOptions.expenseGroups ?? []).map((item) => ({ id: String(item.id), label: item.label ?? item.name })),
        [filterOptions.expenseGroups],
    );
    const crud = useRecordCrud({
        routeUrl,
        emptyForm,
        transformPayload: (data) => ({ ...data, expense_group_id: data.expense_group_id || null }),
        deleteConfirm: (row) => `Xóa danh mục "${row.name}"?`,
    });

    return (
        <SimpleCrudPage
            title={title}
            pageCode="6.2.2"
            routeUrl={routeUrl}
            rows={rows}
            pagination={pagination}
            itemLabel="danh mục"
            tableMinWidth={640}
            dialogWidth="560px"
            crud={crud}
            dialogTitle={(isEditing) => (isEditing ? 'Cập nhật danh mục chi phí' : 'Thêm danh mục chi phí')}
            columns={[
                { key: 'index', label: 'STT', style: { width: 60 } },
                { key: 'group', label: 'Nhóm chi phí' },
                { key: 'name', label: 'Tên' },
                { key: 'updated', label: 'Cập nhật' },
            ]}
            renderCells={(row, index, page) => (
                <>
                    {crudIndexCell(index, page)}
                    <td>{row.group || '—'}</td>
                    {crudNameCell(row)}
                    {crudUpdatedCell(row)}
                </>
            )}
        >
            {({ form }) => (
                <>
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
                </>
            )}
        </SimpleCrudPage>
    );
}
