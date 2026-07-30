import { Head } from '@inertiajs/react';
import { Fragment, useMemo } from 'react';

import { OperationStageSelect } from '@/components/filters/OperationStageSelect';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import {
    MoneyCell,
    NumberCell,
    PercentCell,
} from '@/components/reports/FormatCells';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { SaleNameCell } from '@/components/reports/SaleNameCell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
    PushsaleSelect,
} from '@/components/reports/PushsaleReportChrome';
import { reportPerPageOptions, resolveFilterOptions } from '@/config/reportFilters';
import { cleanInertiaFilters, readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';

const METRIC_OPTIONS = [
    { id: 'total_revenue', label: '1.Doanh số tổng' },
    { id: 'total_closed', label: '2.Số chốt đơn' },
    { id: 'total_contacts', label: '3.Số contact' },
    { id: 'total_rate', label: '4.Tỷ lệ chốt' },
];

function todayIso() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function buildInitialFilters() {
    return readQueryFilters({
        date_type: '',
        date_from: todayIso(),
        date_to: todayIso(),
        no_closing_date_limit: false,
        sale_leader_id: '',
        sale_team_id: '',
        operation_stage: '',
        sort_metric: 'total_revenue',
        per_page: '20',
    });
}

function MetricCells({ row, stage }) {
    return (
        <>
            <NumberCell value={row[`${stage}_contacts`]} />
            <NumberCell value={row[`${stage}_closed`]} />
            <PercentCell value={row[`${stage}_rate`]} empty="0 %" />
            <MoneyCell value={row[`${stage}_revenue`]} stripCurrencySymbol empty="0" />
        </>
    );
}

function ReportRow({ row, stages, className = '', isTotal = false }) {
    return (
        <tr className={className}>
            <td className="text-center">{isTotal ? '' : row.index}</td>
            <td className="text-left">
                <SaleNameCell
                    row={row}
                    isTotal={isTotal}
                    className={isTotal ? undefined : 'ps-operation-conversion-sale'}
                />
            </td>
            <NumberCell value={row.total_contacts} />
            <NumberCell value={row.total_closed} />
            <PercentCell value={row.total_rate} empty="0 %" />
            <MoneyCell value={row.total_revenue ?? row.revenue} stripCurrencySymbol empty="0" />
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
    const { draft, set, visit } = useInertiaFilters(routeUrl, buildInitialFilters(), { sync: false });
    const stages = useMemo(() => {
        const fromSummary = Array.isArray(summary?.stages) ? summary.stages : [];
        if (fromSummary.length) {
            return fromSummary.map((stage) => ({ key: stage.key, label: stage.label }));
        }
        return (filterOptions.operationStages ?? []).map((stage) => ({
            key: stage.value ?? stage.id ?? stage.key,
            label: stage.label ?? stage.name ?? stage.key,
        }));
    }, [summary, filterOptions.operationStages]);
    const totals = summary?.totals || null;
    const queryFilters = useMemo(() => cleanInertiaFilters({
        ...draft,
        no_closing_date_limit: draft.no_closing_date_limit ? '1' : '',
    }), [draft]);

    const search = () => visit({ ...queryFilters, page: 1 });

    const perPageOptions = resolveFilterOptions(filterOptions, 'perPageOptions');
    const resolvedPerPage = perPageOptions.length ? perPageOptions : reportPerPageOptions();

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
                            <ReportFilterField field="date_type" draft={draft} onChange={set} filterOptions={filterOptions} />
                            <PushsaleDateRange filters={draft} onChange={set} className="ps-operation-conversion-date" />
                            <ReportFilterField field="no_closing_date_limit" draft={draft} onChange={set} filterOptions={filterOptions} className="ps-operation-conversion-check" />
                        </div>
                    )}
                    actions={(
                        <>
                            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                            <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label="Xuất Excel" />
                        </>
                    )}
                    advanced={(
                        <div className="ps-operation-conversion-advanced ps-adv-filter-panel">
                            <div className="ps-operation-conversion-filter-row ps-adv-filter-row" style={{ '--ps-adv-cols': 5 }}>
                                <ReportFilterField field="sale_leader_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="sale_team_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <OperationStageSelect
                                    value={draft.operation_stage}
                                    filterOptions={filterOptions}
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
                                    options={resolvedPerPage}
                                    placeholder="20"
                                    onChange={(value) => set('per_page', value)}
                                />
                            </div>
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
                                <TableEmptyRow
                                    colSpan={6 + stages.length * 4}
                                    className="text-center ps-operation-conversion-empty"
                                />
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
