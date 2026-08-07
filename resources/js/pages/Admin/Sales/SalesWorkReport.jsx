import { Head } from '@inertiajs/react';
import { Fragment, useMemo } from 'react';

import { useInertiaFilters } from '@/hooks/useInertiaFilters';
import {
    ReportFilterToolbar,
    cleanReportFilterPayload,
} from '@/components/reports/ReportFilterToolbar';
import { SaleNameCell } from '@/components/reports/SaleNameCell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { formatReportNumber } from '@/components/reports/reportFormat';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

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

export default function SalesWorkReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/work',
    pageRuntimeError = null,
}) {
    const t = useT();
    const title = translateReportText(t, schema?.title ?? 'Báo cáo công việc sale', schema?.title ?? t('reports.extra.sale-work.title'));
    const { draft, set, apply: search } = useInertiaFilters(routeUrl, buildInitialFilters(), {
        sync: false,
        clean: true,
    });
    const stages = useMemo(() => {
        const fromSummary = Array.isArray(summary?.stages) ? summary.stages : [];
        if (fromSummary.length) {
            return fromSummary.map((stage) => ({ key: stage.key, label: translateReportText(t, stage.label, stage.label) }));
        }
        // Fallback: server operationStages (menu 1.8.1), never hardcode call_1.
        return (filterOptions.operationStages ?? []).map((stage) => ({
            key: stage.value ?? stage.id ?? stage.key,
            label: translateReportText(t, stage.label ?? stage.name ?? stage.key, stage.label ?? stage.name ?? stage.key),
        }));
    }, [summary, filterOptions.operationStages, t]);
    const totals = summary?.totals || null;
    const queryFilters = useMemo(() => cleanReportFilterPayload(draft), [draft]);

    return (
        <AppLayout activeMenuCode="4.6.2">
            <Head title={title} />
            <ReportFilterToolbar
                title={title}
                pageCode="4.6.2"
                routeUrl={routeUrl}
                draft={draft}
                onChange={set}
                onSearch={search}
                filterOptions={filterOptions}
                className="ps-sale-work-page ps-report-toolbar-shell"
                headerClassName="ps-sale-work-header"
                bodyClassName="ps-sale-work-body"
                primaryClassName="ps-sale-work-primary"
                advancedClassName="ps-sale-work-advanced ps-adv-filter-panel"
                advancedRowClassName="ps-sale-work-advanced-row ps-adv-filter-row"
                actionsClassName="ps-sale-work-actions"
                advancedCols={6}
                primary={['date_type', 'date_from']}
                advanced={[
                    'operation_stage',
                    'product_id',
                    'sale_id',
                    'sale_leader_id',
                    'sale_team_id',
                    'per_page',
                ]}
                searchLabel={t('reports.pushsale.search')}
                exportLabel={t('reports.pushsale.export_excel')}
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
                                <th rowSpan="2" className="ps-col-stt">{t('reports.pushsale.stt')}</th>
                                <th rowSpan="2" className="ps-col-sale">{t('reports.pushsale.sale')}</th>
                                <th rowSpan="2" className="ps-col-metric">{t('reports.pushsale.total_contact')}</th>
                                <th rowSpan="2" className="ps-col-metric">{t('reports.pushsale.total_contact_untouched')}</th>
                                {stages.map(({ key, label }) => (
                                    <th key={key} colSpan="2">{label}</th>
                                ))}
                            </tr>
                            <tr>
                                {stages.map(({ key }) => (
                                    <Fragment key={key}>
                                        <th className="ps-col-metric">{t('reports.pushsale.contact_count')}</th>
                                        <th className="ps-col-metric">{t('reports.pushsale.untouched')}</th>
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
                                            <td className="ps-sale-name">{t('reports.pushsale.total_colon')}</td>
                                            <td className="text-center">{formatReportNumber(totals.total_contacts)}</td>
                                            <td className="text-center">{formatReportNumber(totals.untouched)}</td>
                                            {stages.map(({ key }) => (
                                                <Fragment key={key}>
                                                    <td className="text-center">{formatReportNumber(totals[`${key}_contacts`])}</td>
                                                    <td className="text-center">{formatReportNumber(totals[`${key}_untouched`])}</td>
                                                </Fragment>
                                            ))}
                                        </tr>
                                    )}
                                    {rows.map((row, index) => (
                                        <tr key={`${row.sale_id ?? row.sale}-${index}`}>
                                            <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                            <td><SaleNameCell row={row} className="ps-sale-name" /></td>
                                            <td className="text-center">{formatReportNumber(row.total_contacts)}</td>
                                            <td className="text-center">{formatReportNumber(row.untouched)}</td>
                                            {stages.map(({ key }) => (
                                                <Fragment key={key}>
                                                    <td className="text-center">{formatReportNumber(row[`${key}_contacts`])}</td>
                                                    <td className="text-center">{formatReportNumber(row[`${key}_untouched`])}</td>
                                                </Fragment>
                                            ))}
                                        </tr>
                                    ))}
                                </>
                            ) : (
                                <TableEmptyRow colSpan={4 + stages.length * 2} />
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
            </ReportFilterToolbar>
        </AppLayout>
    );
}
