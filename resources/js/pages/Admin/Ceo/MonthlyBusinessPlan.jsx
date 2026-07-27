import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function money(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number.toLocaleString('vi-VN') : '0';
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date);
}

function numberValue(value) {
    const number = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? number : 0;
}

const DEPARTMENTS = [
    ['marketing', 'Marketing'],
    ['sales', 'Sale'],
    ['warehouse', 'Kho'],
    ['accounting', 'Kế toán'],
    ['admin', 'CEO / Quản trị'],
    ['all', 'Tất cả'],
];

function buildQuery(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined));
}

export default function MonthlyKpiPlanPage({ schema, rows = [], summary = {}, routeUrl = '/admin/ceo/business-plan/monthly', pageRuntimeError = null }) {
    const { ask } = useConfirm();
    const query = currentQuery();
    const now = new Date();
    const [filters, setFilters] = useState({
        month: query.month || String(now.getMonth() + 1),
        year: query.year || String(now.getFullYear()),
        department: query.department || 'marketing',
    });
    const [draftRows, setDraftRows] = useState(() => (rows ?? []).map((row) => ({ ...row, _dirty: false })));
    const [message, setMessage] = useState('');

    useEffect(() => {
        setDraftRows((rows ?? []).map((row) => ({ ...row, _dirty: false })));
    }, [rows]);
    const [processing, setProcessing] = useState(false);

    const totals = useMemo(() => draftRows.reduce((acc, row) => {
        acc.budget += numberValue(row.budget);
        acc.clicks += numberValue(row.clicks);
        acc.contacts += numberValue(row.contacts);
        acc.revenue_target += numberValue(row.revenue_target);
        acc.base_salary += numberValue(row.base_salary);
        acc.income += numberValue(row.income || (numberValue(row.base_salary) + numberValue(row.revenue_target) * numberValue(row.bonus_percent) / 100));
        return acc;
    }, { budget: 0, clicks: 0, contacts: 0, revenue_target: 0, base_salary: 0, income: 0 }), [draftRows]);

    const setFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
    const runSearch = () => router.get(routeUrl, buildQuery(filters), { preserveScroll: true });
    const exportExcel = () => router.get(routeUrl, { ...buildQuery(filters), export: 1 }, { preserveScroll: true });

    const setRow = (index, key, value) => {
        setDraftRows((current) => current.map((row, idx) => idx === index ? { ...row, [key]: value, _dirty: true } : row));
    };

    const action = async (url, payload = {}, confirmText = null) => {
        if (confirmText) {
            const ok = await ask({ description: confirmText });
            if (!ok) return;
        }
        setMessage('');
        setProcessing(true);
        router.post(url, payload, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã cập nhật dữ liệu KPI.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không cập nhật được dữ liệu KPI.'),
            onFinish: () => setProcessing(false),
        });
    };

    const saveRow = (row, index) => {
        const payload = {
            payload: {
                user_id: row._form?.user_id,
                year: Number(filters.year),
                month: Number(filters.month),
                kpi_name: row.kpi,
                budget: numberValue(row.budget),
                clicks_target: numberValue(row.clicks),
                contacts_target: numberValue(row.contacts),
                revenue_target: numberValue(row.revenue_target),
                bonus_percent: numberValue(row.bonus_percent),
                base_salary: numberValue(row.base_salary),
                working_days: numberValue(row.working_days),
                actual_days: numberValue(row.actual_days),
                locked: Boolean(row.locked),
            },
        };
        router.patch(`${routeUrl}/records/${row._record_id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setDraftRows((current) => current.map((item, idx) => idx === index ? { ...item, _dirty: false } : item));
                setMessage('Đã lưu dòng KPI.');
            },
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không lưu được dòng KPI.'),
        });
    };

    const saveAll = () => {
        const changed = draftRows.filter((row) => row._dirty && row._record_id);
        if (changed.length === 0) {
            setMessage('Không có dòng nào thay đổi.');
            return;
        }
        action(`${routeUrl}/bulk-save`, {
            records: changed.map((row) => ({
                id: row._record_id,
                kpi_name: row.kpi,
                budget: numberValue(row.budget),
                clicks_target: numberValue(row.clicks),
                contacts_target: numberValue(row.contacts),
                revenue_target: numberValue(row.revenue_target),
                bonus_percent: numberValue(row.bonus_percent),
                base_salary: numberValue(row.base_salary),
                working_days: numberValue(row.working_days),
                actual_days: numberValue(row.actual_days),
                locked: Boolean(row.locked),
            })),
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Thiết lập KPI theo tháng'} />
            <div className="ps-monthly-kpi-page">
                {pageRuntimeError && <div className="alert alert-warning">{pageRuntimeError}</div>}

                <PageHeader
                    title="Thiết lập KPI theo tháng"
                    className="ps-monthly-kpi-header"
                    filters={(
                        <>
                            <select className="form-control" value={filters.month} onChange={(event) => setFilter('month', event.target.value)}>
                                <option value="">--Chọn Tháng--</option>
                                {Array.from({ length: 12 }, (_, idx) => idx + 1).map((month) => <option key={month} value={month}>{month}</option>)}
                            </select>
                            <select className="form-control" value={filters.year} onChange={(event) => setFilter('year', event.target.value)}>
                                {[now.getFullYear() - 1, now.getFullYear(), now.getFullYear() + 1].map((year) => <option key={year} value={year}>Năm {year}</option>)}
                            </select>
                            <select className="form-control" value={filters.department} onChange={(event) => setFilter('department', event.target.value)}>
                                {DEPARTMENTS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </>
                    )}
                    actions={(
                        <>
                            <button type="button" className="btn btn-primary btn-sm" onClick={runSearch} disabled={processing}>
                                <i className="fa fa-search" /> Tìm kiếm
                            </button>
                            <button type="button" className="btn btn-primary btn-sm" onClick={exportExcel}>
                                <i className="fa fa-file-excel-o" /> Xuất Excel
                            </button>
                        </>
                    )}
                />

                {message && <div className="ps-monthly-kpi-message">{message}</div>}

                <div className="ps-monthly-kpi-table-wrap">
                    <table className="table table-bordered ps-monthly-kpi-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tài khoản</th>
                                <th>Chức vụ</th>
                                <th>Tên KPI</th>
                                <th>Ngân sách / tháng</th>
                                <th>Số click / tháng</th>
                                <th>Số contact/ tháng</th>
                                <th>Doanh số / tháng</th>
                                <th>Tiền thưởng (% Doanh số)</th>
                                <th>Lương cứng</th>
                                <th>Tổng thu nhập</th>
                                <th>Chốt dữ liệu</th>
                                <th>Cập nhật kế hoạch</th>
                                <th>Cập nhật</th>
                                <th>
                                    <button
                                        type="button"
                                        className="ps-monthly-kpi-link"
                                        onClick={() => action(`${routeUrl}/add-missing`, buildQuery(filters), 'Bạn chắc chắn muốn thêm KPI tháng này?')}
                                    >
                                        <i className="fa fa-plus" /> Thêm KPI
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="rowsum">
                                <td colSpan={4} className="text-center font-weight-bold">Tổng:</td>
                                <td className="text-center font-weight-bold">{money(totals.budget)}</td>
                                <td className="text-center font-weight-bold">{money(totals.clicks)}</td>
                                <td className="text-center font-weight-bold">{money(totals.contacts)}</td>
                                <td className="text-center font-weight-bold">{money(totals.revenue_target)}</td>
                                <td />
                                <td className="text-center font-weight-bold">{money(totals.base_salary)}</td>
                                <td className="text-center font-weight-bold">{money(totals.income)}</td>
                                <td colSpan={4} />
                            </tr>
                            {draftRows.length ? draftRows.map((row, index) => (
                                <tr key={row._record_id ?? index}>
                                    <td className="text-center">{index + 1}</td>
                                    <td className="ps-monthly-kpi-account">{row.account || '—'}</td>
                                    <td className="text-center">{row.role || '—'}</td>
                                    <td><input className="form-control" value={row.kpi ?? ''} onChange={(event) => setRow(index, 'kpi', event.target.value)} /></td>
                                    <td><input className="form-control text-right" value={row.budget ?? 0} onChange={(event) => setRow(index, 'budget', numberValue(event.target.value))} /></td>
                                    <td><input className="form-control text-right" value={row.clicks ?? 0} onChange={(event) => setRow(index, 'clicks', numberValue(event.target.value))} /></td>
                                    <td><input className="form-control text-right" value={row.contacts ?? 0} onChange={(event) => setRow(index, 'contacts', numberValue(event.target.value))} /></td>
                                    <td><input className="form-control text-right" value={row.revenue_target ?? 0} onChange={(event) => setRow(index, 'revenue_target', numberValue(event.target.value))} /></td>
                                    <td><input className="form-control text-right" value={row.bonus_percent ?? 0} onChange={(event) => setRow(index, 'bonus_percent', numberValue(event.target.value))} /></td>
                                    <td><input className="form-control text-right" value={row.base_salary ?? 0} onChange={(event) => setRow(index, 'base_salary', numberValue(event.target.value))} /></td>
                                    <td className="text-right no-wrap">{money(numberValue(row.income || (numberValue(row.base_salary) + numberValue(row.revenue_target) * numberValue(row.bonus_percent) / 100)))} đ</td>
                                    <td className="text-center"><input type="checkbox" checked={Boolean(row.locked)} onChange={(event) => setRow(index, 'locked', event.target.checked)} /></td>
                                    <td className="text-center no-wrap"><button type="button" className="ps-monthly-kpi-link" onClick={() => saveRow(row, index)}><i className="fa fa-save" /> Lưu dòng</button></td>
                                    <td className="text-center no-wrap">{formatDateTime(row.updated_at)}</td>
                                    <td />
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={15} className="text-center ps-monthly-kpi-empty">Không có dữ liệu KPI. Bấm “Thêm KPI” để tạo kế hoạch cho nhân viên chưa có.</td>
                                </tr>
                            )}
                            <tr>
                                <td colSpan={11} className="text-left">
                                    <button type="button" className="ps-monthly-kpi-link pull-right" onClick={() => action(`${routeUrl}/copy-previous`, buildQuery(filters), 'Bạn chắc chắn muốn copy dữ liệu tháng trước?')}>
                                        <i className="fa fa-copy" /> Copy dữ liệu tháng trước
                                    </button>
                                </td>
                                <td className="text-center no-wrap">
                                    <button type="button" className="ps-monthly-kpi-link" onClick={() => action(`${routeUrl}/lock-period`, buildQuery(filters), 'Bạn chắc chắn muốn chốt dữ liệu tháng này?')}>
                                        <i className="fa fa-check" /> Chốt dữ liệu
                                    </button>
                                </td>
                                <td className="text-center" colSpan={2}>
                                    <button type="button" className="ps-monthly-kpi-link" onClick={saveAll}>
                                        <i className="fa fa-save" /> Lưu
                                    </button>
                                </td>
                                <td className="text-center no-wrap" />
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
