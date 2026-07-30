import { crudIndexCell, crudNameCell, crudUpdatedCell, SimpleCrudPage } from '@/components/crud/SimpleCrudPage';
import { useRecordCrud } from '@/hooks/useRecordCrud';

import { RequiredMark } from './expenseShared';

const emptyForm = { name: '' };

export default function ExpenseGroups({
    schema,
    rows = [],
    pagination,
    routeUrl = '/admin/accounting/expense-groups',
}) {
    const title = schema?.title ?? 'Danh mục nhóm chi phí';
    const crud = useRecordCrud({
        routeUrl,
        emptyForm,
        deleteConfirm: (row) => `Xóa nhóm chi phí "${row.name}"?`,
    });

    return (
        <SimpleCrudPage
            title={title}
            pageCode="6.2.3"
            routeUrl={routeUrl}
            rows={rows}
            pagination={pagination}
            itemLabel="nhóm"
            crud={crud}
            dialogTitle={(isEditing) => (isEditing ? 'Cập nhật nhóm chi phí' : 'Thêm nhóm chi phí')}
            columns={[
                { key: 'index', label: 'STT', style: { width: 60 } },
                { key: 'name', label: 'Tên' },
                { key: 'updated', label: 'Cập nhật' },
            ]}
            renderCells={(row, index, page) => (
                <>
                    {crudIndexCell(index, page)}
                    {crudNameCell(row)}
                    {crudUpdatedCell(row)}
                </>
            )}
        >
            {({ form }) => (
                <label>
                    <span>Tên <RequiredMark /></span>
                    <input className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                </label>
            )}
        </SimpleCrudPage>
    );
}
