import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';

function numberValue(value) {
    const number = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? number : 0;
}

function money(value) {
    return numberValue(value).toLocaleString('vi-VN');
}

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

const POSITIONS = [
    ['marketing', 'Marketing'],
    ['sales', 'Sale'],
];

function monthOptions() {
    return [['0', '--Chọn Tháng--'], ...Array.from({ length: 12 }, (_, index) => [String(index + 1), String(index + 1)])];
}

function yearOptions() {
    const year = new Date().getFullYear();
    return Array.from({ length: 8 }, (_, index) => year + 1 - index);
}

function emptyRow(filters, index = 0) {
    return {
        id: null,
        _new: true,
        _dirty: true,
        position_key: filters.position_key || 'marketing',
        position_label: filters.position_key === 'sales' ? 'Sale' : 'Marketing',
        year: Number(filters.year || new Date().getFullYear()),
        month: Number(filters.month || new Date().getMonth() + 1),
        revenue_from: 0,
        revenue_to: 0,
        bonus_percent: 0,
        bonus_amount: 0,
        locked: false,
        sort_order: index,
    };
}

function normalizeRows(rows) {
    return (rows ?? []).map((row) => ({ ...row, _new: false, _dirty: false }));
}

export default function RevenueBonusSetupPage({ rows = [], filters = {}, routeUrl = '/admin/ceo/business-plan/revenue-bonus', activeMenuCode = '7.1.4' }) {
    const query = currentQuery();
    const [draftFilters, setDraftFilters] = useState({
        year: Number(filters.year || query.year || new Date().getFullYear()),
        month: Number(filters.month ?? query.month ?? new Date().getMonth() + 1),
        position_key: filters.position_key || query.position_key || 'marketing',
    });
    const [draftRows, setDraftRows] = useState(() => normalizeRows(rows));
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setDraftRows(normalizeRows(rows));
    }, [rows]);

    const total = useMemo(() => draftRows.reduce((acc, row) => {
        acc.revenue_from += numberValue(row.revenue_from);
        acc.revenue_to += numberValue(row.revenue_to);
        acc.bonus_amount += numberValue(row.bonus_amount);
        return acc;
    }, { revenue_from: 0, revenue_to: 0, bonus_amount: 0 }), [draftRows]);

    const setFilter = (key, value) => setDraftFilters((current) => ({ ...current, [key]: value }));
    const runSearch = () => router.get(routeUrl, draftFilters, { preserveScroll: true });
    const exportExcel = () => router.get(routeUrl, { ...draftFilters, export: 1 }, { preserveScroll: true });

    const addRow = () => setDraftRows((current) => [...current, emptyRow(draftFilters, current.length + 1)]);
    const setRow = (index, key, value) => {
        setDraftRows((current) => current.map((row, idx) => idx === index ? { ...row, [key]: value, _dirty: true } : row));
    };

    const payloadRows = () => draftRows
        .filter((row) => row._dirty || row._new)
        .map((row, index) => ({
            id: row.id || null,
            position_key: row.position_key || draftFilters.position_key,
            year: numberValue(row.year || draftFilters.year),
            month: numberValue(row.month || draftFilters.month || new Date().getMonth() + 1),
            revenue_from: numberValue(row.revenue_from),
            revenue_to: numberValue(row.revenue_to),
            bonus_percent: numberValue(row.bonus_percent),
            bonus_amount: numberValue(row.bonus_amount),
            locked: Boolean(row.locked),
            sort_order: numberValue(row.sort_order || index + 1),
        }));

    const saveAll = () => {
        const records = payloadRows();
        if (!records.length) {
            setMessage('Không có dòng nào thay đổi.');
            return;
        }
        const invalid = records.find((row) => row.revenue_to > 0 && row.revenue_to <= row.revenue_from);
        if (invalid) {
            setMessage('Doanh số tháng đến nhỏ hơn phải lớn hơn doanh số tháng từ.');
            return;
        }
        setProcessing(true);
        setMessage('');
        router.post(`${routeUrl}/bulk-save`, { records }, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã lưu thiết lập thưởng theo doanh số.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không lưu được thiết lập thưởng.'),
            onFinish: () => setProcessing(false),
        });
    };

    const destroyRow = (row, index) => {
        if (!window.confirm('Bạn chắc chắn muốn xóa dòng thưởng này?')) return;
        if (!row.id) {
            setDraftRows((current) => current.filter((_, idx) => idx !== index));
            return;
        }
        router.delete(`${routeUrl}/records/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã xóa dòng thưởng.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không xóa được dòng thưởng.'),
        });
    };

    const copyPrevious = () => {
        if (!window.confirm('Bạn chắc chắn muốn copy dữ liệu tháng trước?')) return;
        setProcessing(true);
        router.post(`${routeUrl}/copy-previous`, draftFilters, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã copy dữ liệu tháng trước.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không copy được dữ liệu tháng trước.'),
            onFinish: () => setProcessing(false),
        });
    };

    const setLocked = (locked) => {
        const message = locked ? 'Bạn chắc chắn muốn chốt dữ liệu tháng này?' : 'Bạn chắc chắn muốn hủy chốt dữ liệu tháng này?';
        if (!window.confirm(message)) return;
        setProcessing(true);
        router.post(`${routeUrl}/lock-period`, { ...draftFilters, locked }, {
            preserveScroll: true,
            onSuccess: () => setMessage(locked ? 'Đã chốt dữ liệu.' : 'Đã hủy chốt dữ liệu.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không cập nhật được trạng thái chốt.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="(UnitAdmin) Thiết lập tiền thưởng theo doanh số" />
            <div className="ps-revenue-bonus-page">
                <PageHeader
                    title="(UnitAdmin) Thiết lập tiền thưởng theo doanh số"
                    pageCode={activeMenuCode}
                    className="ps-revenue-bonus-header"
                    filters={(
                        <>
                            <select className="form-control" value={String(draftFilters.month)} onChange={(event) => setFilter('month', Number(event.target.value))}>
                                {monthOptions().map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            <select className="form-control" value={String(draftFilters.year)} onChange={(event) => setFilter('year', Number(event.target.value))}>
                                {yearOptions().map((year) => <option key={year} value={year}>Năm {year}</option>)}
                            </select>
                            <select className="form-control" value={draftFilters.position_key} onChange={(event) => setFilter('position_key', event.target.value)}>
                                {POSITIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </>
                    )}
                    actions={(
                        <>
                            <button type="button" className="btn btn-sm btn-primary" onClick={runSearch} disabled={processing}><i className="fa fa-search" /> Tìm kiếm</button>
                            <button type="button" className="btn btn-sm btn-primary" onClick={exportExcel}><i className="fa fa-file-excel-o" /> Xuất Excel</button>
                        </>
                    )}
                />

                {message && <div className="ps-revenue-bonus-message">{message}</div>}

                <div className="ps-revenue-bonus-table-wrap">
                    <table className="table table-bordered ps-revenue-bonus-table">
                        <thead>
                            <tr>
                                <th className="text-center">STT</th>
                                <th className="text-center">Chức vụ</th>
                                <th className="text-center no-wrap">Doanh số tháng từ</th>
                                <th className="text-center no-wrap">Doanh số tháng đến nhỏ hơn</th>
                                <th className="text-center no-wrap">% thưởng theo doanh số</th>
                                <th className="text-center no-wrap">Tiền thưởng</th>
                                <th className="text-center no-wrap">Chốt dữ liệu</th>
                                <th className="text-center no-wrap">Cập nhật</th>
                                <th className="text-center no-wrap"><button type="button" className="ps-revenue-bonus-link" onClick={addRow}><i className="fa fa-plus" /> <span className="text">Thêm</span></button></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="rowsum">
                                <td colSpan="2" className="text-center font-weight-bold">Tổng:</td>
                                <td className="text-right font-weight-bold">{money(total.revenue_from)}</td>
                                <td className="text-right font-weight-bold">{money(total.revenue_to)}</td>
                                <td />
                                <td className="text-right font-weight-bold">{money(total.bonus_amount)}</td>
                                <td colSpan="3" />
                            </tr>
                            {draftRows.length ? draftRows.map((row, index) => (
                                <tr key={row.id || `new-${index}`}>
                                    <td className="text-center">{index + 1}</td>
                                    <td>
                                        <select className="form-control" value={row.position_key || draftFilters.position_key} onChange={(event) => setRow(index, 'position_key', event.target.value)} disabled={Boolean(row.locked)}>
                                            {POSITIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                    </td>
                                    <td><input className="form-control text-right" value={row.revenue_from ?? 0} onChange={(event) => setRow(index, 'revenue_from', numberValue(event.target.value))} disabled={Boolean(row.locked)} /></td>
                                    <td><input className="form-control text-right" value={row.revenue_to ?? 0} onChange={(event) => setRow(index, 'revenue_to', numberValue(event.target.value))} disabled={Boolean(row.locked)} /></td>
                                    <td><input className="form-control text-right" value={row.bonus_percent ?? 0} onChange={(event) => setRow(index, 'bonus_percent', numberValue(event.target.value))} disabled={Boolean(row.locked)} /></td>
                                    <td><input className="form-control text-right" value={row.bonus_amount ?? 0} onChange={(event) => setRow(index, 'bonus_amount', numberValue(event.target.value))} disabled={Boolean(row.locked)} /></td>
                                    <td className="text-center">{row.locked ? <span className="ps-revenue-bonus-locked">Đã chốt</span> : <span className="ps-revenue-bonus-open">Chưa chốt</span>}</td>
                                    <td className="text-center no-wrap">{row.updated_by ? <><strong>{row.updated_by}</strong><br /></> : null}{row.updated_at ?? ''}</td>
                                    <td className="text-center no-wrap"><button type="button" className="ps-revenue-bonus-icon" onClick={() => destroyRow(row, index)} disabled={Boolean(row.locked)} title="Xóa"><i className="fa fa-trash" /></button></td>
                                </tr>
                            )) : <tr><td colSpan="9" className="text-center ps-revenue-bonus-empty">Không có dữ liệu. Bấm “Thêm” hoặc “Copy dữ liệu tháng trước” để khai báo thưởng.</td></tr>}
                            <tr>
                                <td colSpan="6" className="text-right"><button type="button" className="ps-revenue-bonus-link" onClick={copyPrevious} disabled={processing}><i className="fa fa-copy" /> <span className="text">Copy dữ liệu tháng trước</span></button></td>
                                <td className="text-center no-wrap"><button type="button" className="ps-revenue-bonus-link" onClick={() => setLocked(true)} disabled={processing}><i className="fa fa-check" /> <span className="text">Chốt dữ liệu</span></button><button type="button" className="ps-revenue-bonus-link" onClick={() => setLocked(false)} disabled={processing}><i className="fa fa-close" /> <span className="text">Hủy chốt dữ liệu</span></button></td>
                                <td />
                                <td className="text-center"><button type="button" className="ps-revenue-bonus-link" onClick={saveAll} disabled={processing}><i className="fa fa-save" /> <span className="text">Lưu</span></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
