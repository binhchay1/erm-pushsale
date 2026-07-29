import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
    PushsaleSelect,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN');
const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

const ALL_STAGES = [
    { key: 'call_1', label: 'Gọi lần 1' },
    { key: 'call_2', label: 'Gọi lần 2' },
    { key: 'call_3', label: 'Gọi lần 3' },
    { key: 'call_4', label: 'Gọi lần 4' },
    { key: 'call_5', label: 'Gọi lần 5' },
    { key: 'call_6', label: 'Gọi lần 6' },
    { key: 'care_1', label: 'Chăm sóc lần 1' },
    { key: 'care_2', label: 'Chăm sóc lần 2' },
    { key: 'care_3', label: 'Chăm sóc lần 3' },
    { key: 'skipped', label: 'Bỏ qua' },
];

const DATE_TYPE_OPTIONS = [
    { id: '', label: '--Chuẩn Pushsale--' },
    { id: 'next_operation_date', label: 'Ngày sale tác nghiệp tiếp' },
    { id: 'closing_date', label: 'Ngày sale chốt đơn' },
    { id: 'care_update', label: 'Ngày sale tác nghiệp' },
    { id: 'data_arrival', label: 'Ngày data về hệ thống' },
    { id: 'sale_received_data', label: 'Ngày sale nhận data' },
    { id: 'posting_date', label: 'Ngày đăng đơn' },
    { id: 'desired_delivery_date', label: 'Ngày nhận care đơn' },
    { id: 'delivery_update_date', label: 'Ngày cập nhật care đơn' },
];

const METRIC_OPTIONS = [
    { id: 'total_revenue', label: '1.Doanh số tổng' },
    { id: 'total_closed', label: '2.Số chốt đơn' },
    { id: 'total_contacts', label: '3.Số contact' },
    { id: 'total_rate', label: '4.Tỷ lệ chốt' },
];

const PER_PAGE_OPTIONS = [20, 50, 100, 200, 500, 1000].map((value) => ({ id: String(value), label: String(value) }));

function currentQuery() {
    if (typeof window === 'undefined') return new URLSearchParams();
    return new URLSearchParams(window.location.search);
}

function todayIso() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function cleanPayload(values) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false),
    );
}

function buildInitialFilters() {
    const params = currentQuery();
    return {
        date_type: params.get('date_type') || '',
        date_from: params.get('date_from') || todayIso(),
        date_to: params.get('date_to') || todayIso(),
        no_closing_date_limit: params.get('no_closing_date_limit') === '1',
        sale_leader_id: params.get('sale_leader_id') || '',
        sale_team_id: params.get('sale_team_id') || '',
        operation_stage: params.get('operation_stage') || '',
        sort_metric: params.get('sort_metric') || 'total_revenue',
        per_page: params.get('per_page') || '20',
    };
}

function number(value) {
    return numberFormatter.format(Number(value) || 0);
}

function percent(value) {
    const numeric = Number(value) || 0;
    return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(2)} %`;
}

function money(value) {
    return currencyFormatter.format(Number(value) || 0).replace(/\s?₫$/, '').trim();
}

function SaleCell({ row }) {
    const sale = String(row.sale ?? '').trim();
    const account = String(row.sale_account ?? '').trim();
    if (!sale && !account) return <span>Tổng</span>;
    return (
        <span className="ps-operation-conversion-sale">
            <span>{sale || 'Chưa phân sale'}</span>
            {account ? <small> ({account})</small> : null}
        </span>
    );
}

function MetricCells({ row, stage }) {
    return (
        <>
            <td className="text-center nowrap">{number(row[`${stage}_contacts`])}</td>
            <td className="text-center nowrap">{number(row[`${stage}_closed`])}</td>
            <td className="text-center nowrap">{percent(row[`${stage}_rate`])}</td>
            <td className="text-center nowrap">{money(row[`${stage}_revenue`])}</td>
        </>
    );
}

function ReportRow({ row, stages, className = '', isTotal = false }) {
    return (
        <tr className={className}>
            <td className="text-center">{isTotal ? '' : row.index}</td>
            <td className="text-left"><SaleCell row={isTotal ? { sale: 'Tổng' } : row} /></td>
            <td className="text-center nowrap">{number(row.total_contacts)}</td>
            <td className="text-center nowrap">{number(row.total_closed)}</td>
            <td className="text-center nowrap">{percent(row.total_rate)}</td>
            <td className="text-center nowrap">{money(row.total_revenue ?? row.revenue)}</td>
            {stages.map(({ key }) => <MetricCells key={key} row={row} stage={key} />)}
        </tr>
    );
}

export default function OperationConversionReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/operation-conversion',
    pageRuntimeError = null,
}) {
    const [draft, setDraft] = useState(buildInitialFilters);
    const stages = useMemo(() => {
        const fromSummary = Array.isArray(summary?.stages) ? summary.stages : [];
        if (fromSummary.length) {
            return fromSummary.map((stage) => ({ key: stage.key, label: stage.label }));
        }
        return ALL_STAGES;
    }, [summary]);
    const totals = summary?.totals || null;
    const queryFilters = useMemo(() => {
        const payload = {
            ...draft,
            no_closing_date_limit: draft.no_closing_date_limit ? '1' : '',
        };
        return cleanPayload(payload);
    }, [draft]);

    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => {
        router.get(routeUrl, { ...queryFilters, page: 1 }, { preserveScroll: true, preserveState: false, replace: true });
    };

    return (
        <AppLayout activeMenuCode="4.6.1">
            <Head title={schema?.title ?? 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp'} />
            <div className="pushsale-page ps-operation-conversion-report" data-page-code="4.6.1">
                {pageRuntimeError && (
                    <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>
                )}

                <PageHeader
                    title={schema?.title ?? 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp'}
                    pageCode="4.6.1"
                    className="ps-operation-conversion-header"
                    defaultCollapsed={false}
                    filters={(
                        <div className="ps-operation-conversion-primary">
                            <PushsaleSelect
                                value={draft.date_type}
                                options={DATE_TYPE_OPTIONS}
                                placeholder="--Chuẩn Pushsale--"
                                onChange={(value) => set('date_type', value)}
                            />
                            <PushsaleDateRange filters={draft} onChange={set} className="ps-operation-conversion-date" />
                            <label className="ps-operation-conversion-check">
                                <input
                                    type="checkbox"
                                    checked={Boolean(draft.no_closing_date_limit)}
                                    onChange={(event) => set('no_closing_date_limit', event.target.checked)}
                                />
                                <span>Không giới hạn ngày chốt</span>
                            </label>
                        </div>
                    )}
                    actions={(
                        <>
                            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                            <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label="Xuất Excel" />
                        </>
                    )}
                    advanced={(
                        <div className="ps-operation-conversion-filter-row ps-adv-filter-panel">
                            <PushsaleSelect
                                value={draft.sale_leader_id}
                                options={filterOptions.saleLeaders ?? []}
                                placeholder="--Trưởng nhóm--"
                                onChange={(value) => set('sale_leader_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.sale_team_id}
                                options={filterOptions.saleTeams ?? filterOptions.teams ?? []}
                                placeholder="--Chọn nhóm--"
                                onChange={(value) => set('sale_team_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.operation_stage}
                                options={ALL_STAGES.map(({ key, label }) => ({ id: key, label }))}
                                placeholder="--Tác nghiệp--"
                                onChange={(value) => set('operation_stage', value)}
                            />
                            <PushsaleSelect
                                value={draft.sort_metric}
                                options={METRIC_OPTIONS}
                                placeholder="1.Doanh số tổng"
                                onChange={(value) => set('sort_metric', value)}
                            />
                            <PushsaleSelect
                                value={draft.per_page}
                                options={PER_PAGE_OPTIONS}
                                placeholder="20"
                                onChange={(value) => set('per_page', value)}
                            />
                        </div>
                    )}
                />

                <div className="dragscroll1 tableFixHead ps-operation-conversion-table-wrap">
                    <table className="table table-bordered table-striped" id="tblData">
                        <thead>
                            <tr className="drags-area">
                                <th className="text-center" rowSpan="2">STT</th>
                                <th className="text-center" rowSpan="2">SALE</th>
                                <th className="text-center" rowSpan="2">Tổng<br />contact</th>
                                <th className="text-center" rowSpan="2">Tổng<br />chốt đơn</th>
                                <th className="text-center" rowSpan="2">Tổng<br />tỷ lệ</th>
                                <th className="text-center" rowSpan="2">Tổng doanh<br />số</th>
                                {stages.map(({ key, label }) => (
                                    <th className="text-center" key={key} colSpan="4">{label}</th>
                                ))}
                            </tr>
                            <tr className="drags-area">
                                {stages.map(({ key }) => (
                                    <Fragment key={key}>
                                        <th className="text-center">Số contact</th>
                                        <th className="text-center">Chốt đơn</th>
                                        <th className="text-center">Tỷ lệ chốt</th>
                                        <th className="text-center">Doanh số</th>
                                    </Fragment>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {totals && rows.length > 0 && <ReportRow row={totals} stages={stages} className="rowTong" isTotal />}
                            {rows.map((row, index) => (
                                <ReportRow
                                    key={`${row.sale_id ?? row.sale}-${index}`}
                                    row={{ ...row, index: (pagination.from || 1) + index }}
                                    stages={stages}
                                />
                            ))}
                            {!rows.length && (
                                <tr>
                                    <td colSpan={6 + stages.length * 4} className="text-center ps-operation-conversion-empty">
                                        Chưa có dữ liệu phù hợp với bộ lọc.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="ps-operation-conversion-bottom-pager">
                    <PushsalePagination
                        meta={pagination}
                        routeUrl={routeUrl}
                        filters={queryFilters}
                        itemLabel="sale"
                        perPageOptions={[20, 50, 100, 200, 500, 1000]}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
