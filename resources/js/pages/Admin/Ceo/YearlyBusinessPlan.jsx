import { Head, router } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { CeoPlanToolbar, ceoMonthOptions, ceoNumberValue } from '@/components/ceo/CeoPlanToolbar';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

const monthOptions = ceoMonthOptions();

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
    const t = useT();
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
            onSuccess: () => {
                toast.success(t('ceo.save_data'));
                onClose();
            },
            onError: (errors) => {
                const message = Object.values(errors || {}).flat().join(' ')
                    || t('ceo.save_failed');
                toast.error(message);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="modal fade in ps-year-plan-modal-backdrop" role="dialog" aria-hidden="false">
            <div className="modal-dialog modal-lg ps-year-plan-data-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <button type="button" className="close" aria-label={t('ceo.close')} onClick={onClose}><span aria-hidden="true">×</span></button>
                        <h4 className="modal-title">{t('ceo.dialog_title')}</h4>
                    </div>
                    <div className="modal-body">
                        <div className="ps-year-plan-dialog-note">
                            {t('ceo.dialog_note')}
                        </div>
                        <div className="ps-year-plan-form-grid">
                            <label><span>{t('ceo.year')}</span><input className="form-control" value={payload.year} onChange={(e) => setField('year', ceoNumberValue(e.target.value))} /></label>
                            <div className="ps-year-plan-month-picker">
                                <span>{t('ceo.apply_months')}</span>
                                <div>{monthOptions.map((month) => <label key={month}><input type="checkbox" checked={(payload.months ?? []).includes(month)} onChange={() => toggleMonth(month)} /> {t('ceo.month')} {month}</label>)}</div>
                            </div>
                            <label><span>Số contact (3)</span><input className="form-control" value={payload.contacts} onChange={(e) => setField('contacts', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Tỉ lệ chốt (4)</span><input className="form-control" value={payload.close_rate} onChange={(e) => setField('close_rate', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Số sản phẩm/đơn (6)</span><input className="form-control" value={payload.products_per_order} onChange={(e) => setField('products_per_order', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Đơn giá TB/SP (7)</span><input className="form-control" value={payload.unit_price} onChange={(e) => setField('unit_price', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Giá contact (11)</span><input className="form-control" value={payload.contact_price} onChange={(e) => setField('contact_price', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Lương marketing (12)</span><input className="form-control" value={payload.marketing_salary} onChange={(e) => setField('marketing_salary', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Thưởng marketing (13)</span><input className="form-control" value={payload.marketing_bonus} onChange={(e) => setField('marketing_bonus', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Lương sale (14)</span><input className="form-control" value={payload.sale_salary} onChange={(e) => setField('sale_salary', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Thưởng sale (15)</span><input className="form-control" value={payload.sale_bonus} onChange={(e) => setField('sale_bonus', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Chi phí khác (16)</span><input className="form-control" value={payload.other_cost} onChange={(e) => setField('other_cost', ceoNumberValue(e.target.value))} /></label>
                            <label><span>Giá vốn hàng hóa % (17)</span><input className="form-control" value={payload.cost_of_goods_percent} onChange={(e) => setField('cost_of_goods_percent', ceoNumberValue(e.target.value))} /></label>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={onClose}>{t('ceo.close')}</button>
                        <button type="button" className="btn btn-primary btn-sm" disabled={saving || !(payload.months ?? []).length} onClick={submit}>
                            <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? t('ceo.saving') : t('ceo.save_data')}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function YearlyBusinessPlanPage({ schema, rows = [], chart = {}, note = {}, summary = {}, filters: initialFilters, routeUrl = '/admin/ceo/business-plan/yearly' }) {
    const t = useT();
    const now = new Date();
    const [filters, setFilters] = useState(() => queryFilters({
        year: String(initialFilters?.year ?? now.getFullYear()),
        months: initialFilters?.months ?? monthOptions,
        discount_mode: initialFilters?.discount_mode ?? 'after_discount',
    }));
    const [showNote, setShowNote] = useState(false);
    const [showData, setShowData] = useState(false);
    const [showToast, setShowToast] = useState(Boolean(summary?.toast));
    const [monthsOpen, setMonthsOpen] = useState(false);

    useEffect(() => {
        setShowToast(Boolean(summary?.toast));
        if (!summary?.toast) return undefined;
        const timer = window.setTimeout(() => setShowToast(false), 3000);
        return () => window.clearTimeout(timer);
    }, [summary?.toast]);

    useEffect(() => {
        setFilters(queryFilters({
            year: String(initialFilters?.year ?? now.getFullYear()),
            months: initialFilters?.months ?? monthOptions,
            discount_mode: initialFilters?.discount_mode ?? 'after_discount',
        }));
    }, [initialFilters?.year, initialFilters?.discount_mode, initialFilters?.months]);

    useEffect(() => {
        if (!monthsOpen) return undefined;
        const onDocClick = (event) => {
            if (!event.target?.closest?.('.ps-year-plan-month-select')) {
                setMonthsOpen(false);
            }
        };
        document.addEventListener('click', onDocClick);
        return () => document.removeEventListener('click', onDocClick);
    }, [monthsOpen]);

    const years = useMemo(() => [now.getFullYear() + 1, now.getFullYear(), now.getFullYear() - 1, now.getFullYear() - 2, now.getFullYear() - 3, now.getFullYear() - 4, now.getFullYear() - 5], [now]);
    const visibleMonths = useMemo(() => {
        const selected = (filters.months ?? []).map(Number).filter((month) => month >= 1 && month <= 12);
        return selected.length ? selected : monthOptions;
    }, [filters.months]);
    const runSearch = () => {
        setMonthsOpen(false);
        router.get(routeUrl, buildQuery(filters), { preserveScroll: false });
    };
    const toggleMonth = (month) => setFilters((current) => {
        const set = new Set(current.months ?? []);
        if (set.has(month)) set.delete(month); else set.add(month);
        return { ...current, months: [...set].sort((a, b) => a - b) };
    });
    const toastClass = summary?.toast_type === 'error' ? 'ps-year-plan-toast-danger' : 'ps-year-plan-toast-warning';

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Lập kế hoạch kinh doanh'} />
            <div className="ps-year-plan-page" data-page-code="7.1.2">
                {showToast && (
                    <div className={`ps-year-plan-toast ${toastClass}`}>
                        <button type="button" onClick={() => setShowToast(false)}>×</button>
                        <i className={`fa ${summary?.toast_type === 'error' ? 'fa-exclamation-circle' : 'fa-warning'}`} />
                        <span>{summary.toast}</span>
                    </div>
                )}
                <CeoPlanToolbar
                    title={schema?.title ?? 'Lập kế hoạch kinh doanh'}
                    pageCode="7.1.2"
                    className="ps-year-plan-header"
                    filtersSlot={(
                        <>
                            <select className="form-control" value={filters.year} onChange={(event) => setFilters((current) => ({ ...current, year: event.target.value }))}>
                                {years.map((year) => <option key={year} value={year}>{t('ceo.year')} {year}</option>)}
                            </select>
                            <div className={`ps-year-plan-month-select${monthsOpen ? ' is-open' : ''}`}>
                                <button
                                    type="button"
                                    className="form-control"
                                    aria-expanded={monthsOpen}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setMonthsOpen((open) => !open);
                                    }}
                                >
                                    {(filters.months ?? []).length === 12 || !(filters.months ?? []).length
                                        ? t('ceo.select_months')
                                        : t('ceo.months_selected', { count: (filters.months ?? []).length })}
                                </button>
                                <div className="ps-year-plan-month-dropdown">
                                    {monthOptions.map((month) => (
                                        <label key={month}>
                                            <input
                                                type="checkbox"
                                                checked={(filters.months ?? []).map(Number).includes(month)}
                                                onChange={() => toggleMonth(month)}
                                            />
                                            {' '}
                                            {t('ceo.month')} {month}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <select className="form-control" value={filters.discount_mode} onChange={(event) => setFilters((current) => ({ ...current, discount_mode: event.target.value }))}>
                                <option value="after_discount">{t('ceo.after_discount')}</option>
                                <option value="before_discount">{t('ceo.before_discount')}</option>
                            </select>
                        </>
                    )}
                    onSearch={runSearch}
                    routeUrl={routeUrl}
                    exportFilters={buildQuery(filters)}
                    actionsExtra={(
                        <div className="ps-year-plan-actions">
                            <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowData(true)}>
                                <i className="fa fa-plus" /> {t('ceo.add_data')}
                            </button>
                        </div>
                    )}
                />

                <button type="button" className="ps-year-plan-guide-link" onClick={() => setShowNote(true)}>
                    <i className="fa fa-book" aria-hidden="true" />
                    {t('ceo.guide_link')}
                </button>

                <div className="ps-year-plan-table-wrap">
                    <table id="tblData" className="table table-bordered table-multi-select tabledata ps-year-plan-table">
                        <thead>
                            <tr>
                                <th rowSpan={2}>{t('ceo.col_name')}</th>
                                <th colSpan={3}>{t('ceo.col_total')}</th>
                                {visibleMonths.map((month) => <th key={month} colSpan={3}>{t('ceo.month')} {month}</th>)}
                            </tr>
                            <tr>
                                <th>{t('ceo.col_planned')}</th><th>{t('ceo.col_actual')}</th><th>{t('ceo.col_ratio')}</th>
                                {visibleMonths.map((month) => <Fragment key={`header-${month}`}><th>{t('ceo.col_planned')}</th><th>{t('ceo.col_actual')}</th><th>{t('ceo.col_ratio')}</th></Fragment>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.code}>
                                    <td className="ps-year-plan-name">{row.name}</td>
                                    <td>{formatMetric(row.total?.planned, row.format)}</td>
                                    <td>{formatMetric(row.total?.actual, row.format)}</td>
                                    <td>{percent(row.total?.ratio)}</td>
                                    {visibleMonths.map((month) => (
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

