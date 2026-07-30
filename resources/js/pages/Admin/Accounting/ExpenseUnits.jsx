import { crudIndexCell, crudNameCell, crudUpdatedCell, SimpleCrudPage } from '@/components/crud/SimpleCrudPage';
import { useRecordCrud } from '@/hooks/useRecordCrud';

import { RequiredMark } from './expenseShared';

const emptyForm = { name: '' };

export default function ExpenseUnits({
    schema,
    rows = [],
    pagination,
    routeUrl = '/admin/accounting/expense-units',
}) {
    const title = schema?.title ?? 'Danh mục đơn vị tính';
    const crud = useRecordCrud({
        routeUrl,
        emptyForm,
        deleteConfirm: (row) => `Xóa đơn vị tính "${row.name}"?`,
    });

    return (
        <SimpleCrudPage
            title={title}
            pageCode="6.2.4"
            routeUrl={routeUrl}
            rows={rows}
            pagination={pagination}
            itemLabel="đơn vị"
            crud={crud}
            dialogTitle={(isEditing) => (isEditing ? 'Cập nhật đơn vị tính' : 'Thêm đơn vị tính')}
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
