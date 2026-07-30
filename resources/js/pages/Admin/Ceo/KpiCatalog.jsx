import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import {
    CeoPlanToolbar,
    CEO_POSITIONS,
    ceoCurrentQuery,
    ceoFormatDateTime,
    ceoMoney,
    ceoNumberValue,
} from '@/components/ceo/CeoPlanToolbar';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

function emptyRow(position, index = 0) {
    return {
        id: null,
        _record_id: null,
        position_key: position,
        position_label: position === 'sales' ? 'Sale' : 'Marketing',
        kpi_name: '',
        daily_budget: 0,
        daily_clicks: 0,
        daily_contacts: 0,
        daily_revenue: 0,
        daily_new_contacts: 0,
        daily_new_closed: 0,
        daily_old_contacts: 0,
        daily_old_closed: 0,
        is_active: true,
        sort_order: index,
        _dirty: true,
        _new: true,
    };
}

function normalizedRows(rows) {
    return (rows ?? []).map((row) => ({
        ...row,
        position_key: row.position_key || row._role || 'marketing',
        is_active: row.is_active !== false,
        _dirty: false,
        _new: false,
    }));
}

export default function KpiCatalogPage({ schema, rows = [], routeUrl = '/admin/ceo/business-plan/kpi-catalog', pageRuntimeError = null }) {
    const { ask } = useConfirm();
    const query = ceoCurrentQuery();
    const [filters, setFilters] = useState({ position_key: query.position_key || query.role || 'marketing' });
    const [draftRows, setDraftRows] = useState(() => normalizedRows(rows));
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setDraftRows(normalizedRows(rows));
    }, [rows]);

    const isSales = filters.position_key === 'sales';
    const totals = useMemo(() => draftRows.reduce((acc, row) => {
        acc.daily_budget += ceoNumberValue(row.daily_budget);
        acc.daily_clicks += ceoNumberValue(row.daily_clicks);
        acc.daily_contacts += ceoNumberValue(row.daily_contacts);
        acc.daily_revenue += ceoNumberValue(row.daily_revenue);
        acc.daily_new_contacts += ceoNumberValue(row.daily_new_contacts);
        acc.daily_new_closed += ceoNumberValue(row.daily_new_closed);
        acc.daily_old_contacts += ceoNumberValue(row.daily_old_contacts);
        acc.daily_old_closed += ceoNumberValue(row.daily_old_closed);
        return acc;
    }, {
        daily_budget: 0,
        daily_clicks: 0,
        daily_contacts: 0,
        daily_revenue: 0,
        daily_new_contacts: 0,
        daily_new_closed: 0,
        daily_old_contacts: 0,
        daily_old_closed: 0,
    }), [draftRows]);

    const runSearch = () => router.get(routeUrl, filters, { preserveScroll: true });

    const setFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
    const setRow = (index, key, value) => {
        setDraftRows((current) => current.map((row, idx) => idx === index ? { ...row, [key]: value, _dirty: true } : row));
    };

    const addRow = () => setDraftRows((current) => [...current, emptyRow(filters.position_key, current.length + 1)]);

    const initializeDefaults = async () => {
        const ok = await ask({ description: 'Bạn chắc chắn muốn khởi tạo?' });
        if (!ok) return;
        setProcessing(true);
        setMessage('');
        router.post(`${routeUrl}/initialize-defaults`, filters, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã khởi tạo danh mục KPI mặc định.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không khởi tạo được danh mục KPI.'),
            onFinish: () => setProcessing(false),
        });
    };

    const saveAll = () => {
        const records = draftRows
            .filter((row) => row._dirty || row._new)
            .map((row, index) => ({
                id: row._record_id || row.id || null,
                position_key: filters.position_key,
                kpi_name: row.kpi_name,
                daily_budget: ceoNumberValue(row.daily_budget),
                daily_clicks: ceoNumberValue(row.daily_clicks),
                daily_contacts: ceoNumberValue(row.daily_contacts),
                daily_revenue: ceoNumberValue(row.daily_revenue),
                daily_new_contacts: ceoNumberValue(row.daily_new_contacts),
                daily_new_closed: ceoNumberValue(row.daily_new_closed),
                daily_old_contacts: ceoNumberValue(row.daily_old_contacts),
                daily_old_closed: ceoNumberValue(row.daily_old_closed),
                is_active: Boolean(row.is_active),
                sort_order: ceoNumberValue(row.sort_order || index + 1),
            }));

        if (!records.length) {
            setMessage('Không có dòng nào thay đổi.');
            return;
        }

        const missingName = records.some((record) => !String(record.kpi_name || '').trim());
        if (missingName) {
            setMessage('Tên KPI không được để trống.');
            return;
        }

        setProcessing(true);
        setMessage('');
        router.post(`${routeUrl}/bulk-save`, { records }, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã lưu danh mục KPI.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không lưu được danh mục KPI.'),
            onFinish: () => setProcessing(false),
        });
    };

    const destroyRow = async (row, index) => {
        const ok = await ask({ description: 'Bạn chắc chắn muốn xóa KPI này?', confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        if (!row._record_id && !row.id) {
            setDraftRows((current) => current.filter((_, idx) => idx !== index));
            return;
        }
        router.delete(`${routeUrl}/records/${row._record_id || row.id}`, {
            preserveScroll: true,
            onSuccess: () => setMessage('Đã xóa danh mục KPI.'),
            onError: (errors) => setMessage(Object.values(errors ?? {})[0] ?? 'Không xóa được danh mục KPI.'),
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? '(Unit admin) Danh mục KPI'} />
            <div className="ps-kpi-catalog-page">
                {pageRuntimeError && <div className="alert alert-warning">{pageRuntimeError}</div>}

                <CeoPlanToolbar
                    title="(Unit admin) Danh mục KPI"
                    className="ps-kpi-catalog-header"
                    filtersSlot={(
                        <select className="form-control" value={filters.position_key} onChange={(event) => setFilter('position_key', event.target.value)}>
                            {CEO_POSITIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    )}
                    onSearch={runSearch}
                    routeUrl={routeUrl}
                    exportFilters={filters}
                    searchDisabled={processing}
                />

                {message && <div className="ps-kpi-catalog-message">{message}</div>}

                <div className="ps-kpi-catalog-table-wrap">
                    <table className="table table-bordered ps-kpi-catalog-table">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: 60 }}>Id</th>
                                <th className="text-center no-wrap">Tên KPI</th>
                                <th className="text-center no-wrap">Chức vụ</th>
                                {!isSales && <th className="text-center">Ngân sách / ngày</th>}
                                {!isSales && <th className="text-center">Số click / ngày</th>}
                                {!isSales && <th className="text-center">Số contact/ ngày</th>}
                                {isSales && <th className="text-center">Số contact mới / ngày</th>}
                                {isSales && <th className="text-center">Chốt đơn mới / ngày</th>}
                                {isSales && <th className="text-center">Số contact cũ / ngày</th>}
                                {isSales && <th className="text-center">Chốt đơn cũ / ngày</th>}
                                <th className="text-center">Doanh số / ngày</th>
                                <th className="text-center">Cập nhật</th>
                                <th className="text-center no-wrap" style={{ width: 80 }}>
                                    <button type="button" className="ps-kpi-catalog-link" onClick={addRow}>
                                        <i className="fa fa-plus" /> <span className="text">Thêm</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="rowsum">
                                <td colSpan={3} className="text-center font-weight-bold">Tổng:</td>
                                {!isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_budget)}</td>}
                                {!isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_clicks)}</td>}
                                {!isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_contacts)}</td>}
                                {isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_new_contacts)}</td>}
                                {isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_new_closed)}</td>}
                                {isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_old_contacts)}</td>}
                                {isSales && <td className="text-right font-weight-bold">{ceoMoney(totals.daily_old_closed)}</td>}
                                <td className="text-right font-weight-bold">{ceoMoney(totals.daily_revenue)}</td>
                                <td colSpan={2} />
                            </tr>
                            {draftRows.length ? draftRows.map((row, index) => (
                                <tr key={row._record_id ?? `new-${index}`}>
                                    <td className="text-center">{row.id || ''}</td>
                                    <td><input className="form-control" value={row.kpi_name ?? ''} onChange={(event) => setRow(index, 'kpi_name', event.target.value)} /></td>
                                    <td className="text-center no-wrap">{row.position_label || (filters.position_key === 'sales' ? 'Sale' : 'Marketing')}</td>
                                    {!isSales && <td><input className="form-control text-right" value={row.daily_budget ?? 0} onChange={(event) => setRow(index, 'daily_budget', ceoNumberValue(event.target.value))} /></td>}
                                    {!isSales && <td><input className="form-control text-right" value={row.daily_clicks ?? 0} onChange={(event) => setRow(index, 'daily_clicks', ceoNumberValue(event.target.value))} /></td>}
                                    {!isSales && <td><input className="form-control text-right" value={row.daily_contacts ?? 0} onChange={(event) => setRow(index, 'daily_contacts', ceoNumberValue(event.target.value))} /></td>}
                                    {isSales && <td><input className="form-control text-right" value={row.daily_new_contacts ?? 0} onChange={(event) => setRow(index, 'daily_new_contacts', ceoNumberValue(event.target.value))} /></td>}
                                    {isSales && <td><input className="form-control text-right" value={row.daily_new_closed ?? 0} onChange={(event) => setRow(index, 'daily_new_closed', ceoNumberValue(event.target.value))} /></td>}
                                    {isSales && <td><input className="form-control text-right" value={row.daily_old_contacts ?? 0} onChange={(event) => setRow(index, 'daily_old_contacts', ceoNumberValue(event.target.value))} /></td>}
                                    {isSales && <td><input className="form-control text-right" value={row.daily_old_closed ?? 0} onChange={(event) => setRow(index, 'daily_old_closed', ceoNumberValue(event.target.value))} /></td>}
                                    <td><input className="form-control text-right" value={row.daily_revenue ?? 0} onChange={(event) => setRow(index, 'daily_revenue', ceoNumberValue(event.target.value))} /></td>
                                    <td className="text-center no-wrap">{ceoFormatDateTime(row.updated_at)}</td>
                                    <td className="text-center no-wrap">
                                        <button type="button" className="ps-kpi-catalog-icon" title="Xóa" onClick={() => destroyRow(row, index)}>
                                            <i className="fa fa-trash" />
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={isSales ? 10 : 9} className="text-center ps-kpi-catalog-empty">Không có dữ liệu. Bấm “Khởi tạo” để tạo danh mục KPI mặc định.</td>
                                </tr>
                            )}
                            <tr>
                                <td colSpan={isSales ? 8 : 7} className="text-left">
                                    <button type="button" className="ps-kpi-catalog-link pull-right" onClick={initializeDefaults} disabled={processing}>
                                        <i className="fa fa-refresh" /> <span className="text">Khởi tạo</span>
                                    </button>
                                </td>
                                <td className="text-center" colSpan={2}>
                                    <button type="button" className="ps-kpi-catalog-link" onClick={saveAll} disabled={processing}>
                                        <i className="fa fa-save" /> <span className="text">Lưu</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
