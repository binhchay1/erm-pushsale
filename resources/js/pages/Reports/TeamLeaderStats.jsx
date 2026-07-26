import { Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PushsaleStatusSummary } from '@/components/reports/ceo/PushsaleStatusSummary';
import { useReportSearch } from '@/hooks/useReportSearch';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

const PER_PAGE_OPTIONS = ['20', '50', '100', '200', '500', '1000', '999999'];
const MONEY_KEYS = new Set([
    'budget',
    'contactPrice',
    'newEstRevenue',
    'oldEstRevenue',
    'totalEstRevenue',
    'codFee',
    'codSupport',
    'discount',
    'deposit',
    'totalAfterDiscount',
    'marketingKpi',
]);
const PERCENT_KEYS = new Set([
    'closeRate',
    'budgetRevenueRatioNew',
    'budgetRevenueRatioNewAfterDiscount',
    'budgetRevenueRatioTotal',
    'achievementRate',
]);

function optionValue(item) {
    return String(item?.value ?? item?.id ?? '');
}

function optionLabel(item) {
    return item?.label ?? item?.name ?? item?.title ?? String(item?.value ?? item?.id ?? '');
}

function Select({ value, onChange, options = [], placeholder, className = 'ps-select' }) {
    return (
        <select className={className} value={String(value ?? '')} onChange={(event) => onChange(event.target.value)}>
            {placeholder ? <option value="">{placeholder}</option> : null}
            {options.map((item) => (
                <option key={optionValue(item) || optionLabel(item)} value={optionValue(item)}>
                    {optionLabel(item)}
                </option>
            ))}
        </select>
    );
}

function displayMetric(value, key) {
    if (value == null || value === '') {
        return PERCENT_KEYS.has(key) ? '0 %' : '0';
    }

    if (PERCENT_KEYS.has(key)) {
        return `${Number(value) || 0} %`;
    }

    if (MONEY_KEYS.has(key) || typeof value === 'number') {
        return formatNumber(Number(value) || 0);
    }

    return String(value);
}

function ProgressTd({ row, metric, max, className = '' }) {
    const value = Number(row?.[metric]) || 0;
    const denominator = Number(max?.[metric]) || 0;
    const width = PERCENT_KEYS.has(metric)
        ? Math.min(100, Math.max(0, value))
        : denominator > 0
          ? Math.min(100, Math.max(0, (value / denominator) * 100))
          : 0;

    return (
        <td className={`tdProgress ${className}`.trim()}>
            <div className="box-progress">
                <div className="progress">
                    <div className="progress-bar" style={{ width: `${width}%` }} />
                </div>
                <span className="progress-text">{displayMetric(row?.[metric], metric)}</span>
            </div>
        </td>
    );
}

function sumRows(rows, key) {
    return rows.reduce((acc, row) => acc + (Number(row?.[key]) || 0), 0);
}

function maxRows(rows, totals) {
    const metrics = [
        'budget',
        'contacts',
        'contactPrice',
        'closed',
        'closeRate',
        'newEstRevenue',
        'budgetRevenueRatioNew',
        'budgetRevenueRatioNewAfterDiscount',
        'oldEstRevenue',
        'totalEstRevenue',
        'budgetRevenueRatioTotal',
        'codFee',
        'codSupport',
        'discount',
        'deposit',
        'totalAfterDiscount',
        'marketingKpi',
        'achievementRate',
    ];

    return metrics.reduce((carry, key) => {
        carry[key] = Math.max(Number(totals?.[key]) || 0, ...rows.map((row) => Number(row?.[key]) || 0));
        return carry;
    }, {});
}

function MarketingLeaderFilter({ filters, filterOptions, routeUrl }) {
    const t = useT();
    const { search } = useReportSearch(routeUrl, filters);
    const [form, setForm] = useState({ ...filters });

    useEffect(() => {
        setForm({ ...filters });
    }, [filters]);

    const set = (key, value) => setForm((current) => ({ ...current, [key]: value, page: 1 }));
    const submit = () => search(form);
    const marketingTeams = useMemo(() => {
        const all = filterOptions?.marketingTeams ?? [];
        const leaderId = String(form.marketing_team_leader_id ?? '');
        if (!leaderId) return all;
        return all.filter((team) => String(team.leaderId ?? team.leader_user_id ?? '') === leaderId);
    }, [filterOptions?.marketingTeams, form.marketing_team_leader_id]);

    return (
        <PageHeader
            title={t('reports.team_leaders.title')}
            pageCode="2.8.1"
            className="ps-leader-filter"
            actions={(
                <button type="button" className="btn btn-sm btn-primary ps-leader-search-btn" onClick={submit}>
                    <Search className="size-3.5" /> {t('common.search')}
                </button>
            )}
            advanced={(
                <div className="ps-leader-advanced-row" role="search" aria-label="Bộ lọc thống kê trưởng nhóm">
                    <Select
                        value={form.date_type}
                        onChange={(value) => set('date_type', value)}
                        options={filterOptions?.dateTypes ?? []}
                        placeholder="--Chuẩn Pushsale--"
                    />
                    <div className="date-range-wrap legacy-range">
                        <input
                            type="date"
                            className="ps-input"
                            value={form.date_from ?? ''}
                            onChange={(event) => set('date_from', event.target.value)}
                        />
                        <input
                            type="date"
                            className="ps-input"
                            value={form.date_to ?? ''}
                            onChange={(event) => set('date_to', event.target.value)}
                        />
                    </div>
                    <Select
                        value={form.delivery_status}
                        onChange={(value) => set('delivery_status', value)}
                        options={filterOptions?.deliveryStatuses ?? []}
                        placeholder="-- Chọn trạng thái giao hàng --"
                    />
                    <Select
                        value={form.discount_mode}
                        onChange={(value) => set('discount_mode', value)}
                        options={filterOptions?.discountModes ?? []}
                        placeholder="Sau chiết khấu"
                    />
                    <Select
                        value={form.marketing_team_leader_id}
                        onChange={(value) => set('marketing_team_leader_id', value)}
                        options={filterOptions?.marketingTeamLeaders ?? []}
                        placeholder="--Chọn trưởng nhóm--"
                    />
                    <Select
                        value={form.marketing_team_id}
                        onChange={(value) => set('marketing_team_id', value)}
                        options={marketingTeams}
                        placeholder="--Chọn nhóm--"
                    />
                    <Select
                        value={form.parent_product_id}
                        onChange={(value) => set('parent_product_id', value)}
                        options={filterOptions?.parentProducts ?? []}
                        placeholder="-- Sản phẩm cha --"
                    />
                    <Select
                        value={form.product_id}
                        onChange={(value) => set('product_id', value)}
                        options={filterOptions?.products ?? []}
                        placeholder="-- Sản phẩm --"
                    />
                    <Select
                        value={form.reconciliation_status}
                        onChange={(value) => set('reconciliation_status', value)}
                        options={filterOptions?.reconciliationStatuses ?? []}
                        placeholder="-- Đối soát --"
                    />
                    <Select
                        value={form.per_page ?? '20'}
                        onChange={(value) => set('per_page', value)}
                        options={PER_PAGE_OPTIONS.map((value) => ({ value, label: value === '999999' ? '--Hiển thị tất--' : value }))}
                    />
                    <label className="ps-report-check">
                        <input
                            type="checkbox"
                            checked={Boolean(form.no_closing_date_limit)}
                            onChange={(event) => set('no_closing_date_limit', event.target.checked ? 1 : 0)}
                        />
                        <span>Không giới hạn ngày chốt</span>
                    </label>
                </div>
            )}
        />
    );
}

function MarketingName({ row }) {
    return (
        <span>
            <b>{row.marketerName}</b>
            {row.marketerUsername ? (
                <>
                    <br />
                    <span className="small-tip">({row.marketerUsername})</span>
                </>
            ) : null}
        </span>
    );
}

function MarketingStatsTable({ rows, totals }) {
    const max = useMemo(() => maxRows(rows, totals), [rows, totals]);

    return (
        <div className="dragscroll1 tableFixHead table_marketing">
            <table className="table table-bordered table-multi-select ps-leader-table">
                <thead>
                    <tr className="drags-area">
                        <th className="text-center" style={{ width: 50 }} />
                        <th className="text-center" style={{ width: '11%' }} />
                        <th className="text-center" colSpan={8}>KHÁCH HÀNG MỚI</th>
                        <th className="text-center" colSpan={1}>KHÁCH HÀNG CŨ</th>
                        <th className="text-center" colSpan={9}>TỔNG CHUNG</th>
                    </tr>
                    <tr className="drags-area">
                        <th className="text-center">STT</th>
                        <th className="text-center">MARKETING</th>
                        <th className="text-center">Ngân sách</th>
                        <th className="text-center">Contact</th>
                        <th className="text-center">Giá contact</th>
                        <th className="text-center">Chốt đơn</th>
                        <th className="text-center">Tỉ lệ chốt đơn (%)</th>
                        <th className="text-center">Doanh số tạm tính (KHM)</th>
                        <th className="text-center">Ngân sách/Doanh số (KHM)(%)</th>
                        <th className="text-center">Ngân sách/Doanh số đã CK(%)</th>
                        <th className="text-center">Doanh số tạm tính (KHC)</th>
                        <th className="text-center">Doanh số tạm tính</th>
                        <th className="text-center">Ngân sách/Doanh số (%)</th>
                        <th className="text-center show_sp">Phí COD</th>
                        <th className="text-center show_sp">Hỗ trợ COD</th>
                        <th className="text-center show_sp">CK</th>
                        <th className="text-center show_sp">Đặt cọc</th>
                        <th className="text-center">Doanh số tạm tính sau chiết khấu</th>
                        <th className="text-center">KPI doanh số</th>
                        <th className="text-center no-wrap">Tỉ lệ (%)</th>
                    </tr>
                    <tr className="rowsum drags-area">
                        <td colSpan={2} className="text-center font-weight-bold">Tổng:</td>
                        {[
                            'budget',
                            'contacts',
                            'contactPrice',
                            'closed',
                            'closeRate',
                            'newEstRevenue',
                            'budgetRevenueRatioNew',
                            'budgetRevenueRatioNewAfterDiscount',
                            'oldEstRevenue',
                            'totalEstRevenue',
                            'budgetRevenueRatioTotal',
                            'codFee',
                            'codSupport',
                            'discount',
                            'deposit',
                            'totalAfterDiscount',
                            'marketingKpi',
                            'achievementRate',
                        ].map((key) => (
                            <td key={key} className="text-center font-weight-bold">
                                {displayMetric(totals?.[key], key)}
                            </td>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={20} className="text-center no-data-row">Không có dữ liệu phù hợp.</td>
                        </tr>
                    ) : rows.map((row) => (
                        <tr key={row.id}>
                            <td className="text-center">{row.stt}</td>
                            <td><MarketingName row={row} /></td>
                            <ProgressTd row={row} metric="budget" max={max} className="tdNganSach_Marketing" />
                            <ProgressTd row={row} metric="contacts" max={max} className="tdSoContact_Marketing" />
                            <ProgressTd row={row} metric="contactPrice" max={max} className="tdSoClick_Marketing" />
                            <ProgressTd row={row} metric="closed" max={max} className="tdSoClick_Marketing" />
                            <ProgressTd row={row} metric="closeRate" max={max} className="tdTyLeContactClick_Marketing" />
                            <ProgressTd row={row} metric="newEstRevenue" max={max} className="tdDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="budgetRevenueRatioNew" max={max} className="tdTyLeNganSachDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="budgetRevenueRatioNewAfterDiscount" max={max} className="tdTyLeNganSachDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="oldEstRevenue" max={max} className="tdDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="totalEstRevenue" max={max} className="tdDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="budgetRevenueRatioTotal" max={max} className="tdTyLeNganSachDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="codFee" max={max} className="tdDoanhSo_Marketing show_sp" />
                            <ProgressTd row={row} metric="codSupport" max={max} className="tdDoanhSo_Marketing show_sp" />
                            <ProgressTd row={row} metric="discount" max={max} className="tdDoanhSo_Marketing show_sp" />
                            <ProgressTd row={row} metric="deposit" max={max} className="tdDoanhSo_Marketing show_sp" />
                            <ProgressTd row={row} metric="totalAfterDiscount" max={max} className="tdDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="marketingKpi" max={max} className="tdDoanhSo_Marketing" />
                            <ProgressTd row={row} metric="achievementRate" max={max} className="tdTyLeNganSachDoanhSo_Marketing" />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function TeamLeaderStats({
    rows = [],
    totals = {},
    statusSummary = {},
    filters = {},
    filterOptions = {},
    routeUrl,
    activeMenuCode = '2.8.1',
}) {
    const t = useT();
    const title = t('reports.team_leaders.title');

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <div className="ceo-report-pushsale ps-marketing-leader-page">
                <MarketingLeaderFilter filters={filters} filterOptions={filterOptions} routeUrl={routeUrl} />
                <div className="box">
                    <div className="box-body">
                        <PushsaleStatusSummary statusSummary={statusSummary} />
                        <div style={{ clear: 'both' }} />
                        <MarketingStatsTable rows={rows} totals={totals} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
