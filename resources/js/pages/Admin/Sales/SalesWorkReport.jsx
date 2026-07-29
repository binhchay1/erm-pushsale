import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
    PushsaleSelect,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';

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
    { id: 'sale_received_data', label: 'Ngày sale nhận data' },
    { id: 'data_arrival', label: 'Ngày data về hệ thống' },
    { id: 'care_update', label: 'Ngày sale tác nghiệp' },
    { id: 'closing_date', label: 'Ngày sale chốt đơn' },
    { id: 'posting_date', label: 'Ngày đăng đơn' },
    { id: 'next_operation_date', label: 'Ngày sale tác nghiệp tiếp' },
];

const PER_PAGE_OPTIONS = [20, 50, 100, 200, 500, 1000].map((value) => ({ id: String(value), label: String(value) }));
const numberFormatter = new Intl.NumberFormat('vi-VN');

function currentQuery() {
    if (typeof window === 'undefined') return new URLSearchParams();
    return new URLSearchParams(window.location.search);
}

function todayIso() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function daysAgoIso(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);
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
        date_type: params.get('date_type') || 'sale_received_data',
        date_from: params.get('date_from') || daysAgoIso(6),
        date_to: params.get('date_to') || todayIso(),
        operation_stage: params.get('operation_stage') || '',
        product_id: params.get('product_id') || '',
        sale_id: params.get('sale_id') || '',
        sale_leader_id: params.get('sale_leader_id') || '',
        sale_team_id: params.get('sale_team_id') || '',
        per_page: params.get('per_page') || '50',
    };
}

function formatNumber(value) {
    return numberFormatter.format(Number(value) || 0);
}

function SaleName({ row }) {
    const sale = String(row.sale ?? '').trim() || 'Chưa phân sale';
    const account = String(row.sale_account ?? '').trim();
    return (
        <span className="ps-sale-name">
            {sale}
            {account ? <small> ({account})</small> : null}
        </span>
    );
}

export default function SalesWorkReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/work',
    pageRuntimeError = null,
}) {
    const title = schema?.title ?? 'Báo cáo công việc sale';
    const [draft, setDraft] = useState(buildInitialFilters);
    const stages = useMemo(() => {
        const fromSummary = Array.isArray(summary?.stages) ? summary.stages : [];
        if (fromSummary.length) {
            return fromSummary.map((stage) => ({ key: stage.key, label: stage.label }));
        }
        return ALL_STAGES;
    }, [summary]);
    const totals = summary?.totals || null;
    const queryFilters = useMemo(() => cleanPayload(draft), [draft]);
    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => {
        router.get(routeUrl, { ...cleanPayload(draft), page: 1 }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout activeMenuCode="4.6.2">
            <Head title={title} />
            <PushsalePageShell
                title={title}
                pageCode="4.6.2"
                className="ps-sale-work-page ps-report-toolbar-shell"
                headerClassName="ps-sale-work-header"
                bodyClassName="ps-sale-work-body"
                collapsible
                defaultFiltersCollapsed={false}
                primaryFilters={(
                    <div className="ps-sale-work-primary">
                        <PushsaleSelect
                            value={draft.date_type}
                            options={DATE_TYPE_OPTIONS}
                            placeholder="-- Kiểu ngày --"
                            onChange={(value) => set('date_type', value)}
                        />
                        <PushsaleDateRange filters={draft} onChange={set} className="ps-sale-work-date" />
                    </div>
                )}
                advancedFilters={(
                    <div className="ps-sale-work-advanced ps-adv-filter-panel">
                        <div className="ps-sale-work-advanced-row ps-adv-filter-row" style={{ '--ps-adv-cols': 6 }}>
                            <PushsaleSelect
                                value={draft.operation_stage}
                                options={ALL_STAGES.map(({ key, label }) => ({ id: key, label }))}
                                placeholder="-- Chọn tác nghiệp --"
                                onChange={(value) => set('operation_stage', value)}
                            />
                            <PushsaleSelect
                                value={draft.product_id}
                                options={filterOptions.products ?? filterOptions.productGroups ?? []}
                                placeholder="-- Chọn sản phẩm --"
                                onChange={(value) => set('product_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.sale_id}
                                options={filterOptions.sales ?? filterOptions.salesUsers ?? []}
                                placeholder="-- Chọn sale --"
                                onChange={(value) => set('sale_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.sale_leader_id}
                                options={filterOptions.saleLeaders ?? []}
                                placeholder="-- Trưởng nhóm sale --"
                                onChange={(value) => set('sale_leader_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.sale_team_id}
                                options={filterOptions.saleTeams ?? filterOptions.teams ?? []}
                                placeholder="-- Chọn nhóm sale --"
                                onChange={(value) => set('sale_team_id', value)}
                            />
                            <PushsaleSelect
                                value={draft.per_page}
                                options={PER_PAGE_OPTIONS}
                                placeholder="50"
                                onChange={(value) => set('per_page', value)}
                            />
                        </div>
                    </div>
                )}
                actions={(
                    <div className="ps-sale-work-actions">
                        <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                        <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label="Xuất Excel" />
                    </div>
                )}
                notice={pageRuntimeError ? (
                    <div className="pushsale-error-banner">
                        <i className="fa fa-exclamation-triangle" /> {pageRuntimeError}
                    </div>
                ) : null}
            >
                <div className="ps-sale-work-table-wrap">
                    <table className="table table-bordered table-striped ps-sale-work-table">
                        <thead>
                            <tr>
                                <th rowSpan="2" className="ps-col-stt">STT</th>
                                <th rowSpan="2" className="ps-col-sale">SALE</th>
                                <th rowSpan="2" className="ps-col-metric">Tổng<br />contact</th>
                                <th rowSpan="2" className="ps-col-metric">Tổng contact<br />chưa TN</th>
                                {stages.map(({ key, label }) => (
                                    <th key={key} colSpan="2">{label}</th>
                                ))}
                            </tr>
                            <tr>
                                {stages.map(({ key }) => (
                                    <Fragment key={key}>
                                        <th className="ps-col-metric">Số<br />contact</th>
                                        <th className="ps-col-metric">Chưa<br />TN</th>
                                    </Fragment>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? (
                                <>
                                    {totals && (
                                        <tr className="ps-sale-work-total">
                                            <td className="text-center" />
                                            <td className="ps-sale-name">Tổng:</td>
                                            <td className="text-center">{formatNumber(totals.total_contacts)}</td>
                                            <td className="text-center">{formatNumber(totals.untouched)}</td>
                                            {stages.map(({ key }) => (
                                                <Fragment key={key}>
                                                    <td className="text-center">{formatNumber(totals[`${key}_contacts`])}</td>
                                                    <td className="text-center">{formatNumber(totals[`${key}_untouched`])}</td>
                                                </Fragment>
                                            ))}
                                        </tr>
                                    )}
                                    {rows.map((row, index) => (
                                        <tr key={`${row.sale_id ?? row.sale}-${index}`}>
                                            <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                            <td><SaleName row={row} /></td>
                                            <td className="text-center">{formatNumber(row.total_contacts)}</td>
                                            <td className="text-center">{formatNumber(row.untouched)}</td>
                                            {stages.map(({ key }) => (
                                                <Fragment key={key}>
                                                    <td className="text-center">{formatNumber(row[`${key}_contacts`])}</td>
                                                    <td className="text-center">{formatNumber(row[`${key}_untouched`])}</td>
                                                </Fragment>
                                            ))}
                                        </tr>
                                    ))}
                                </>
                            ) : (
                                <tr>
                                    <td colSpan={4 + stages.length * 2} className="text-center">Chưa có dữ liệu phù hợp với bộ lọc.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="ps-sale-work-footer">
                    <PushsalePagination
                        meta={pagination}
                        routeUrl={routeUrl}
                        filters={queryFilters}
                        itemLabel="sale"
                        perPageOptions={[20, 50, 100, 200, 500, 1000]}
                    />
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
