import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import {
    PushsaleDateRange,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    useInertiaFilters,
} from '@/components/reports/PushsaleReportChrome';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

function money(value) {
    return formatNumber(Number(value ?? 0));
}

function percent(value) {
    const number = Number(value ?? 0);
    return `${Number.isInteger(number) ? number : number.toFixed(2)}%`;
}

function Avatar({ user, rank }) {
    if (user.avatar) {
        return <img src={user.avatar} alt={user.name} />;
    }
    return <span className={`psr-avatar-fallback rank-${rank}`}>{user.initials || user.name?.slice(0, 2)?.toUpperCase()}</span>;
}

function Podium({ items = [] }) {
    const t = useT();
    const sorted = [...items].sort((a, b) => Number(b.rank) - Number(a.rank));

    return (
        <div className="psr-podium-scroll">
            <div className="psr-podium" style={{ '--psr-count': Math.max(1, sorted.length) }}>
                {sorted.map((item) => {
                    const rank = Number(item.rank);
                    const step = Math.max(0, 11 - rank);
                    return (
                        <div className={`psr-podium-item rank-${rank}`} key={item.id} style={{ '--psr-step': step }}>
                            <div className="psr-person">
                                {rank === 1 && <span className="psr-crown" aria-hidden="true">♛</span>}
                                <div className="psr-avatar"><Avatar user={item} rank={rank} /></div>
                                <strong className="psr-rank-number">{rank}</strong>
                                <div className="psr-person-name" title={item.name}>{item.name}</div>
                                <div className="psr-person-team">{item.team || item.username}</div>
                                <div className="psr-person-revenue">{money(item.finalRevenue)}</div>
                            </div>
                            <div className="psr-step" aria-hidden="true" />
                        </div>
                    );
                })}
                {!sorted.length && <div className="psr-empty-podium">{t('rankings.empty_period')}</div>}
            </div>
        </div>
    );
}

function RankingCells({ row }) {
    return (
        <>
            <td>{money(row.newContacts)}</td>
            <td>{money(row.newClosedOrders)}</td>
            <td>{percent(row.newClosingRate)}</td>
            <td>{money(row.newProductQuantity)}</td>
            <td>{money(row.newRevenue)}</td>
            <td>{money(row.oldContacts)}</td>
            <td>{money(row.oldClosedOrders)}</td>
            <td>{percent(row.oldClosingRate)}</td>
            <td>{money(row.oldProductQuantity)}</td>
            <td>{money(row.oldRevenue)}</td>
            <td>{money(row.subtotalRevenue)}</td>
            <td>{money(row.discount)}</td>
            <td>{money(row.codCollected)}</td>
            <td>{money(row.codServiceFee)}</td>
            <td>{money(row.finalRevenue)}</td>
        </>
    );
}

function RankingTable({ report = {} }) {
    const t = useT();
    const rows = report.rows ?? [];
    const total = report.totalRow ?? {};

    return (
        <div className="psr-table-scroll">
            <table className="psr-table">
                <colgroup>
                    <col className="psr-col-index" />
                    <col className="psr-col-sale" />
                    <col className="psr-col-count" />
                    <col className="psr-col-count" />
                    <col className="psr-col-count" />
                    <col className="psr-col-rate" />
                    <col className="psr-col-products" />
                    <col className="psr-col-revenue" />
                    <col className="psr-col-count" />
                    <col className="psr-col-count" />
                    <col className="psr-col-rate" />
                    <col className="psr-col-products" />
                    <col className="psr-col-revenue" />
                    <col className="psr-col-revenue" />
                    <col className="psr-col-discount" />
                    <col className="psr-col-cod" />
                    <col className="psr-col-cod-fee" />
                    <col className="psr-col-revenue" />
                </colgroup>
                <thead>
                    <tr className="psr-head-group">
                        <th rowSpan="2">{t('reports.pushsale.stt')}</th>
                        <th rowSpan="2">{t('reports.pushsale.sale')}</th>
                        <th rowSpan="2">{t('reports.columns.raw_packets')}</th>
                        <th colSpan="5">{t('reports.ceo_report.new_customers_group')}</th>
                        <th colSpan="5">{t('reports.ceo_report.old_customers_group')}</th>
                        <th colSpan="5">{t('reports.ceo_report.total_group')}</th>
                    </tr>
                    <tr>
                        <th>{t('reports.pushsale.contact')}</th><th>{t('reports.pushsale.closed_orders')}</th><th>{t('reports.pushsale.close_rate')}</th><th>{t('reports.pushsale.product_qty')}</th><th>{t('reports.columns.net')}</th>
                        <th>{t('reports.pushsale.contact')}</th><th>{t('reports.pushsale.closed_orders')}</th><th>{t('reports.pushsale.close_rate')}</th><th>{t('reports.pushsale.product_qty')}</th><th>{t('reports.columns.net')}</th>
                        <th>{t('reports.columns.net')}</th><th>{t('reports.columns.discount')}</th><th>{t('reports.ceo_report.cod_fee')}</th><th>{t('reports.ceo_report.cod_fee')}</th><th>{t('reports.pushsale.revenue')}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr className="psr-total-row">
                        <td colSpan="2">{t('common.grand_total')}:</td>
                        <td>{money(total.rawContacts)}</td>
                        <RankingCells row={total} />
                    </tr>
                    {!rows.length && <tr><td colSpan="18" className="psr-empty">{t('reports.pushsale.no_data')}</td></tr>}
                    {rows.map((row) => (
                        <tr key={row.id} className={row.rank <= 3 ? `is-top rank-${row.rank}` : ''}>
                            <td>{row.rank}</td>
                            <td className="psr-sale-cell">
                                <span className="psr-sale-avatar"><Avatar user={row} rank={row.rank} /></span>
                                <span><b>{row.name}</b><small>{row.team || row.username}</small></span>
                            </td>
                            <td>{money(row.rawContacts)}</td>
                            <RankingCells row={row} />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function MarketingRanking({ report = {}, filters = {}, filterOptions = {}, filterRouteUrl = '/admin/rankings', activeMenuCode = '2.2', pageTitle = '' }) {
    const t = useT();
    const resolvedTitle = translateReportText(t, pageTitle, pageTitle || t('rankings.title'));
    const { draft, set, apply } = useInertiaFilters(filterRouteUrl, filters);
    const [collapsed, setCollapsed] = useState(false);
    const [gearOpen, setGearOpen] = useState(false);
    const gearRef = useRef(null);

    useEffect(() => {
        const close = (event) => {
            if (gearRef.current && !gearRef.current.contains(event.target)) setGearOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const selectedLeader = Number(draft.team_leader_id || 0);
    const teams = selectedLeader
        ? (filterOptions.teams ?? []).filter((team) => Number(team.leader_user_id) === selectedLeader)
        : (filterOptions.teams ?? []);
    const pagination = report.pagination ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 10 };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={resolvedTitle} />
            <section className="psr-page">
                <PageHeader
                    title={resolvedTitle}
                    pageCode={activeMenuCode}
                    className="psr-topbar"
                    filters={(
                        <div className="psr-primary-filters">
                            <PushsaleDateRange filters={draft} onChange={set} />
                            <PushsaleSelect value={draft.discount_mode ?? ''} onChange={(value) => set('discount_mode', value)} options={filterOptions.discountModes ?? []} placeholder={t('reports.pushsale.discount_after')} />
                            <PushsaleSelect value={draft.operation_scope ?? ''} onChange={(value) => set('operation_scope', value)} options={filterOptions.operationScopes ?? []} placeholder={t('dashboard.marketing.operation_scope')} />
                        </div>
                    )}
                    actions={(
                        <div className="psr-actions">
                            <button type="button" className="psr-collapse" onClick={() => setCollapsed((value) => !value)}><i className={`fa fa-angle-double-${collapsed ? 'down' : 'up'}`} /></button>
                            <PushsaleSearchButton onClick={() => apply()} />
                            <div className="psr-gear" ref={gearRef}>
                                <button type="button" className="psr-square-button" onClick={() => setGearOpen((value) => !value)}><i className="fa fa-cog" /></button>
                                {gearOpen && <div className="psr-gear-menu"><button type="button" onClick={() => window.print()}><i className="fa fa-print" /> {t('rankings.print')}</button><button type="button" onClick={() => apply()}><i className="fa fa-refresh" /> {t('common.refresh')}</button></div>}
                            </div>
                            <button type="button" className="psr-help" title={t('rankings.help_title')}><i className="fa fa-question-circle" /></button>
                        </div>
                    )}
                    advanced={!collapsed ? (
                        <div className="psr-filter-row">
                            <PushsaleSelect value={draft.team_leader_id ?? ''} onChange={(value) => { set('team_leader_id', value); set('team_id', ''); }} options={filterOptions.teamLeaders ?? []} placeholder={t('reports.pushsale.choose_team_leader')} />
                            <PushsaleSelect value={draft.team_id ?? ''} onChange={(value) => set('team_id', value)} options={teams} placeholder={t('reports.pushsale.choose_team')} />
                        </div>
                    ) : null}
                    collapsible={false}
                />

                <div className="psr-content">
                    <Podium items={report.top ?? []} />
                    <RankingTable report={report} />
                    <div className="psr-pagination-row">
                        <div>{t('common.pagination.showing', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total ?? 0 })}</div>
                        <PushsalePager current={pagination.current_page} totalPages={pagination.last_page} onPage={(page) => apply({ page })} />
                        <label>{t('common.pagination.rows_per_page')} <select value={pagination.per_page ?? 10} onChange={(event) => apply({ page: 1, per_page: event.target.value })}><option value="10">10</option><option value="20">20</option><option value="50">50</option><option value="100">100</option></select></label>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
