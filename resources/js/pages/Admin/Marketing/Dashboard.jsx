import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import {
    PushsaleDateRange,
    PushsaleSearchButton,
    PushsaleSelect,
    useInertiaFilters,
} from '@/components/reports/PushsaleReportChrome';
import { ProductSearchSelect } from '@/components/filters/ProductSearchSelect';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { apiGet } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

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

function reconciliationCount(item = {}) {
    const explicit = Number(item.reconciliationPackets ?? NaN);
    if (Number.isFinite(explicit)) return Math.max(0, explicit);

    return Math.max(0, Number(item.contacts ?? 0) - Number(item.validContacts ?? 0) - Number(item.duplicatePackets ?? 0));
}

function ContactBreakdown({ item = {} }) {
    return (
        <div className="psm-contact-breakdown is-total-only" title="Chi tiết gói tin nằm trong biểu đồ và danh sách gói tin">
            <b>{money(item.contacts)}</b>
        </div>
    );
}

function DialogShell({ open, title, size = 'lg', onClose, children, footer }) {
    const widths = { md: '650px', lg: '980px', xl: '1280px', wide: 'min(1540px, calc(100vw - 32px))', full: 'min(1760px, calc(100vw - 16px))' };

    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onClose?.()}
            title={title}
            width={widths[size] ?? widths.lg}
            className={`psm-dialog psm-dialog-${size}`}
            bodyClassName="psm-dialog-body"
            footerClassName="psm-dialog-footer"
            footer={footer}
        >
            {children}
        </PushsaleDialog>
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
                <span><i className="is-contact" /> Gói tin</span>
                <span><i className="is-revenue" /> Doanh số</span>
            </div>
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Biểu đồ gói tin marketing">
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
                <span>Tổng gói tin: <b>{money(days.reduce((sum, day) => sum + Number(day.contacts ?? 0), 0))}</b></span>
                <span>Chính: <b>{money(days.reduce((sum, day) => sum + Number(day.baseContacts ?? 0), 0))}</b></span>
                <span>Upsale: <b>{money(days.reduce((sum, day) => sum + Number(day.upsaleContacts ?? 0), 0))}</b></span>
                <span>Đã xử lý hợp lệ: <b>{money(days.reduce((sum, day) => sum + Number(day.validContacts ?? 0), 0))}</b></span>
                <span>Gửi trùng: <b>{money(days.reduce((sum, day) => sum + Number(day.duplicatePackets ?? 0), 0))}</b></span>
                <span>Cần rà soát: <b>{money(days.reduce((sum, day) => sum + reconciliationCount(day), 0))}</b></span>
                <span>Doanh số: <b>{money(days.reduce((sum, day) => sum + Number(day.revenue ?? 0), 0))}</b></span>
            </div>
        </div>
    );
}

function ChartDialog({ state, endpoint, filters, onClose }) {
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
        <DialogShell open={Boolean(state)} title="BIỂU ĐỒ DỮ LIỆU" size="full" onClose={onClose}>
            {loading && <LoadingBlock />}
            {error && <ErrorBlock message={error} />}
            {!loading && !error && data && (
                <>
                    <div className="psm-chart-title">
                        <b>{data.title}</b>
                        <span>{data.filterLabel ? `${data.filterLabel} · ` : ''}{data.source?.name}{data.utm_source ? ` / ${data.utm_source}` : ''}{data.utm_campaign ? ` / ${data.utm_campaign}` : ''}</span>
                    </div>
                    <ChartGraphic days={data.days ?? []} />
                </>
            )}
        </DialogShell>
    );
}

function PacketTypeBadge({ row, t }) {
    const label = {
        primary: t('dashboard.marketing.packet_dialog.primary'),
        upsale: t('dashboard.marketing.packet_dialog.upsale'),
        late_upsale: t('dashboard.marketing.packet_dialog.late_upsale'),
        orphan_upsale: t('dashboard.marketing.packet_dialog.orphan_upsale'),
    }[row.packetType] ?? row.packetTypeLabel;

    return <span className={`psm-packet-badge is-${row.packetType}`}>{label}</span>;
}

function LandingPacketsDialog({ state, endpoint, filters, onClose }) {
    const t = useT();
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [data, setData] = useState(null);

    useEffect(() => {
        if (!state?.row || !endpoint) return;
        let alive = true;
        setLoading(true);
        setError('');

        apiGet(`${endpoint}?${queryString({
            ...filters,
            source_id: state.row.sourceId,
            utm_source: state.row.utmSource,
            utm_campaign: state.row.utmCampaign,
            packet_page: page,
            packet_per_page: 20,
        })}`)
            .then((result) => alive && setData(result))
            .catch((exception) => alive && setError(exception.message))
            .finally(() => alive && setLoading(false));

        return () => { alive = false; };
    }, [state, endpoint, filters, page]);

    useEffect(() => {
        if (state) setPage(1);
    }, [state]);

    const summary = data?.summary ?? {};
    const pagination = data?.pagination ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 };
    const expected = Number(state?.row?.contacts ?? 0);
    const actual = Number(summary.contacts ?? 0);

    return (
        <DialogShell
            open={Boolean(state)}
            title={t('dashboard.marketing.packet_dialog.title')}
            size="full"
            onClose={onClose}
        >
            <div className="psm-packet-heading">
                <div>
                    <b>{data?.source?.name ?? state?.row?.sourceName ?? '—'}</b>
                    <span>
                        {data?.utm_source ? `UTM Source: ${data.utm_source}` : t('dashboard.marketing.packet_dialog.all_utm')}
                        {data?.utm_campaign ? ` · UTM Campaign: ${data.utm_campaign}` : ''}
                    </span>
                </div>
                <span className="psm-packet-filter-note">
                    <i className="fa fa-filter" /> {t('dashboard.marketing.packet_dialog.filter_note')}
                </span>
            </div>

            <div className="psm-packet-summary">
                <div><span>{t('dashboard.marketing.packet_dialog.total')}</span><b>{money(summary.contacts)}</b></div>
                <div><span>{t('dashboard.marketing.packet_dialog.primary')}</span><b>{money(summary.baseContacts)}</b></div>
                <div className="is-upsale"><span>{t('dashboard.marketing.packet_dialog.upsale')}</span><b>{money(summary.upsaleContacts)}</b></div>
                <div className="is-valid"><span>{t('dashboard.marketing.packet_dialog.valid_contacts')}</span><b>{money(summary.validContacts)}</b></div>
                <div><span>{t('dashboard.marketing.packet_dialog.unique_phones')}</span><b>{money(summary.uniquePhones)}</b></div>
                <div><span>{t('dashboard.marketing.packet_dialog.duplicate_packets')}</span><b>{money(summary.duplicatePackets)}</b></div>
                <div className="is-review"><span>{t('dashboard.marketing.packet_dialog.reconciliation_packets')}</span><b>{money(reconciliationCount(summary))}</b></div>
                <div className="is-review"><span>{t('dashboard.marketing.packet_dialog.rejected_packets')}</span><b>{money(summary.rejectedPackets)}</b></div>
                <div className="is-review"><span>{t('dashboard.marketing.packet_dialog.failed_packets')}</span><b>{money(summary.failedPackets)}</b></div>
            </div>

            {!loading && !error && data && expected !== actual && (
                <div className="alert alert-warning psm-alert">
                    <i className="fa fa-warning" /> {t('dashboard.marketing.packet_dialog.total_mismatch', { expected, actual })}
                </div>
            )}
            {loading && <LoadingBlock label={t('dashboard.marketing.packet_dialog.loading')} />}
            {error && <ErrorBlock message={error} />}

            {!loading && !error && data && (
                <>
                    <div className="table-responsive psm-packet-table-wrap">
                        <table className="table table-bordered table-striped psm-packet-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>{t('dashboard.marketing.packet_dialog.received_at')}</th>
                                    <th>{t('dashboard.marketing.packet_dialog.packet_type')}</th>
                                    <th>{t('dashboard.marketing.packet_dialog.customer')}</th>
                                    <th>{t('dashboard.marketing.packet_dialog.products')}</th>
                                    <th>{t('dashboard.marketing.packet_dialog.landing')}</th>
                                    <th>UTM</th>
                                    <th>{t('dashboard.marketing.packet_dialog.order')}</th>
                                    <th>{t('dashboard.marketing.packet_dialog.status')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!data.rows?.length && (
                                    <tr><td colSpan="9" className="text-center psm-packet-empty">{t('dashboard.marketing.packet_dialog.empty')}</td></tr>
                                )}
                                {(data.rows ?? []).map((row, index) => (
                                    <tr key={row.id} className={row.isUpsale ? 'is-upsale' : 'is-primary'}>
                                        <td>{Number(pagination.from || 1) + index}</td>
                                        <td className="psm-packet-time">
                                            <b>{row.receivedAt || '—'}</b>
                                            {row.externalId && <small>ID: {row.externalId}</small>}
                                        </td>
                                        <td><PacketTypeBadge row={row} t={t} /></td>
                                        <td className="psm-packet-customer">
                                            <b>{row.customerName}</b>
                                            <a href={`tel:${row.customerPhone}`}>{row.customerPhone}</a>
                                            {row.message && <small title={row.message}>{row.message}</small>}
                                        </td>
                                        <td className="psm-packet-product">{row.productSummary || '—'}</td>
                                        <td className="psm-packet-landing">
                                            <b>{row.landingSourceName !== '—' ? row.landingSourceName : row.sourceName}</b>
                                            <small>{row.landingName !== '—' ? row.landingName : ''}</small>
                                        </td>
                                        <td className="psm-packet-utm">
                                            <span>{row.utmSource || '—'}</span>
                                            <small>{row.utmCampaign || ''}</small>
                                        </td>
                                        <td>{row.orderCode || (row.orderId ? `#${row.orderId}` : '—')}</td>
                                        <td className="psm-packet-status-cell">
                                            <span className={`psm-packet-status is-${row.status}`}>{row.statusLabel || row.status || '—'}</span>
                                            {row.requiresReview && <small className="psm-packet-review"><i className="fa fa-exclamation-triangle" /> {t('dashboard.marketing.packet_dialog.review')}</small>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="psm-packet-pagination">
                        <span>{t('dashboard.marketing.packet_dialog.range', {
                            from: pagination.from ?? 0,
                            to: pagination.to ?? 0,
                            total: pagination.total ?? 0,
                        })}</span>
                        <div>
                            <button type="button" className="btn btn-default btn-xs" disabled={pagination.current_page <= 1} onClick={() => setPage(1)}><i className="fa fa-angle-double-left" /></button>
                            <button type="button" className="btn btn-default btn-xs" disabled={pagination.current_page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}><i className="fa fa-angle-left" /></button>
                            <b>{pagination.current_page} / {pagination.last_page}</b>
                            <button type="button" className="btn btn-default btn-xs" disabled={pagination.current_page >= pagination.last_page} onClick={() => setPage((current) => Math.min(pagination.last_page, current + 1))}><i className="fa fa-angle-right" /></button>
                            <button type="button" className="btn btn-default btn-xs" disabled={pagination.current_page >= pagination.last_page} onClick={() => setPage(pagination.last_page)}><i className="fa fa-angle-double-right" /></button>
                        </div>
                    </div>
                </>
            )}
        </DialogShell>
    );
}

function HelpDialog({ open, onClose }) {
    return (
        <DialogShell open={open} title="HƯỚNG DẪN MARKETING DASHBOARD" size="md" onClose={onClose}>
            <div className="psm-help">
                <p><b>Nút dấu cộng</b> mở danh sách toàn bộ gói tin landing tương ứng với bộ lọc và dòng nguồn/UTM đang xem.</p>
                <p><b>Tổng gói tin</b> là số form landing server nhận được để đối soát với sheet/quảng cáo. Chi tiết xử lý hợp lệ, gửi trùng và cần rà soát nằm trong biểu đồ hoặc danh sách gói tin.</p>
                <p>Bấm mũi tên tại cột sản phẩm để mở chi tiết UTM Source và UTM Campaign; bấm biểu đồ để xem biến động theo ngày.</p>
            </div>
        </DialogShell>
    );
}

function TotalRow({ label, total = {}, advancedUtm = false }) {
    return (
        <tr className="psm-total-row">
            <td colSpan={advancedUtm ? 9 : 6}>{label}:</td>
            <td>{money(total.budget)}</td>
            <td>{money(total.interactions)}</td>
            <td><ContactBreakdown item={total} /></td>
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

function DashboardTable({ report, expanded, onToggle, onChart, onPackets, advancedUtm = false }) {
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
                <colgroup>
                    <col className="psm-col-stt" />
                    <col className="psm-col-source" />
                    <col className="psm-col-product" />
                    <col className="psm-col-channel" />
                    <col className="psm-col-utm-source" />
                    <col className="psm-col-utm-campaign" />
                    {advancedUtm && <>
                        <col className="psm-col-utm-medium" />
                        <col className="psm-col-utm-term" />
                        <col className="psm-col-utm-content" />
                    </>}
                    <col className="psm-col-budget" />
                    <col className="psm-col-clicks" />
                    <col className="psm-col-contacts" />
                    <col className="psm-col-contact-rate" />
                    <col className="psm-col-contact-price" />
                    <col className="psm-col-closed" />
                    <col className="psm-col-close-rate" />
                    <col className="psm-col-products" />
                    <col className="psm-col-products-per-order" />
                    <col className="psm-col-revenue" />
                    <col className="psm-col-net-revenue" />
                    <col className="psm-col-budget-revenue" />
                    <col className="psm-col-budget-net-revenue" />
                    <col className="psm-col-chart" />
                </colgroup>
                <thead>
                    <tr className="psm-head-group"><th colSpan={advancedUtm ? 9 : 6}>THÔNG TIN NGUỒN DỮ LIỆU</th><th colSpan="14">THÔNG TIN HIỆU QUẢ MARKETING</th></tr>
                    <tr>
                        <th>STT</th><th>Tên Nguồn dữ liệu</th><th>Sản phẩm</th><th>Kênh quảng cáo</th><th>UTM Source</th><th>UTM<br />Campaign</th>
                        {advancedUtm && <><th>UTM<br />Medium</th><th>UTM<br />Term</th><th>UTM<br />Content</th></>}
                        <th>Ngân sách (1)</th><th>Số tương tác<br />(2)</th><th>Tổng gói tin<br />(3)</th><th>Tỷ lệ<br />gói tin/tương tác<br />(4=3/2) (%)</th>
                        <th>Giá/gói tin<br />(5=1/3)</th><th>Chốt đơn<br />(6)</th><th>Tỷ lệ chốt/gói tin<br />(7=6/3) (%)</th><th>Số sản phẩm<br />(8)</th>
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
                                        <button type="button" className="psm-add-button" title="Xem gói tin landing" onClick={() => onPackets(row)}><i className="fa fa-plus" /></button>
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
                                <td>{money(row.budget)}</td><td>{money(row.interactions)}</td><td><ContactBreakdown item={row} /></td><td>{percent(row.contactRate, true)}</td>
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
    const { draft, set, apply } = useInertiaFilters(routeUrl, filters);
    const [gearOpen, setGearOpen] = useState(false);
    const [expanded, setExpanded] = useState(new Set());
    const [chartState, setChartState] = useState(null);
    const [packetState, setPacketState] = useState(null);
    const [helpOpen, setHelpOpen] = useState(false);
    const gearRef = useRef(null);

    useEffect(() => {
        const close = (event) => {
            if (gearRef.current && !gearRef.current.contains(event.target)) setGearOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const asSelectOptions = (items = []) => items.map((item) => ({
        ...item,
        value: String(item.value ?? item.id ?? ''),
        label: item.label ?? item.name ?? String(item.value ?? item.id ?? ''),
    }));
    const teams = asSelectOptions(filterOptions.teams ?? []);
    const marketers = asSelectOptions(filterOptions.marketingUsers ?? []);
    const selectedTeam = Number(draft.team_id || 0);
    const selectedLeader = Number(draft.team_leader_id || 0);
    const visibleTeams = selectedLeader ? teams.filter((team) => Number(team.leader_user_id) === selectedLeader) : teams;
    const visibleMarketers = marketers.filter((user) => (
        (!selectedTeam || Number(user.team_id) === selectedTeam)
        && (!selectedLeader || Number(user.manager_user_id) === selectedLeader || Number(user.id) === selectedLeader)
    ));
    const selectedParent = Number(draft.parent_product_id || 0);
    const products = asSelectOptions((filterOptions.products ?? []).filter((product) => !selectedParent || Number(product.parent_id) === selectedParent || Number(product.id) === selectedParent));
    const teamLeaders = asSelectOptions(filterOptions.teamLeaders ?? []);
    const parentProducts = asSelectOptions(filterOptions.parentProducts ?? []);
    const pagination = report.pagination ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 10 };

    const toggle = (key) => setExpanded((current) => {
        const next = new Set(current);
        if (next.has(key)) next.delete(key); else next.add(key);
        return next;
    });
    const exportHref = `${endpoints.export ?? `${routeUrl}/export`}?${queryString(draft)}`;

    const primaryFilters = (
        <div className="psm-top-selects">
            <PushsaleSelect value={draft.team_leader_id ?? ''} onChange={(value) => { set('team_leader_id', value); set('team_id', ''); set('marketer_id', ''); }} options={teamLeaders} placeholder="--Chọn trưởng nhóm--" searchable />
            <PushsaleSelect value={draft.team_id ?? ''} onChange={(value) => { set('team_id', value); set('marketer_id', ''); }} options={visibleTeams} placeholder="--Chọn nhóm--" searchable />
            <PushsaleSelect value={draft.marketer_id ?? ''} onChange={(value) => set('marketer_id', value)} options={visibleMarketers} placeholder="--Marketing--" searchable searchPlaceholder="Tìm marketing..." />
        </div>
    );

    const shellActions = (
        <div className="psm-top-actions">
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
    );

    const advancedFilters = (
        <div className="ps-adv-filter-panel psm-filter-panel">
            <div className="ps-adv-filter-row psm-filter-grid is-first">
                <PushsaleSelect value={draft.date_type ?? ''} onChange={(value) => set('date_type', value)} options={filterOptions.dateTypes ?? []} placeholder="--Chuẩn Pushsale--" />
                <PushsaleDateRange filters={draft} onChange={set} />
                <PushsaleSelect value={draft.operation_scope ?? ''} onChange={(value) => set('operation_scope', value)} options={filterOptions.operationScopes ?? []} placeholder="Tác nghiệp cần" />
                <PushsaleSelect value={draft.customer_type ?? ''} onChange={(value) => set('customer_type', value)} options={filterOptions.customerTypes ?? []} placeholder="--Tất cả--" />
                <PushsaleSelect value={draft.contact_mode ?? ''} onChange={(value) => set('contact_mode', value)} options={filterOptions.contactModes ?? []} placeholder="Có gói tin về" />
                <PushsaleSelect value={draft.source_type ?? ''} onChange={(value) => set('source_type', value)} options={filterOptions.sourceTypes ?? []} placeholder="--Nguồn dữ liệu--" />
                <PushsaleSelect value={draft.ad_channel ?? ''} onChange={(value) => set('ad_channel', value)} options={filterOptions.adChannels ?? []} placeholder="--Kênh quảng cáo--" />
            </div>
            <div className="ps-adv-filter-row psm-filter-grid is-second">
                <PushsaleSelect value={draft.parent_product_id ?? ''} onChange={(value) => { set('parent_product_id', value); set('product_id', ''); }} options={parentProducts} placeholder="--Sản phẩm cha--" searchable />
                <ProductSearchSelect products={products} value={draft.product_id ?? ''} onChange={(value) => set('product_id', value)} placeholder="--Sản phẩm / gói sản phẩm--" showPrice={false} />
                <input className="ps-control" value={draft.utm_keyword ?? ''} onChange={(event) => set('utm_keyword', event.target.value)} placeholder="Mã Utm" />
                <input className="ps-control" value={draft.source_keyword ?? ''} onChange={(event) => set('source_keyword', event.target.value)} placeholder="Tên nguồn dữ liệu" />
                <PushsaleSelect value={draft.sort_by ?? ''} onChange={(value) => set('sort_by', value)} options={filterOptions.sortOptions ?? []} placeholder="Số gói tin" />
                <PushsaleSelect value={draft.revenue_mode ?? ''} onChange={(value) => set('revenue_mode', value)} options={filterOptions.revenueModes ?? []} placeholder="1.Doanh số tổng" />
                <div className="ps-adv-inline-cluster">
                    <label className="psm-utm-check"><input type="checkbox" checked={Boolean(draft.advanced_utm)} onChange={(event) => set('advanced_utm', event.target.checked ? 1 : 0)} /> UTM Nâng cao</label>
                    <Link className="psm-history-link" href={endpoints.activityHistory ?? '/notifications'}><i className="fa fa-history" /> Lịch sử hoạt động</Link>
                </div>
            </div>
        </div>
    );

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Marketing dashboard" />
            <PushsalePageShell
                title="Marketing dashboard"
                pageCode="2.1"
                headerClassName="psm-dashboard-header"
                primaryFilters={primaryFilters}
                actions={shellActions}
                advancedFilters={advancedFilters}
                className="psm-page"
            >
                <div className="psm-table-area">
                    <DashboardTable report={report} expanded={expanded} advancedUtm={Boolean(draft.advanced_utm)} onToggle={toggle} onChart={(row) => setChartState({ row })} onPackets={(row) => setPacketState({ row })} />
                    <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={draft} itemLabel="nguồn dữ liệu" />
                </div>
            </PushsalePageShell>

            <ChartDialog state={chartState} endpoint={endpoints.chart} filters={draft} onClose={() => setChartState(null)} />
            <LandingPacketsDialog state={packetState} endpoint={endpoints.packets} filters={draft} onClose={() => setPacketState(null)} />
            <HelpDialog open={helpOpen} onClose={() => setHelpOpen(false)} />
        </AppLayout>
    );
}
