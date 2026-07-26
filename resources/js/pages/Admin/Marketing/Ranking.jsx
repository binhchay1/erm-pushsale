import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import {
    PushsaleDateRange,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';

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
                {!sorted.length && <div className="psr-empty-podium">Chưa có dữ liệu xếp hạng trong khoảng thời gian đã chọn.</div>}
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
                        <th rowSpan="2">STT</th>
                        <th rowSpan="2">SALE</th>
                        <th colSpan="5">KHÁCH HÀNG MỚI</th>
                        <th colSpan="5">KHÁCH HÀNG CŨ</th>
                        <th colSpan="5">TỔNG CHUNG</th>
                    </tr>
                    <tr>
                        <th>Contact</th><th>Chốt đơn</th><th>% chốt</th><th>Số SP</th><th>Doanh số tạm tính sau CK</th>
                        <th>Contact</th><th>Chốt đơn</th><th>% chốt</th><th>Số SP</th><th>Doanh số tạm tính sau CK</th>
                        <th>Doanh số tạm tính sau CK</th><th>CK</th><th>COD thu của khách</th><th>Phí COD dịch vụ</th><th>Doanh số</th>
                    </tr>
                </thead>
                <tbody>
                    <tr className="psr-total-row">
                        <td colSpan="2">Tổng cộng:</td>
                        <RankingCells row={total} />
                    </tr>
                    {!rows.length && <tr><td colSpan="17" className="psr-empty">Không có dữ liệu.</td></tr>}
                    {rows.map((row) => (
                        <tr key={row.id} className={row.rank <= 3 ? `is-top rank-${row.rank}` : ''}>
                            <td>{row.rank}</td>
                            <td className="psr-sale-cell">
                                <span className="psr-sale-avatar"><Avatar user={row} rank={row.rank} /></span>
                                <span><b>{row.name}</b><small>{row.team || row.username}</small></span>
                            </td>
                            <RankingCells row={row} />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function MarketingRanking({ report = {}, filters = {}, filterOptions = {}, filterRouteUrl = '/admin/rankings', activeMenuCode = '2.2', pageTitle = 'Bảng xếp hạng' }) {
    const { draft, set, apply } = usePushsaleFilters(filterRouteUrl, filters);
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
            <Head title={pageTitle} />
            <section className="psr-page">
                <div className="psr-topbar">
                    <h1>{pageTitle}</h1>
                    <div className="psr-primary-filters">
                        <PushsaleDateRange filters={draft} onChange={set} />
                        <PushsaleSelect value={draft.discount_mode ?? ''} onChange={(value) => set('discount_mode', value)} options={filterOptions.discountModes ?? []} placeholder="Sau chiết khấu" />
                        <PushsaleSelect value={draft.operation_scope ?? ''} onChange={(value) => set('operation_scope', value)} options={filterOptions.operationScopes ?? []} placeholder="Tác nghiệp cần" />
                    </div>
                    <div className="psr-actions">
                        <button type="button" className="psr-collapse" onClick={() => setCollapsed((value) => !value)}><i className={`fa fa-angle-double-${collapsed ? 'down' : 'up'}`} /></button>
                        <PushsaleSearchButton onClick={() => apply()} />
                        <div className="psr-gear" ref={gearRef}>
                            <button type="button" className="psr-square-button" onClick={() => setGearOpen((value) => !value)}><i className="fa fa-cog" /></button>
                            {gearOpen && <div className="psr-gear-menu"><button type="button" onClick={() => window.print()}><i className="fa fa-print" /> In bảng xếp hạng</button><button type="button" onClick={() => apply()}><i className="fa fa-refresh" /> Làm mới dữ liệu</button></div>}
                        </div>
                        <button type="button" className="psr-help" title="Bảng xếp hạng được tính từ dữ liệu contact và đơn chốt thực tế"><i className="fa fa-question-circle" /></button>
                    </div>
                </div>

                {!collapsed && (
                    <div className="psr-filter-row">
                        <PushsaleSelect value={draft.team_leader_id ?? ''} onChange={(value) => { set('team_leader_id', value); set('team_id', ''); }} options={filterOptions.teamLeaders ?? []} placeholder="--Chọn trưởng nhóm--" />
                        <PushsaleSelect value={draft.team_id ?? ''} onChange={(value) => set('team_id', value)} options={teams} placeholder="--Chọn nhóm--" />
                    </div>
                )}

                <div className="psr-content">
                    <Podium items={report.top ?? []} />
                    <RankingTable report={report} />
                    <div className="psr-pagination-row">
                        <div>Hiển thị {pagination.from ?? 0} - {pagination.to ?? 0} / {pagination.total ?? 0} nhân sự</div>
                        <PushsalePager current={pagination.current_page} totalPages={pagination.last_page} onPage={(page) => apply({ page })} />
                        <label>Hiển thị <select value={pagination.per_page ?? 10} onChange={(event) => apply({ page: 1, per_page: event.target.value })}><option value="10">10</option><option value="20">20</option><option value="50">50</option><option value="100">100</option></select> dòng</label>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
