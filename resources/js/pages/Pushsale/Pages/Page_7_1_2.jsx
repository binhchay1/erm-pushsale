import { Head, router } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);

function nf(value, digits = 0) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? new Intl.NumberFormat('vi-VN', { maximumFractionDigits: digits }).format(number) : '0';
}

function formatMetric(value, format) {
    if (value === null || value === undefined || value === '') return '';
    if (format === 'currency') return `${nf(value)} đ`;
    if (format === 'percent') return `${nf(value, 2)} %`;
    return nf(value, 2);
}

function percent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '';
    return `${nf(value, 2)} %`;
}

function numberValue(value) {
    const number = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? number : 0;
}

function queryFilters(defaultFilters) {
    if (typeof window === 'undefined') return defaultFilters;
    const query = new URLSearchParams(window.location.search);
    const months = query.get('months');
    return {
        year: query.get('year') || defaultFilters.year,
        months: months ? months.split(',').map(Number).filter(Boolean) : defaultFilters.months,
        discount_mode: query.get('discount_mode') || defaultFilters.discount_mode,
    };
}

function buildQuery(filters) {
    return {
        year: filters.year,
        months: (filters.months || []).join(','),
        discount_mode: filters.discount_mode,
    };
}

function Chart({ chart }) {
    const categories = chart?.categories ?? [];
    const series = [
        ['Doanh số dự kiến', chart?.revenue_planned ?? [], 'plan-revenue'],
        ['Doanh số thực tế', chart?.revenue_actual ?? [], 'actual-revenue'],
        ['Lợi nhuận dự kiến', chart?.profit_planned ?? [], 'plan-profit'],
        ['Lợi nhuận thực tế', chart?.profit_actual ?? [], 'actual-profit'],
    ];
    const max = Math.max(1, ...series.flatMap(([, values]) => values.map((value) => Math.abs(Number(value) || 0))));

    return (
        <section className="ps-year-plan-chart-box">
            <h2>BIỂU ĐỒ</h2>
            <div className="ps-year-plan-chart-menu"><i className="fa fa-bars" /></div>
            <div className="ps-year-plan-axis-label">Giá trị</div>
            <div className="ps-year-plan-chart">
                {categories.map((category, index) => (
                    <div className="ps-year-plan-chart-month" key={category}>
                        <div className="ps-year-plan-chart-bars">
                            {series.map(([label, values, tone]) => {
                                const value = Number(values[index] ?? 0);
                                const height = Math.max(2, Math.round((Math.abs(value) / max) * 260));
                                return (
                                    <span
                                        key={label}
                                        className={`ps-year-plan-chart-bar ${tone}`}
                                        style={{ height: `${height}px` }}
                                        title={`${category} - ${label}: ${nf(value)}`}
                                    />
                                );
                            })}
                        </div>
                        <span className="ps-year-plan-chart-month-label">{category.replace('Tháng ', 'T')}</span>
                    </div>
                ))}
            </div>
            <div className="ps-year-plan-legend">
                {series.map(([label, , tone]) => <span key={label}><i className={tone} />{label}</span>)}
                <span className="muted"><i />Max doanh số dự kiến</span>
                <span><i className="line actual-revenue" />Max doanh số thực tế</span>
                <span className="muted"><i />Max lợi nhuận dự kiến</span>
                <span className="muted"><i />Max lợi nhuận thực tế</span>
            </div>
        </section>
    );
}

function NoteDialog({ open, onClose, note }) {
    if (!open) return null;
    return (
        <div className="modal fade modal-note in ps-year-plan-modal-backdrop" role="dialog" aria-hidden="false">
            <div className="modal-dialog modal-lg ps-year-plan-note-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <button type="button" className="close" aria-label="Close" onClick={onClose}><span aria-hidden="true">×</span></button>
                        <h4 className="modal-title">GIẢI THÍCH</h4>
                    </div>
                    <div className="modal-body">
                        <div className="table-responsive">
                            <table className="table table-bordered table-striped ps-year-plan-note-table">
                                <thead><tr><th style={{ width: 45 }}>STT</th><th>Chỉ số</th><th style={{ width: 130 }}>Ký hiệu</th></tr></thead>
                                <tbody>
                                    {(note?.metrics ?? []).map((metric, index) => (
                                        <tr key={metric.code}><td className="text-center text-bold">{index + 1}</td><td>{metric.label}</td><td className="text-center">{metric.symbol}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="table-responsive ps-year-plan-note-formula-wrap">
                            <table className="table table-bordered table-striped ps-year-plan-note-table">
                                <thead><tr><th style={{ width: '15%' }}>Chỉ số</th><th style={{ width: '15%' }}>Công thức</th><th>Mô tả</th></tr></thead>
                                <tbody>
                                    {(note?.formulas ?? []).map((formula) => (
                                        <tr key={formula.metric}><td className="text-bold">{formula.metric}</td><td className="text-center text-bold">{formula.formula}</td><td className="text-bold">{formula.description}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PlannedDataDialog({ open, filters, onClose, routeUrl }) {
    const [payload, setPayload] = useState({
        year: filters.year,
        months: filters.months?.length ? filters.months : [new Date().getMonth() + 1],
        contacts: 1800,
        close_rate: 32,
        products_per_order: 1.8,
        unit_price: 620000,
        contact_price: 45000,
        marketing_salary: 60000000,
        marketing_bonus: 30000000,
        sale_salary: 90000000,
        sale_bonus: 45000000,
        other_cost: 25000000,
        cost_of_goods_percent: 38,
    });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        setPayload((current) => ({ ...current, year: filters.year, months: filters.months?.length ? filters.months : current.months }));
    }, [open, filters.year, filters.months]);

    if (!open) return null;

    const setField = (key, value) => setPayload((current) => ({ ...current, [key]: value }));
    const toggleMonth = (month) => setPayload((current) => {
        const selected = new Set(current.months ?? []);
        if (selected.has(month)) selected.delete(month); else selected.add(month);
        return { ...current, months: [...selected].sort((a, b) => a - b) };
    });
    const submit = () => {
        setSaving(true);
        router.post(`${routeUrl}/planned-data`, payload, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="modal fade in ps-year-plan-modal-backdrop" role="dialog" aria-hidden="false">
            <div className="modal-dialog modal-lg ps-year-plan-data-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <button type="button" className="close" aria-label="Close" onClick={onClose}><span aria-hidden="true">×</span></button>
                        <h4 className="modal-title">THÊM DỮ LIỆU KẾ HOẠCH NĂM</h4>
                    </div>
                    <div className="modal-body">
                        <div className="ps-year-plan-dialog-note">
                            Nhập các chỉ số gốc, hệ thống tự tính theo công thức của màn hình: số đơn, doanh số, ngân sách, chi phí và lợi nhuận cho từng tháng được chọn.
                        </div>
                        <div className="ps-year-plan-form-grid">
                            <label><span>Năm</span><input className="form-control" value={payload.year} onChange={(e) => setField('year', numberValue(e.target.value))} /></label>
                            <div className="ps-year-plan-month-picker">
                                <span>Tháng áp dụng</span>
                                <div>{monthOptions.map((month) => <label key={month}><input type="checkbox" checked={(payload.months ?? []).includes(month)} onChange={() => toggleMonth(month)} /> Tháng {month}</label>)}</div>
                            </div>
                            <label><span>Số contact (3)</span><input className="form-control" value={payload.contacts} onChange={(e) => setField('contacts', numberValue(e.target.value))} /></label>
                            <label><span>Tỉ lệ chốt (4)</span><input className="form-control" value={payload.close_rate} onChange={(e) => setField('close_rate', numberValue(e.target.value))} /></label>
                            <label><span>Số sản phẩm/đơn (6)</span><input className="form-control" value={payload.products_per_order} onChange={(e) => setField('products_per_order', numberValue(e.target.value))} /></label>
                            <label><span>Đơn giá TB/SP (7)</span><input className="form-control" value={payload.unit_price} onChange={(e) => setField('unit_price', numberValue(e.target.value))} /></label>
                            <label><span>Giá contact (11)</span><input className="form-control" value={payload.contact_price} onChange={(e) => setField('contact_price', numberValue(e.target.value))} /></label>
                            <label><span>Lương marketing (12)</span><input className="form-control" value={payload.marketing_salary} onChange={(e) => setField('marketing_salary', numberValue(e.target.value))} /></label>
                            <label><span>Thưởng marketing (13)</span><input className="form-control" value={payload.marketing_bonus} onChange={(e) => setField('marketing_bonus', numberValue(e.target.value))} /></label>
                            <label><span>Lương sale (14)</span><input className="form-control" value={payload.sale_salary} onChange={(e) => setField('sale_salary', numberValue(e.target.value))} /></label>
                            <label><span>Thưởng sale (15)</span><input className="form-control" value={payload.sale_bonus} onChange={(e) => setField('sale_bonus', numberValue(e.target.value))} /></label>
                            <label><span>Chi phí khác (16)</span><input className="form-control" value={payload.other_cost} onChange={(e) => setField('other_cost', numberValue(e.target.value))} /></label>
                            <label><span>Giá vốn hàng hóa % (17)</span><input className="form-control" value={payload.cost_of_goods_percent} onChange={(e) => setField('cost_of_goods_percent', numberValue(e.target.value))} /></label>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                        <button type="button" className="btn btn-primary btn-sm" disabled={saving || !(payload.months ?? []).length} onClick={submit}>
                            <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? 'Đang lưu' : 'Lưu dữ liệu'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function YearlyBusinessPlanPage({ schema, rows = [], chart = {}, note = {}, summary = {}, filters: initialFilters, routeUrl = '/admin/ceo/business-plan/yearly' }) {
    const now = new Date();
    const [filters, setFilters] = useState(() => queryFilters({
        year: String(initialFilters?.year ?? now.getFullYear()),
        months: initialFilters?.months ?? monthOptions,
        discount_mode: initialFilters?.discount_mode ?? 'after_discount',
    }));
    const [showNote, setShowNote] = useState(false);
    const [showData, setShowData] = useState(false);
    const [showToast, setShowToast] = useState(Boolean(summary?.toast));

    useEffect(() => {
        setShowToast(Boolean(summary?.toast));
        if (!summary?.toast) return undefined;
        const timer = window.setTimeout(() => setShowToast(false), 3000);
        return () => window.clearTimeout(timer);
    }, [summary?.toast]);

    const years = useMemo(() => [now.getFullYear() + 1, now.getFullYear(), now.getFullYear() - 1, now.getFullYear() - 2, now.getFullYear() - 3, now.getFullYear() - 4, now.getFullYear() - 5], [now]);
    const runSearch = () => router.get(routeUrl, buildQuery(filters), { preserveScroll: false });
    const exportExcel = () => router.get(routeUrl, { ...buildQuery(filters), export: 1 }, { preserveScroll: true });
    const toggleMonth = (month) => setFilters((current) => {
        const set = new Set(current.months ?? []);
        if (set.has(month)) set.delete(month); else set.add(month);
        return { ...current, months: [...set].sort((a, b) => a - b) };
    });

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Lập kế hoạch kinh doanh'} />
            <div className="ps-year-plan-page">
                {showToast && (
                    <div className="ps-year-plan-toast ps-year-plan-toast-warning">
                        <button type="button" onClick={() => setShowToast(false)}>×</button>
                        <i className="fa fa-warning" />
                        <span>{summary.toast}</span>
                    </div>
                )}
                <div className="ps-year-plan-toolbar m-header-wrap">
                    <select className="form-control" value={filters.year} onChange={(event) => setFilters((current) => ({ ...current, year: event.target.value }))}>
                        {years.map((year) => <option key={year} value={year}>Năm {year}</option>)}
                    </select>
                    <div className="ps-year-plan-month-select">
                        <button type="button" className="form-control">{(filters.months ?? []).length === 12 ? 'Chọn tháng' : `Đã chọn ${(filters.months ?? []).length} tháng`}</button>
                        <div className="ps-year-plan-month-dropdown">
                            {monthOptions.map((month) => <label key={month}><input type="checkbox" checked={(filters.months ?? []).includes(month)} onChange={() => toggleMonth(month)} /> Tháng {month}</label>)}
                        </div>
                    </div>
                    <select className="form-control" value={filters.discount_mode} onChange={(event) => setFilters((current) => ({ ...current, discount_mode: event.target.value }))}>
                        <option value="after_discount">Sau chiết khấu</option>
                        <option value="before_discount">Trước chiết khấu</option>
                    </select>
                    <div className="ps-year-plan-actions">
                        <button type="button" className="btn btn-primary btn-sm" onClick={runSearch}><i className="fa fa-search" /> Tìm kiếm</button>
                        <button type="button" className="btn btn-primary btn-sm" onClick={exportExcel}><i className="fa fa-file-excel-o" /> Xuất Excel</button>
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowData(true)}><i className="fa fa-plus" /> Thêm dữ liệu</button>
                        <button type="button" className="ps-year-plan-note-button" title="Chú thích" onClick={() => setShowNote(true)}><i className="fa fa-question-circle" /></button>
                    </div>
                </div>

                <div className="ps-year-plan-table-wrap">
                    <table id="tblData" className="table table-bordered table-multi-select tabledata ps-year-plan-table">
                        <thead>
                            <tr>
                                <th rowSpan={2}>Tên</th>
                                <th colSpan={3}>Tổng</th>
                                {monthOptions.map((month) => <th key={month} colSpan={3}>Tháng {month}</th>)}
                            </tr>
                            <tr>
                                <th>Dự kiến</th><th>Thực tế</th><th>Tỉ lệ</th>
                                {monthOptions.map((month) => <Fragment key={`header-${month}`}><th>Dự kiến</th><th>Thực tế</th><th>Tỉ lệ</th></Fragment>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.code}>
                                    <td className="ps-year-plan-name">{row.name}</td>
                                    <td>{formatMetric(row.total?.planned, row.format)}</td>
                                    <td>{formatMetric(row.total?.actual, row.format)}</td>
                                    <td>{percent(row.total?.ratio)}</td>
                                    {monthOptions.map((month) => (
                                        <Fragment key={`${row.code}-${month}`}>
                                            <td>{formatMetric(row.months?.[month]?.planned, row.format)}</td>
                                            <td>{formatMetric(row.months?.[month]?.actual, row.format)}</td>
                                            <td>{percent(row.months?.[month]?.ratio)}</td>
                                        </Fragment>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Chart chart={chart} />
                <NoteDialog open={showNote} onClose={() => setShowNote(false)} note={note} />
                <PlannedDataDialog open={showData} onClose={() => setShowData(false)} filters={filters} routeUrl={routeUrl} />
            </div>
        </AppLayout>
    );
}
