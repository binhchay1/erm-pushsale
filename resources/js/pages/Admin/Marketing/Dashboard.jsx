import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import {
    PushsaleDateRange,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';
import { apiGet, getCsrfToken } from '@/lib/api';
import { formatNumber } from '@/lib/format';

function queryString(values = {}) {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined && value !== false) {
            params.set(key, String(value));
        }
    });
    return params.toString();
}

function percent(value, infinity = false) {
    if (value === null || value === undefined) return infinity ? '∞ %' : '0 %';
    const number = Number(value);
    if (!Number.isFinite(number)) return infinity ? '∞ %' : '0 %';
    return `${Number.isInteger(number) ? number : number.toFixed(2)} %`;
}

function money(value) {
    return formatNumber(Number(value ?? 0));
}

function Modal({ open, title, size = 'lg', onClose, children, footer }) {
    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => event.key === 'Escape' && onClose?.();
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="psm-modal-layer" role="dialog" aria-modal="true" aria-label={title}>
            <button type="button" className="psm-modal-backdrop" aria-label="Đóng" onClick={onClose} />
            <div className={`psm-modal psm-modal-${size}`}>
                <div className="psm-modal-header">
                    <h2>{title}</h2>
                    <button type="button" onClick={onClose} aria-label="Đóng"><i className="fa fa-times" /></button>
                </div>
                <div className="psm-modal-body">{children}</div>
                {footer && <div className="psm-modal-footer">{footer}</div>}
            </div>
        </div>
    );
}

function LoadingBlock({ label = 'Đang tải dữ liệu…' }) {
    return <div className="psm-loading"><i className="fa fa-spinner fa-spin" /> {label}</div>;
}

function ErrorBlock({ message }) {
    return <div className="alert alert-danger psm-alert"><i className="fa fa-warning" /> {message}</div>;
}

function MetricFill({ value, max, tone }) {
    const width = max > 0 ? Math.min(100, Math.max(0, (Number(value ?? 0) / max) * 100)) : 0;
    return (
        <span className={`psm-metric-fill is-${tone}`}>
            <span className="psm-metric-bar" style={{ width: `${width}%` }} />
            <b>{money(value)}</b>
        </span>
    );
}

function ChartGraphic({ days = [] }) {
    const width = 920;
    const height = 330;
    const padding = { left: 50, right: 28, top: 30, bottom: 55 };
    const graphWidth = width - padding.left - padding.right;
    const graphHeight = height - padding.top - padding.bottom;
    const maxContacts = Math.max(1, ...days.map((day) => Number(day.contacts ?? 0)));
    const maxRevenue = Math.max(1, ...days.map((day) => Number(day.revenue ?? 0)));
    const x = (index) => padding.left + (days.length <= 1 ? graphWidth / 2 : (index / (days.length - 1)) * graphWidth);
    const yContacts = (value) => padding.top + graphHeight - (Number(value ?? 0) / maxContacts) * graphHeight;
    const yRevenue = (value) => padding.top + graphHeight - (Number(value ?? 0) / maxRevenue) * graphHeight;
    const contactPoints = days.map((day, index) => `${x(index)},${yContacts(day.contacts)}`).join(' ');
    const revenuePoints = days.map((day, index) => `${x(index)},${yRevenue(day.revenue)}`).join(' ');

    if (!days.length) return <div className="psm-chart-empty">Không có dữ liệu trong khoảng ngày đã chọn.</div>;

    return (
        <div className="psm-chart-wrap">
            <div className="psm-chart-legend">
                <span><i className="is-contact" /> Contact</span>
                <span><i className="is-revenue" /> Doanh số</span>
            </div>
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Biểu đồ hiệu quả marketing">
                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                    const y = padding.top + graphHeight * ratio;
                    return <line key={ratio} x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="psm-chart-grid" />;
                })}
                <line x1={padding.left} y1={padding.top} x2={padding.left} y2={padding.top + graphHeight} className="psm-chart-axis" />
                <line x1={padding.left} y1={padding.top + graphHeight} x2={width - padding.right} y2={padding.top + graphHeight} className="psm-chart-axis" />
                <polyline points={contactPoints} className="psm-chart-line is-contact" />
                <polyline points={revenuePoints} className="psm-chart-line is-revenue" />
                {days.map((day, index) => (
                    <g key={day.date}>
                        <circle cx={x(index)} cy={yContacts(day.contacts)} r="4" className="psm-chart-point is-contact" />
                        <circle cx={x(index)} cy={yRevenue(day.revenue)} r="4" className="psm-chart-point is-revenue" />
                        <text x={x(index)} y={height - 25} textAnchor="middle" className="psm-chart-label">{day.label}</text>
                    </g>
                ))}
            </svg>
            <div className="psm-chart-summary">
                <span>Ngân sách: <b>{money(days.reduce((sum, day) => sum + Number(day.budget ?? 0), 0))}</b></span>
                <span>Tương tác: <b>{money(days.reduce((sum, day) => sum + Number(day.clicks ?? 0), 0))}</b></span>
                <span>Contact: <b>{money(days.reduce((sum, day) => sum + Number(day.contacts ?? 0), 0))}</b></span>
                <span>Doanh số: <b>{money(days.reduce((sum, day) => sum + Number(day.revenue ?? 0), 0))}</b></span>
            </div>
        </div>
    );
}

function ChartModal({ state, endpoint, filters, onClose }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [data, setData] = useState(null);

    useEffect(() => {
        if (!state?.row || !endpoint) return;
        let alive = true;
        setLoading(true);
        setError('');
        const url = `${endpoint}?${queryString({
            ...filters,
            source_id: state.row.sourceId,
            utm_source: state.row.utmSource,
            utm_campaign: state.row.utmCampaign,
        })}`;
        apiGet(url)
            .then((result) => alive && setData(result))
            .catch((exception) => alive && setError(exception.message))
            .finally(() => alive && setLoading(false));
        return () => { alive = false; };
    }, [state, endpoint, filters]);

    return (
        <Modal open={Boolean(state)} title="BIỂU ĐỒ DỮ LIỆU" size="full" onClose={onClose}>
            {loading && <LoadingBlock />}
            {error && <ErrorBlock message={error} />}
            {!loading && !error && data && (
                <>
                    <div className="psm-chart-title">
                        <b>{data.title}</b>
                        <span>{data.source?.name}{data.utm_source ? ` / ${data.utm_source}` : ''}{data.utm_campaign ? ` / ${data.utm_campaign}` : ''}</span>
                    </div>
                    <ChartGraphic days={data.days ?? []} />
                </>
            )}
        </Modal>
    );
}

function DailyMetricsModal({ state, endpoint, filters, canEdit, onClose, onSaved }) {
    const [range, setRange] = useState({ date_from: filters.date_from, date_to: filters.date_to });
    const [rows, setRows] = useState([]);
    const [source, setSource] = useState(null);
    const [loading, setLoading] = useState(false);
    const [savingKey, setSavingKey] = useState(null);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const load = async () => {
        if (!state?.row || !endpoint) return;
        setLoading(true);
        setError('');
        setMessage('');
        try {
            const result = await apiGet(`${endpoint}?${queryString({
                source_id: state.row.sourceId,
                date_from: range.date_from,
                date_to: range.date_to,
                utm_source: state.row.utmSource,
                utm_campaign: state.row.utmCampaign,
            })}`);
            setSource(result.source);
            setRows(result.rows ?? []);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!state) return;
        setRange({ date_from: filters.date_from, date_to: filters.date_to });
    }, [state, filters.date_from, filters.date_to]);

    useEffect(() => {
        if (state) load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [state]);

    const updateRow = (index, key, value) => {
        setRows((current) => current.map((row, rowIndex) => (
            rowIndex === index ? { ...row, [key]: Math.max(0, Number(value || 0)) } : row
        )));
    };

    const save = async (payloadRows = rows, key = 'all') => {
        if (!canEdit || !endpoint || !payloadRows.length) return;
        setSavingKey(key);
        setError('');
        setMessage('');
        try {
            const response = await fetch(endpoint, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ source_id: state.row.sourceId, rows: payloadRows }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const first = Object.values(result.errors ?? {}).flat()[0];
                throw new Error(first ?? result.message ?? 'Không thể lưu dữ liệu.');
            }
            setMessage(result.message ?? 'Đã lưu dữ liệu.');
            onSaved?.();
            await load();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setSavingKey(null);
        }
    };

    return (
        <Modal
            open={Boolean(state)}
            title="THÊM DỮ LIỆU CHO LANDING THEO NGÀY"
            size="full"
            onClose={onClose}
            footer={(
                <>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}><i className="fa fa-times" /> Đóng</button>
                    {canEdit && <button type="button" className="btn btn-primary btn-sm" disabled={Boolean(savingKey) || loading} onClick={() => save(rows, 'all')}><i className={`fa ${savingKey === 'all' ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu</button>}
                </>
            )}
        >
            <div className="psm-daily-toolbar">
                <div className="psm-daily-source"><b>Thêm dữ liệu nguồn dữ liệu</b><small>{source?.name ?? state?.row?.sourceName ?? '—'}</small></div>
                <label>Từ ngày<input type="date" value={range.date_from ?? ''} onChange={(event) => setRange((current) => ({ ...current, date_from: event.target.value }))} /></label>
                <label>Đến ngày<input type="date" value={range.date_to ?? ''} onChange={(event) => setRange((current) => ({ ...current, date_to: event.target.value }))} /></label>
                <button type="button" className="btn btn-primary btn-sm" onClick={load}><i className="fa fa-search" /> Tìm kiếm</button>
            </div>
            <div className="psm-daily-legend">
                <span className="is-ready">Ngân sách và số click &gt; 0</span>
                <span className="is-zero">Ngân sách hoặc số click = 0</span>
                <span className="is-missing">Chưa tạo dữ liệu</span>
            </div>
            {error && <ErrorBlock message={error} />}
            {message && <div className="alert alert-success psm-alert"><i className="fa fa-check" /> {message}</div>}
            {loading ? <LoadingBlock /> : (
                <div className="table-responsive psm-daily-table-wrap">
                    <table className="table table-bordered table-striped psm-daily-table">
                        <thead><tr><th>STT</th><th>Sản phẩm</th><th>Nguồn dữ liệu</th><th>Kênh quảng cáo</th><th>Ngân sách</th><th>Số click</th><th>Dữ liệu ngày</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            {!rows.length && <tr><td colSpan="9" className="text-center">Không có ngày trong khoảng đã chọn.</td></tr>}
                            {rows.map((row, index) => (
                                <tr key={row.metric_date} className={`psm-daily-row is-${row.status}`}>
                                    <td>{index + 1}</td>
                                    <td>{row.product_name}</td>
                                    <td>{row.source_name}{row.utm_source ? <small><br />UTM: {row.utm_source}{row.utm_campaign ? ` / ${row.utm_campaign}` : ''}</small> : null}</td>
                                    <td>{row.ad_channel}</td>
                                    <td><input type="number" min="0" disabled={!canEdit} value={row.budget ?? 0} onChange={(event) => updateRow(index, 'budget', event.target.value)} /></td>
                                    <td><input type="number" min="0" disabled={!canEdit} value={row.clicks ?? 0} onChange={(event) => updateRow(index, 'clicks', event.target.value)} /></td>
                                    <td>{row.display_date}</td>
                                    <td className="psm-daily-updated">{row.updated_at ? <><b>{row.updated_at}</b><small>{row.updated_by || ''}</small></> : '—'}</td>
                                    <td>
                                        {canEdit ? (
                                            <button type="button" className="btn btn-primary btn-xs" disabled={Boolean(savingKey)} onClick={() => save([row], row.metric_date)}>
                                                <i className={`fa ${savingKey === row.metric_date ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu
                                            </button>
                                        ) : <span className={`psm-daily-status is-${row.status}`}>{row.status === 'ready' ? 'Đã nhập' : row.status === 'zero' ? 'Bằng 0' : 'Chưa nhập'}</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Modal>
    );
}

function HelpModal({ open, onClose }) {
    return (
        <Modal open={open} title="HƯỚNG DẪN MARKETING DASHBOARD" size="md" onClose={onClose}>
            <div className="psm-help">
                <p><b>Ngân sách và số tương tác</b> được nhập theo ngày bằng nút dấu cộng tại từng nguồn/UTM.</p>
                <p><b>Contact</b> lấy từ lead hợp lệ của nguồn dữ liệu trong khoảng lọc. <b>Chốt đơn và doanh số</b> lấy từ đơn đã chốt thực tế.</p>
                <p>Bấm mũi tên tại cột sản phẩm để mở chi tiết UTM Source và UTM Campaign; bấm biểu đồ để xem biến động theo ngày.</p>
            </div>
        </Modal>
    );
}

function TotalRow({ label, total = {}, advancedUtm = false }) {
    return (
        <tr className="psm-total-row">
            <td colSpan={advancedUtm ? 9 : 6}>{label}:</td>
            <td>{money(total.budget)}</td>
            <td>{money(total.interactions)}</td>
            <td>{money(total.contacts)}</td>
            <td>{percent(total.contactRate, true)}</td>
            <td>{money(total.costPerContact)}</td>
            <td>{money(total.closedOrders)}</td>
            <td>{percent(total.closingRate)}</td>
            <td>{money(total.productQuantity)}</td>
            <td>{Number(total.avgProductPerOrder ?? 0).toFixed(2).replace(/\.00$/, '')}</td>
            <td>{money(total.totalRevenue)}</td>
            <td>{money(total.revenueAfterDiscount)}</td>
            <td>{percent(total.budgetRevenueRatio)}</td>
            <td>{percent(total.budgetNetRevenueRatio)}</td>
            <td />
        </tr>
    );
}

function DashboardTable({ report, expanded, onToggle, onChart, onDaily, advancedUtm = false }) {
    const rows = report.rows ?? [];
    const visibleRows = rows.filter((row) => {
        if (row.level === 1) return true;
        if (row.level === 2) return expanded.has(row.parentKey);
        return expanded.has(row.parentKey) && expanded.has(row.parentKey.split('-u')[0]);
    });
    const maxRevenue = Math.max(1, ...visibleRows.map((row) => Number(row.totalRevenue ?? 0)));
    const maxNetRevenue = Math.max(1, ...visibleRows.map((row) => Number(row.revenueAfterDiscount ?? 0)));
    let rootIndex = 0;

    return (
        <div className="psm-table-scroll">
            <table className={`psm-dashboard-table ${advancedUtm ? 'is-advanced' : ''}`}>
                <thead>
                    <tr className="psm-head-group"><th colSpan={advancedUtm ? 9 : 6}>THÔNG TIN NGUỒN DỮ LIỆU</th><th colSpan="14">THÔNG TIN HIỆU QUẢ MARKETING</th></tr>
                    <tr>
                        <th>STT</th><th>Tên Nguồn dữ liệu</th><th>Sản phẩm</th><th>Kênh quảng cáo</th><th>UTM Source</th><th>UTM<br />Campaign</th>
                        {advancedUtm && <><th>UTM<br />Medium</th><th>UTM<br />Term</th><th>UTM<br />Content</th></>}
                        <th>Ngân sách (1)</th><th>Số tương tác<br />(2)</th><th>Số contact<br />(3)</th><th>Tỷ lệ<br />contact/tương tác<br />(4=3/2) (%)</th>
                        <th>Giá contact<br />(5=1/3)</th><th>Chốt đơn<br />(6)</th><th>Tỷ lệ chốt đơn<br />(7=6/3) (%)</th><th>Số sản phẩm<br />(8)</th>
                        <th>Sản phẩm/đơn<br />(9=8/6)</th><th>Doanh số<br />(10)</th><th>Doanh số sau CK<br />(11)</th><th>NS/Doanh số<br />(12=1/10) (%)</th>
                        <th>NS/Doanh số trừ CK<br />(13=1/11) (%)</th><th>Biểu đồ</th>
                    </tr>
                </thead>
                <tbody>
                    <TotalRow label="Tổng bộ lọc" total={report.filterTotal} advancedUtm={advancedUtm} />
                    <TotalRow label="Tổng theo trang" total={report.pageTotal} advancedUtm={advancedUtm} />
                    {!visibleRows.length && <tr><td colSpan={advancedUtm ? 23 : 20} className="psm-empty">Không có dữ liệu trong khoảng thời gian đã chọn.</td></tr>}
                    {visibleRows.map((row) => {
                        if (row.level === 1) rootIndex += 1;
                        const isExpanded = expanded.has(row.rowKey);
                        return (
                            <tr key={row.rowKey} className={`psm-level-${row.level}`}>
                                <td>{row.level === 1 ? rootIndex : ''}</td>
                                <td className="psm-source-cell">
                                    <span className="psm-indent" style={{ width: `${(row.level - 1) * 18}px` }} />
                                    {row.level <= 2 && (
                                        <button type="button" className="psm-add-button" title="Thêm dữ liệu theo ngày" onClick={() => onDaily(row)}><i className="fa fa-plus" /></button>
                                    )}
                                    <span>{row.sourceName || (row.level === 2 ? 'Chi tiết UTM' : '')}</span>
                                </td>
                                <td className="psm-product-cell">
                                    <span>{row.productName}</span>
                                    {row.hasChildren && (
                                        <button type="button" className="psm-expand-button" onClick={() => onToggle(row.rowKey)} title={isExpanded ? 'Thu gọn' : 'Mở rộng'}>
                                            <i className={`fa fa-angle-${isExpanded ? 'up' : 'down'}`} />
                                        </button>
                                    )}
                                </td>
                                <td>{row.adChannel}</td>
                                <td className="psm-utm-cell">{row.utmSource}</td>
                                <td className="psm-utm-cell">{row.utmCampaign}</td>
                                {advancedUtm && <><td className="psm-utm-cell">{row.utmMedium}</td><td className="psm-utm-cell">{row.utmTerm}</td><td className="psm-utm-cell">{row.utmContent}</td></>}
                                <td>{money(row.budget)}</td><td>{money(row.interactions)}</td><td>{money(row.contacts)}</td><td>{percent(row.contactRate, true)}</td>
                                <td>{money(row.costPerContact)}</td><td>{money(row.closedOrders)}</td><td className="psm-rate-cell"><span style={{ width: `${Math.min(100, Number(row.closingRate ?? 0))}%` }} />{percent(row.closingRate)}</td>
                                <td>{money(row.productQuantity)}</td><td>{Number(row.avgProductPerOrder ?? 0).toFixed(2).replace(/\.00$/, '')}</td>
                                <td><MetricFill value={row.totalRevenue} max={maxRevenue} tone="green" /></td>
                                <td><MetricFill value={row.revenueAfterDiscount} max={maxNetRevenue} tone="blue" /></td>
                                <td>{percent(row.budgetRevenueRatio)}</td><td>{percent(row.budgetNetRevenueRatio)}</td>
                                <td><button type="button" className="psm-chart-button" title="Xem biểu đồ" onClick={() => onChart(row)}><i className="fa fa-bar-chart" /></button></td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

export default function Dashboard({ filters = {}, filterOptions = {}, report = {}, filterRouteUrl, endpoints = {}, activeMenuCode = '2.1' }) {
    const routeUrl = filterRouteUrl ?? '/admin/marketing/dashboard';
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [collapsed, setCollapsed] = useState(false);
    const [gearOpen, setGearOpen] = useState(false);
    const [expanded, setExpanded] = useState(new Set());
    const [chartState, setChartState] = useState(null);
    const [dailyState, setDailyState] = useState(null);
    const [helpOpen, setHelpOpen] = useState(false);
    const gearRef = useRef(null);

    useEffect(() => {
        const close = (event) => {
            if (gearRef.current && !gearRef.current.contains(event.target)) setGearOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const teams = filterOptions.teams ?? [];
    const marketers = filterOptions.marketingUsers ?? [];
    const selectedTeam = Number(draft.team_id || 0);
    const selectedLeader = Number(draft.team_leader_id || 0);
    const visibleTeams = selectedLeader ? teams.filter((team) => Number(team.leader_user_id) === selectedLeader) : teams;
    const visibleMarketers = marketers.filter((user) => (
        (!selectedTeam || Number(user.team_id) === selectedTeam)
        && (!selectedLeader || Number(user.manager_user_id) === selectedLeader || Number(user.id) === selectedLeader)
    ));
    const selectedParent = Number(draft.parent_product_id || 0);
    const products = (filterOptions.products ?? []).filter((product) => !selectedParent || Number(product.parent_id) === selectedParent || Number(product.id) === selectedParent);
    const pagination = report.pagination ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 10 };

    const changePage = (page) => apply({ page });
    const changePerPage = (value) => apply({ page: 1, per_page: value });
    const toggle = (key) => setExpanded((current) => {
        const next = new Set(current);
        if (next.has(key)) next.delete(key); else next.add(key);
        return next;
    });
    const exportHref = `${endpoints.export ?? `${routeUrl}/export`}?${queryString(draft)}`;

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Marketing dashboard" />
            <section className="psm-page">
                <div className="psm-topbar">
                    <h1>Marketing dashboard</h1>
                    <div className="psm-top-selects">
                        <PushsaleSelect value={draft.team_leader_id ?? ''} onChange={(value) => { set('team_leader_id', value); set('team_id', ''); set('marketer_id', ''); }} options={filterOptions.teamLeaders ?? []} placeholder="--Chọn trưởng nhóm--" />
                        <PushsaleSelect value={draft.team_id ?? ''} onChange={(value) => { set('team_id', value); set('marketer_id', ''); }} options={visibleTeams} placeholder="--Chọn nhóm--" />
                        <PushsaleSelect value={draft.marketer_id ?? ''} onChange={(value) => set('marketer_id', value)} options={visibleMarketers} placeholder="--Marketing--" />
                    </div>
                    <div className="psm-top-actions">
                        <button type="button" className="psm-collapse" onClick={() => setCollapsed((value) => !value)} title="Thu gọn"><i className={`fa fa-angle-double-${collapsed ? 'down' : 'up'}`} /></button>
                        <PushsaleSearchButton onClick={() => apply()} />
                        <div className="psm-gear" ref={gearRef}>
                            <button type="button" className="psm-square-button" onClick={() => setGearOpen((value) => !value)} title="Cấu hình"><i className="fa fa-cog" /></button>
                            {gearOpen && (
                                <div className="psm-gear-menu">
                                    <a href={exportHref}><i className="fa fa-file-excel-o" /> Xuất Excel</a>
                                    {endpoints.operationConfig && <Link href={endpoints.operationConfig}><i className="fa fa-sliders" /> Cấu hình tác nghiệp loại trừ</Link>}
                                </div>
                            )}
                        </div>
                        <button type="button" className="psm-help-button" onClick={() => setHelpOpen(true)} title="Hướng dẫn"><i className="fa fa-question-circle" /></button>
                    </div>
                </div>

                {!collapsed && (
                    <div className="psm-filter-panel">
                        <div className="psm-filter-grid is-first">
                            <PushsaleSelect value={draft.date_type ?? ''} onChange={(value) => set('date_type', value)} options={filterOptions.dateTypes ?? []} placeholder="--Chuẩn Pushsale--" />
                            <PushsaleDateRange filters={draft} onChange={set} />
                            <PushsaleSelect value={draft.operation_scope ?? ''} onChange={(value) => set('operation_scope', value)} options={filterOptions.operationScopes ?? []} placeholder="Tác nghiệp cần" />
                            <PushsaleSelect value={draft.customer_type ?? ''} onChange={(value) => set('customer_type', value)} options={filterOptions.customerTypes ?? []} placeholder="Khách mới" />
                            <PushsaleSelect value={draft.contact_mode ?? ''} onChange={(value) => set('contact_mode', value)} options={filterOptions.contactModes ?? []} placeholder="Có Contact (Hoặc chốt đơn)" />
                            <PushsaleSelect value={draft.source_type ?? ''} onChange={(value) => set('source_type', value)} options={filterOptions.sourceTypes ?? []} placeholder="--Nguồn dữ liệu--" />
                            <PushsaleSelect value={draft.ad_channel ?? ''} onChange={(value) => set('ad_channel', value)} options={filterOptions.adChannels ?? []} placeholder="--Kênh quảng cáo--" />
                        </div>
                        <div className="psm-filter-grid is-second">
                            <PushsaleSelect value={draft.parent_product_id ?? ''} onChange={(value) => { set('parent_product_id', value); set('product_id', ''); }} options={filterOptions.parentProducts ?? []} placeholder="--Sản phẩm cha--" />
                            <PushsaleSelect value={draft.product_id ?? ''} onChange={(value) => set('product_id', value)} options={products} placeholder="-- Sản phẩm --" />
                            <input className="ps-control" value={draft.utm_keyword ?? ''} onChange={(event) => set('utm_keyword', event.target.value)} placeholder="Mã Utm" />
                            <input className="ps-control" value={draft.source_keyword ?? ''} onChange={(event) => set('source_keyword', event.target.value)} placeholder="Tên nguồn dữ liệu" />
                            <PushsaleSelect value={draft.sort_by ?? ''} onChange={(value) => set('sort_by', value)} options={filterOptions.sortOptions ?? []} placeholder="Số contact" />
                            <PushsaleSelect value={draft.revenue_mode ?? ''} onChange={(value) => set('revenue_mode', value)} options={filterOptions.revenueModes ?? []} placeholder="1.Doanh số tổng" />
                            <label className="psm-utm-check"><input type="checkbox" checked={Boolean(draft.advanced_utm)} onChange={(event) => set('advanced_utm', event.target.checked ? 1 : 0)} /> UTM Nâng cao</label>
                            <Link className="psm-history-link" href={endpoints.activityHistory ?? '/notifications'}><i className="fa fa-history" /> Lịch sử hoạt động</Link>
                        </div>
                    </div>
                )}

                <div className="psm-table-area">
                    <DashboardTable report={report} expanded={expanded} advancedUtm={Boolean(draft.advanced_utm)} onToggle={toggle} onChart={(row) => setChartState({ row })} onDaily={(row) => setDailyState({ row })} />
                    <div className="psm-pagination-row">
                        <div>Hiển thị {pagination.from ?? 0} - {pagination.to ?? 0} / {pagination.total ?? 0} nguồn dữ liệu</div>
                        <PushsalePager current={pagination.current_page} totalPages={pagination.last_page} onPage={changePage} />
                        <label>Hiển thị <select value={pagination.per_page ?? 10} onChange={(event) => changePerPage(event.target.value)}><option value="10">10</option><option value="20">20</option><option value="50">50</option><option value="100">100</option></select> dòng</label>
                    </div>
                </div>
            </section>

            <ChartModal state={chartState} endpoint={endpoints.chart} filters={draft} onClose={() => setChartState(null)} />
            <DailyMetricsModal state={dailyState} endpoint={endpoints.dailyMetrics} filters={draft} canEdit={Boolean(filterOptions.canEditDailyMetrics)} onClose={() => setDailyState(null)} onSaved={() => router.reload({ only: ['report'] })} />
            <HelpModal open={helpOpen} onClose={() => setHelpOpen(false)} />
        </AppLayout>
    );
}
