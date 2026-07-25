import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePageFrame } from '@/pages/Pushsale/components/PushsalePageFrame';

const numberFormatter = new Intl.NumberFormat('vi-VN');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function requestJson(url, method, payload = null) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const errors = Object.values(body.errors ?? {}).flat().join(' ');
        throw new Error(errors || body.message || 'Không thể lưu cấu hình tác nghiệp.');
    }

    return response.json().catch(() => ({}));
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function createDraftCategory() {
    return {
        id: `new-${Date.now()}`,
        _isNew: true,
        _record_id: null,
        name: '',
        sort_order: 0,
        is_start: false,
        is_pool: false,
        pool: false,
        duration_minutes: 0,
        is_active: true,
        updated_by: '',
        updated_at: null,
    };
}

function normalizeCategory(row) {
    const form = row?._form ?? row ?? {};
    return {
        ...row,
        name: form.name ?? row.name ?? '',
        sort_order: Number(form.sort_order ?? row.sort_order ?? 0),
        is_start: Boolean(form.is_start ?? row.is_start),
        is_pool: Boolean(form.is_pool ?? row.is_pool ?? row.pool),
        pool: Boolean(form.is_pool ?? row.is_pool ?? row.pool),
        duration_minutes: Number(form.duration_minutes ?? row.duration_minutes ?? 0),
        is_active: Boolean(form.is_active ?? row.is_active ?? true),
        updated_by: row.updated_by ?? row.updater ?? '',
    };
}

function normalizeWorkflow(row) {
    const form = row?._form ?? row ?? {};
    return {
        id: row.id ?? row._record_id,
        _record_id: row._record_id ?? row.id,
        from_operation_category_id: form.from_operation_category_id ?? row.from_operation_category_id ?? '',
        operation_result: form.operation_result ?? row.operation_result ?? row.result_value ?? '',
        condition_type: form.condition_type ?? row.condition_type ?? '',
        to_operation_category_id: form.to_operation_category_id ?? row.to_operation_category_id ?? '',
        delay_minutes: Number(form.delay_minutes ?? row.delay_minutes ?? 0),
        is_active: Boolean(form.is_active ?? row.is_active ?? true),
        updated_at: row.updated_at,
        updated_by: row.updated_by ?? '',
    };
}

function createDraftWorkflow(categories = [], results = []) {
    return {
        id: `new-workflow-${Date.now()}`,
        _isNew: true,
        _record_id: null,
        from_operation_category_id: categories[0]?._record_id ?? categories[0]?.id ?? '',
        operation_result: results[1]?.value ?? results[0]?.value ?? '',
        condition_type: '',
        to_operation_category_id: categories[1]?._record_id ?? categories[0]?._record_id ?? categories[0]?.id ?? '',
        delay_minutes: 0,
        is_active: true,
        updated_by: '',
        updated_at: null,
    };
}

function categoryPayload(row) {
    return {
        name: String(row.name ?? '').trim(),
        sort_order: Math.max(0, Number(row.sort_order || 0)),
        is_start: Boolean(row.is_start),
        is_pool: Boolean(row.is_pool ?? row.pool),
        duration_minutes: Math.max(0, Number(row.duration_minutes || 0)),
        is_active: Boolean(row.is_active ?? true),
    };
}

function workflowPayload(row) {
    return {
        from_operation_category_id: row.from_operation_category_id ? Number(row.from_operation_category_id) : null,
        condition_type: String(row.condition_type ?? '').trim(),
        operation_result: String(row.operation_result ?? '').trim(),
        to_operation_category_id: row.to_operation_category_id ? Number(row.to_operation_category_id) : null,
        delay_minutes: Math.max(0, Number(row.delay_minutes || 0)),
        is_active: Boolean(row.is_active ?? true),
    };
}

function IconButton({ title, icon, danger = false, disabled = false, onClick }) {
    return (
        <button
            type="button"
            className={`ps-op-icon-button${danger ? ' is-danger' : ''}`}
            title={title}
            disabled={disabled}
            onClick={onClick}
        >
            <i className={`fa ${icon}`} aria-hidden="true" />
        </button>
    );
}

function OperationCategoriesTable({ rows, setRows, onSave, onDelete, askBeforeDelete, savingId }) {
    const updateRow = (index, patch) => {
        setRows((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
    };

    return (
        <section className="ps-op-panel ps-op-panel-left">
            <div className="ps-op-panel-title">
                <span>Danh sách tác nghiệp</span>
                <button type="button" className="ps-op-add-link" onClick={() => setRows((current) => [...current, createDraftCategory()])}>
                    <i className="fa fa-plus" /> Thêm
                </button>
            </div>
            <div className="ps-op-table-wrap">
                <table className="table table-bordered table-striped ps-op-table ps-op-category-table">
                    <thead>
                        <tr>
                            <th className="id-col">Id</th>
                            <th>Tên</th>
                            <th className="sort-col">STT</th>
                            <th className="check-col">Khởi đầu</th>
                            <th className="check-col">Kho số</th>
                            <th className="check-col">Sửa giờ</th>
                            <th className="updated-col">Cập nhật</th>
                            <th className="actions-col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => {
                            const id = row._record_id ?? row.id;
                            return (
                                <tr key={id ?? row.id ?? index} className={!row.is_active ? 'is-inactive' : ''}>
                                    <td className="text-center">{row._isNew ? 'Mới' : id}</td>
                                    <td>
                                        <input className="form-control input-sm" value={row.name ?? ''} onChange={(event) => updateRow(index, { name: event.target.value })} />
                                    </td>
                                    <td>
                                        <input className="form-control input-sm text-center" type="number" min="0" value={row.sort_order ?? 0} onChange={(event) => updateRow(index, { sort_order: event.target.value })} />
                                    </td>
                                    <td className="text-center">
                                        <input type="checkbox" checked={Boolean(row.is_start)} onChange={(event) => updateRow(index, { is_start: event.target.checked })} />
                                    </td>
                                    <td className="text-center">
                                        <input type="checkbox" checked={Boolean(row.is_pool ?? row.pool)} onChange={(event) => updateRow(index, { is_pool: event.target.checked, pool: event.target.checked })} />
                                    </td>
                                    <td>
                                        <input className="form-control input-sm text-center" type="number" min="0" value={row.duration_minutes ?? 0} onChange={(event) => updateRow(index, { duration_minutes: event.target.value })} />
                                    </td>
                                    <td className="text-center ps-op-updated">
                                        <strong>{row.updated_by || '—'}</strong>
                                        <span>{formatDateTime(row.updated_at)}</span>
                                    </td>
                                    <td className="text-center">
                                        <IconButton title="Lưu" icon={savingId === id ? 'fa-spinner fa-spin' : 'fa-save'} disabled={savingId === id} onClick={() => onSave(row)} />
                                        <IconButton title="Xóa" icon="fa-trash" danger onClick={() => onDelete(row, askBeforeDelete)} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <label className="ps-op-confirm-delete">
                <input type="checkbox" checked readOnly /> Hỏi lại khi xóa
            </label>
        </section>
    );
}

function OperationResultsTable({ results, setResults, onSave, savingValue }) {
    const updateRow = (index, patch) => {
        setResults((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
    };

    return (
        <section className="ps-op-panel ps-op-panel-right">
            <div className="ps-op-panel-title">
                <span>Danh sách kết quả tác nghiệp</span>
                <span className="ps-op-business-chip" title="Kết quả tác nghiệp là cấu hình nghiệp vụ thật. Chọn Chốt đơn sẽ gọi luồng chốt đơn, trừ tồn kho và ghi doanh thu.">Business config</span>
            </div>
            <div className="ps-op-table-wrap">
                <table className="table table-bordered table-striped ps-op-table ps-op-result-table">
                    <thead>
                        <tr>
                            <th className="id-col">Id</th>
                            <th>Tên</th>
                            <th className="check-col">Chốt đơn</th>
                            <th className="check-col">Áp dụng</th>
                            <th className="updated-col">Ảnh hưởng nghiệp vụ</th>
                            <th className="actions-col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {results.map((result, index) => {
                            const saving = savingValue === result.value;
                            return (
                                <tr key={result.value} className={!result.is_active ? 'is-inactive' : ''}>
                                    <td className="text-center">{result.legacy_id ?? (109117 + index)}</td>
                                    <td>
                                        <input className="form-control input-sm" value={result.label ?? ''} onChange={(event) => updateRow(index, { label: event.target.value })} />
                                    </td>
                                    <td className="text-center"><input type="checkbox" checked={Boolean(result.closes_order)} onChange={(event) => updateRow(index, { closes_order: event.target.checked })} /></td>
                                    <td className="text-center"><input type="checkbox" checked={result.is_active !== false} onChange={(event) => updateRow(index, { is_active: event.target.checked })} /></td>
                                    <td className="ps-op-business-effect">
                                        {result.closes_order ? 'Chốt đơn, khóa mã đơn, trừ tồn kho và ghi nhận doanh thu.' : 'Cập nhật kết quả sale, chuyển bước theo luồng bên dưới.'}
                                    </td>
                                    <td className="text-center">
                                        <IconButton title="Lưu" icon={saving ? 'fa-spinner fa-spin' : 'fa-save'} disabled={saving} onClick={() => onSave(result)} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <label className="ps-op-confirm-delete">
                <input type="checkbox" checked readOnly /> Cấu hình này được dùng trực tiếp khi sale cập nhật kết quả tác nghiệp.
            </label>
        </section>
    );
}

function OperationWorkflowTable({ rows, setRows, categories, results, routeUrl, savingId, setSavingId, setFlash }) {
    const workflowsUrl = routeUrl.replace('/operation-categories', '/operation-workflows');
    const updateRow = (index, patch) => {
        setRows((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
    };

    const save = async (row) => {
        const payload = workflowPayload(row);
        if (!payload.operation_result) {
            setFlash({ type: 'danger', message: 'Vui lòng chọn kết quả tác nghiệp.' });
            return;
        }
        setSavingId(row._record_id ?? row.id);
        try {
            const url = row._isNew ? `${workflowsUrl}/records` : `${workflowsUrl}/records/${row._record_id ?? row.id}`;
            await requestJson(url, row._isNew ? 'POST' : 'PUT', { payload });
            setFlash({ type: 'success', message: 'Đã lưu luồng chuyển tác nghiệp.' });
            router.reload({ preserveScroll: true });
        } catch (exception) {
            setFlash({ type: 'danger', message: exception.message });
        } finally {
            setSavingId(null);
        }
    };

    const remove = async (row) => {
        if (row._isNew) {
            setRows((current) => current.filter((item) => item !== row));
            return;
        }
        if (!window.confirm('Xóa cấu hình chuyển bước này?')) return;
        setSavingId(row._record_id ?? row.id);
        try {
            await requestJson(`${workflowsUrl}/records/${row._record_id ?? row.id}`, 'DELETE');
            setFlash({ type: 'success', message: 'Đã xóa luồng chuyển tác nghiệp.' });
            router.reload({ preserveScroll: true });
        } catch (exception) {
            setFlash({ type: 'danger', message: exception.message });
        } finally {
            setSavingId(null);
        }
    };

    return (
        <section className="ps-op-panel ps-op-workflow-panel">
            <div className="ps-op-panel-title">
                <span>Danh sách tác nghiệp sau bao lâu</span>
                <button type="button" className="ps-op-add-link" onClick={() => setRows((current) => [...current, createDraftWorkflow(categories, results)])}>
                    <i className="fa fa-plus" /> Thêm
                </button>
            </div>
            <div className="ps-op-table-wrap">
                <table className="table table-bordered table-striped ps-op-table ps-op-workflow-table">
                    <thead>
                        <tr>
                            <th className="id-col">Id</th>
                            <th>Nếu đang ở tác nghiệp</th>
                            <th>Kết quả</th>
                            <th>Thì chuyển sang</th>
                            <th className="delay-col">Sau bao lâu</th>
                            <th className="check-col">Áp dụng</th>
                            <th className="updated-col">Cập nhật</th>
                            <th className="actions-col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => {
                            const rowId = row._record_id ?? row.id;
                            return (
                                <tr key={rowId ?? index}>
                                    <td className="text-center">{row._isNew ? 'Mới' : rowId}</td>
                                    <td>
                                        <select className="form-control input-sm" value={row.from_operation_category_id ?? ''} onChange={(event) => updateRow(index, { from_operation_category_id: event.target.value })}>
                                            <option value="">Mọi tác nghiệp</option>
                                            {categories.map((category) => <option key={category._record_id ?? category.id} value={category._record_id ?? category.id}>{category.name}</option>)}
                                        </select>
                                    </td>
                                    <td>
                                        <select className="form-control input-sm" value={row.operation_result ?? ''} onChange={(event) => updateRow(index, { operation_result: event.target.value })}>
                                            <option value="">-- Kết quả --</option>
                                            {results.map((result) => <option key={result.value} value={result.value}>{result.label}</option>)}
                                        </select>
                                    </td>
                                    <td>
                                        <select className="form-control input-sm" value={row.to_operation_category_id ?? ''} onChange={(event) => updateRow(index, { to_operation_category_id: event.target.value })}>
                                            <option value="">Không đổi</option>
                                            {categories.map((category) => <option key={category._record_id ?? category.id} value={category._record_id ?? category.id}>{category.name}</option>)}
                                        </select>
                                    </td>
                                    <td>
                                        <input className="form-control input-sm text-center" type="number" min="0" value={row.delay_minutes ?? 0} onChange={(event) => updateRow(index, { delay_minutes: event.target.value })} />
                                    </td>
                                    <td className="text-center"><input type="checkbox" checked={Boolean(row.is_active)} onChange={(event) => updateRow(index, { is_active: event.target.checked })} /></td>
                                    <td className="text-center ps-op-updated"><strong>{row.updated_by || '—'}</strong><span>{formatDateTime(row.updated_at)}</span></td>
                                    <td className="text-center">
                                        <IconButton title="Lưu" icon={savingId === rowId ? 'fa-spinner fa-spin' : 'fa-save'} disabled={savingId === rowId} onClick={() => save(row)} />
                                        <IconButton title="Xóa" icon="fa-trash" danger onClick={() => remove(row)} />
                                    </td>
                                </tr>
                            );
                        }) : (
                            <tr><td colSpan="8" className="text-center text-muted">Chưa có cấu hình chuyển bước. Hệ thống đang dùng rule mặc định của business.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function OperationCategoriesPage({ schema, rows = [], filterOptions = {}, routeUrl, pageRuntimeError = null }) {
    const [categories, setCategories] = useState(() => rows.map(normalizeCategory));
    const [workflows, setWorkflows] = useState(() => (filterOptions.operationWorkflowsFull ?? []).map(normalizeWorkflow));
    const [askBeforeDelete, setAskBeforeDelete] = useState(true);
    const [savingId, setSavingId] = useState(null);
    const [workflowSavingId, setWorkflowSavingId] = useState(null);
    const [flash, setFlash] = useState(null);

    const initialResults = useMemo(() => {
        const source = filterOptions.operationResults ?? [];
        return source.map((item, index) => ({
            value: item.value ?? item.id,
            label: item.label ?? item.name ?? item.value ?? '',
            legacy_id: item.legacy_id ?? (109117 + index),
            sort_order: item.sort_order ?? (index + 1),
            closes_order: Boolean(item.closes_order),
            is_active: item.is_active !== false,
        })).filter((item) => item.value && item.label);
    }, [filterOptions.operationResults]);
    const [results, setResults] = useState(() => initialResults);
    const [resultSavingValue, setResultSavingValue] = useState(null);

    const activeCount = categories.filter((item) => item.is_active !== false).length;

    const saveCategory = async (row) => {
        const payload = categoryPayload(row);
        if (!payload.name) {
            setFlash({ type: 'danger', message: 'Vui lòng nhập tên tác nghiệp.' });
            return;
        }

        const id = row._record_id ?? row.id;
        setSavingId(id);
        try {
            const url = row._isNew ? `${routeUrl}/records` : `${routeUrl}/records/${id}`;
            await requestJson(url, row._isNew ? 'POST' : 'PUT', { payload });
            setFlash({ type: 'success', message: 'Đã lưu danh mục tác nghiệp.' });
            router.reload({ preserveScroll: true });
        } catch (exception) {
            setFlash({ type: 'danger', message: exception.message });
        } finally {
            setSavingId(null);
        }
    };

    const deleteCategory = async (row, confirmBeforeDelete) => {
        if (row._isNew) {
            setCategories((current) => current.filter((item) => item !== row));
            return;
        }
        const id = row._record_id ?? row.id;
        if (confirmBeforeDelete && !window.confirm(`Xóa tác nghiệp “${row.name}”?`)) return;

        setSavingId(id);
        try {
            await requestJson(`${routeUrl}/records/${id}`, 'DELETE');
            setFlash({ type: 'success', message: 'Đã xóa danh mục tác nghiệp.' });
            router.reload({ preserveScroll: true });
        } catch (exception) {
            setFlash({ type: 'danger', message: exception.message });
        } finally {
            setSavingId(null);
        }
    };

    const saveResult = async (row) => {
        const payload = {
            label: String(row.label ?? '').trim(),
            closes_order: Boolean(row.closes_order),
            is_active: row.is_active !== false,
            sort_order: Number(row.sort_order || 0),
        };
        if (!payload.label) {
            setFlash({ type: 'danger', message: 'Vui lòng nhập tên kết quả tác nghiệp.' });
            return;
        }

        setResultSavingValue(row.value);
        try {
            await requestJson(`${routeUrl}/results/${encodeURIComponent(row.value)}`, 'PATCH', { payload });
            setFlash({ type: 'success', message: 'Đã lưu kết quả tác nghiệp.' });
            router.reload({ preserveScroll: true });
        } catch (exception) {
            setFlash({ type: 'danger', message: exception.message });
        } finally {
            setResultSavingValue(null);
        }
    };

    const pageActions = (
        <>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setCategories((current) => [...current, createDraftCategory()])}>
                <i className="fa fa-plus" /> Thêm tác nghiệp
            </button>
            <button type="button" className="btn btn-default btn-sm" onClick={() => router.reload({ preserveScroll: true })}>
                <i className="fa fa-refresh" /> Tải lại
            </button>
        </>
    );

    const filters = (
        <div className="ps-op-summary-strip">
            <span><b>{numberFormatter.format(categories.length)}</b> tác nghiệp</span>
            <span><b>{numberFormatter.format(results.length)}</b> kết quả</span>
            <span><b>{numberFormatter.format(workflows.length)}</b> luồng chuyển bước</span>
            <span><b>{numberFormatter.format(activeCount)}</b> đang áp dụng</span>
            <label>
                <input type="checkbox" checked={askBeforeDelete} onChange={(event) => setAskBeforeDelete(event.target.checked)} /> Hỏi lại khi xóa
            </label>
        </div>
    );

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Quản lý danh mục tác nghiệp'} />
            <PushsalePageFrame title={schema?.title ?? 'Quản lý danh mục tác nghiệp'} actions={pageActions} filters={filters} className="ps-operation-categories-page" data-page-code="1.8.1">
                {pageRuntimeError && <div className="alert alert-danger"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>}
                {flash && <div className={`alert alert-${flash.type === 'success' ? 'success' : 'danger'}`}><i className={`fa ${flash.type === 'success' ? 'fa-check' : 'fa-exclamation-triangle'}`} /> {flash.message}</div>}

                <div className="ps-op-grid-two">
                    <OperationCategoriesTable rows={categories} setRows={setCategories} onSave={saveCategory} onDelete={deleteCategory} askBeforeDelete={askBeforeDelete} savingId={savingId} />
                    <OperationResultsTable results={results} setResults={setResults} onSave={saveResult} savingValue={resultSavingValue} />
                </div>

                <OperationWorkflowTable
                    rows={workflows}
                    setRows={setWorkflows}
                    categories={categories.filter((category) => !category._isNew)}
                    results={results}
                    routeUrl={routeUrl}
                    savingId={workflowSavingId}
                    setSavingId={setWorkflowSavingId}
                    setFlash={setFlash}
                />
            </PushsalePageFrame>
        </AppLayout>
    );
}
