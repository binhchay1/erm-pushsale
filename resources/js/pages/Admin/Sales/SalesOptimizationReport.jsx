import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PageHeader } from '@/components/layout/PageHeader';
import { useConfirm } from '@/hooks/use-confirm';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { SaleNameCell } from '@/components/reports/SaleNameCell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import {
    formatReportNumber,
    formatReportPercent,
} from '@/components/reports/reportFormat';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
} from '@/components/reports/PushsaleReportChrome';
import { cleanInertiaFilters, readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';
import {
    leaderOptionsFrom,
    OptimizationAlertsDialog,
    OptimizationCatalogDialog,
    OptimizationTargetsDialog,
} from '@/pages/Admin/Sales/optimization/SalesOptimizationDialogs';
import { useT } from '@/providers/I18nProvider';

function todayIso() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function buildInitialFilters() {
    return readQueryFilters({
        date_type: 'sale_received_data',
        date_from: todayIso(),
        date_to: todayIso(),
        sale_leader_id: '',
        sale_team_id: '',
        parent_product_id: '',
        product_id: '',
        include_saturday: false,
        include_sunday: false,
        per_page: '50',
    });
}

function num(value) {
    return formatReportNumber(value, { empty: '' });
}

function pct(value) {
    return formatReportPercent(value, { empty: '', spaceBeforeSuffix: false });
}

function OptCell({ value, variant = 'plain', stacked = false }) {
    return (
        <td className={`text-center ps-opt-td ps-opt-td--${variant}`}>
            <div className={variant === 'revenue' ? 'doanh_so_ds' : 'doanh_so'}>{value}</div>
            {stacked && (
                <>
                    <div className={variant === 'revenue' ? 'doanh_so_mau_ds' : 'doanh_so_mau'} />
                    <div className={variant === 'revenue' ? 'doanh_so_mau_ds' : 'doanh_so_mau'} />
                </>
            )}
        </td>
    );
}

function MetricCell({ actual, target = null, ratio = null }) {
    return (
        <td className="text-center ps-opt-td ps-opt-td--pink">
            <div className="doanh_so">{actual}</div>
            {target !== null && target !== undefined && target !== '' && (
                <div className="doanh_so_mau">{target}</div>
            )}
            {ratio !== null && ratio !== undefined && ratio !== '' && (
                <div className="doanh_so_mau">{ratio}</div>
            )}
        </td>
    );
}

export default function SalesOptimizationReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/optimization',
    pageRuntimeError = null,
}) {
    const t = useT();
    const title = schema?.title ?? t('reports.sales_optimization.title');
    const { ask } = useConfirm();
    const { draft, set, visit } = useInertiaFilters(routeUrl, buildInitialFilters(), { sync: false });
    const [dialog, setDialog] = useState(null);
    const [savingReceiveId, setSavingReceiveId] = useState(null);
    const queryFilters = useMemo(() => cleanInertiaFilters({
        ...draft,
        include_saturday: draft.include_saturday ? '1' : '',
        include_sunday: draft.include_sunday ? '1' : '',
    }), [draft]);
    const totals = summary?.totals || null;
    const thresholds = summary?.thresholds || { low: 80, high: 100 };
    const levels = Array.isArray(summary?.levels) ? summary.levels : [];
    const catalogs = Array.isArray(summary?.catalogs) ? summary.catalogs : [];
    const targetMap = summary?.target_map || {};
    const leaderOptions = useMemo(() => leaderOptionsFrom(filterOptions), [filterOptions]);
    const selectedLeader = useMemo(
        () => leaderOptions.find((option) => String(option.id) === String(draft.sale_leader_id || '')),
        [leaderOptions, draft.sale_leader_id],
    );
    const search = () => visit({ ...queryFilters, page: 1 });

    const openLevelsDialog = () => {
        if (!draft.sale_leader_id) {
            toast.error(t('reports.sales_optimization.need_leader'));
            return;
        }
        setDialog('levels');
    };

    const confirmReceiveToggle = async (saleId, currentValue) => {
        if (!saleId || savingReceiveId) {
            return;
        }

        const ok = await ask({
            description: 'Bạn chắc chắn thực hiện thao tác với sale này ???',
            confirmLabel: 'OK',
            cancelLabel: 'Cancel',
        });
        if (!ok) {
            return;
        }

        setSavingReceiveId(saleId);
        router.post('/admin/sales/reports/optimization/receive-data', {
            sale_ids: [saleId],
            receive_data: !currentValue,
        }, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('reports.sales_optimization.receive_ok')),
            onError: () => toast.error(t('reports.sales_optimization.receive_fail')),
            onFinish: () => setSavingReceiveId(null),
        });
    };

    return (
        <AppLayout activeMenuCode="4.6.5">
            <Head title={title} />
            <div className="pushsale-page ps-sales-leader-report ps-sales-optimization-report" data-page-code="4.6.5">
                {pageRuntimeError && (
                    <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>
                )}

                <PageHeader
                    title={title}
                    pageCode="4.6.5"
                    className="ps-sales-leader-header"
                    defaultCollapsed={false}
                    filters={(
                        <div className="ps-sales-leader-primary">
                            <ReportFilterField field="date_type" draft={draft} onChange={set} filterOptions={filterOptions} />
                            <PushsaleDateRange filters={draft} onChange={set} />
                            <label className="ps-operation-conversion-check">
                                <input type="checkbox" checked={Boolean(draft.include_saturday)} onChange={(event) => set('include_saturday', event.target.checked)} />
                                <span>Tính thứ 7</span>
                            </label>
                            <label className="ps-operation-conversion-check">
                                <input type="checkbox" checked={Boolean(draft.include_sunday)} onChange={(event) => set('include_sunday', event.target.checked)} />
                                <span>Tính chủ nhật</span>
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
                        <div className="ps-sales-leader-advanced ps-adv-filter-panel">
                            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 5 }}>
                                <ReportFilterField field="sale_leader_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="sale_team_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="parent_product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="per_page" draft={draft} onChange={set} filterOptions={filterOptions} />
                            </div>
                        </div>
                    )}
                />

                <div className="ps-sales-opt-toolbar">
                    <div className="ps-sales-opt-toolbar__actions">
                        <div className="ps-sales-opt-toolbar__actions-col">
                            <button type="button" className="btn btn-sm btn-primary" onClick={openLevelsDialog}>
                                <i className="fa fa-list" /> {t('reports.sales_optimization.btn_levels')}
                            </button>
                            <button
                                type="button"
                                className="btn btn-sm btn-default"
                                onClick={() => setDialog('receive-bulk')}
                                title={t('reports.sales_optimization.btn_receive')}
                            >
                                <i className="fa fa-gears" /> {t('reports.sales_optimization.btn_receive')}
                            </button>
                        </div>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('alerts')}>
                            <i className="fa fa-asterisk" /> {t('reports.sales_optimization.btn_alerts')}
                        </button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('targets')}>
                            <i className="fa fa-plus" /> {t('reports.sales_optimization.btn_targets')}
                        </button>
                    </div>
                    <div className="ps-sales-opt-legend">
                        {levels.map((level) => (
                            <div key={level.label} className="ps-sales-opt-legend__item">
                                <span className={`ps-sales-opt-swatch tone-${level.tone}`} />
                                <span>{level.label}</span>
                            </div>
                        ))}
                        <div className="ps-sales-opt-legend__note">
                            <strong>{t('reports.sales_optimization.legend_actual')}</strong>
                            {' - '}
                            <span>{t('reports.sales_optimization.legend_target')}</span>
                            {' - '}
                            <span>{t('reports.sales_optimization.legend_ratio')}</span>
                        </div>
                    </div>
                </div>

                <div className="dragscroll1 tableFixHead ps-sales-leader-table-wrap">
                    <table className="table table-bordered table-striped" id="tableReport">
                        <colgroup>
                            <col className="ps-opt-c-stt" />
                            <col className="ps-opt-c-sale" />
                            <col className="ps-opt-c-receive" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-4" />
                            <col className="ps-opt-c-4" />
                            <col className="ps-opt-c-4" />
                            <col className="ps-opt-c-4" />
                            <col className="ps-opt-c-7" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-7" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-6" />
                            <col className="ps-opt-c-7" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th className="text-center" rowSpan="2">STT</th>
                                <th className="text-center" rowSpan="2">Sale</th>
                                <th className="text-center" rowSpan="2">Nhận dữ liệu</th>
                                <th className="text-center" rowSpan="2">Doanh số tạm tính</th>
                                <th className="text-center" rowSpan="2">Doanh số thành công</th>
                                <th className="text-center" rowSpan="2">Contact tổng</th>
                                <th className="text-center" colSpan="3">Contact được chia</th>
                                <th className="text-center" rowSpan="2">Tổng cuộc gọi nghe máy/Tổng cuộc gọi ra</th>
                                <th className="text-center" rowSpan="2">Tổng thời gian gọi ra</th>
                                <th className="text-center" rowSpan="2">Thời gian gọi Trung bình</th>
                                <th className="text-center" rowSpan="2">Số chốt đơn/Cuộc gọi ra bắt máy (%)</th>
                                <th className="text-center" rowSpan="2">Contact chốt đơn</th>
                                <th className="text-center" rowSpan="2">Tỷ lệ chốt đơn</th>
                                <th className="text-center" rowSpan="2">Giá trị TB đơn</th>
                                <th className="text-center" rowSpan="2">Số sản phẩm/Đơn</th>
                                <th className="text-center" rowSpan="2">Contact chưa tác nghiệp</th>
                                <th className="text-center" rowSpan="2">Doanh số tạm tính/Contact được chia</th>
                                <th className="text-center" rowSpan="2">Doanh số hủy</th>
                                <th className="text-center" rowSpan="2">Doanh số hoàn</th>
                            </tr>
                            <tr>
                                <th className="text-center">Tổng</th>
                                <th className="text-center">Trùng</th>
                                <th className="text-center">Không Trùng</th>
                            </tr>
                            {totals && (
                                <tr className="rowsum">
                                    <td colSpan="3" className="text-center font-weight-bold">Tổng:</td>
                                    <td className="text-center font-weight-bold">{num(totals.provisional_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.success_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.contacts)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.allocated_total)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.allocated_duplicate)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.allocated_unique)}</td>
                                    <td /><td /><td /><td />
                                    <td className="text-center font-weight-bold">{num(totals.closed_contacts)}</td>
                                    <td className="text-center font-weight-bold">{pct(totals.close_rate)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.avg_order_value)}</td>
                                    <td className="text-center font-weight-bold">{totals.products_per_order ?? ''}</td>
                                    <td className="text-center font-weight-bold">{num(totals.untouched)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.revenue_per_contact)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.cancelled_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.returned_revenue)}</td>
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={`${row.sale_id}-${index}`}>
                                    <td className="text-center">{(pagination.from || 1) + index}</td>
                                    <td className="ps-opt-td ps-opt-td--sale">
                                        <SaleNameCell row={row} />
                                    </td>
                                    <td className="text-center ps-opt-td">
                                        {row.sale_id ? (
                                            <span className="ps-sales-receive-check myCheckChange" data-saleid={row.sale_id}>
                                                <label title={row.receive_data ? 'Đang nhận dữ liệu' : 'Không nhận dữ liệu'}>
                                                    <input
                                                        type="checkbox"
                                                        checked={Boolean(row.receive_data)}
                                                        disabled={savingReceiveId === row.sale_id}
                                                        onChange={() => confirmReceiveToggle(row.sale_id, Boolean(row.receive_data))}
                                                    />
                                                </label>
                                            </span>
                                        ) : (
                                            <span>Không</span>
                                        )}
                                    </td>
                                    <OptCell value={num(row.provisional_revenue)} variant="revenue" stacked />
                                    <OptCell value={num(row.success_revenue)} variant="pink" stacked />
                                    <td className="text-center ps-opt-td">{num(row.contacts)}</td>
                                    <OptCell value={num(row.allocated_total)} variant="pink" stacked />
                                    <OptCell value={num(row.allocated_duplicate)} variant="pink" stacked />
                                    <OptCell value={num(row.allocated_unique)} variant="pink" stacked />
                                    <td className="text-center ps-opt-td">(/)</td>
                                    <td className="text-center ps-opt-td">{row.call_duration_seconds ?? ''}</td>
                                    <td className="text-center ps-opt-td">{row.avg_call_seconds ?? ''}</td>
                                    <td className="text-center ps-opt-td">%</td>
                                    <OptCell value={num(row.closed_contacts)} variant="pink" stacked />
                                    <MetricCell
                                        actual={pct(row.close_rate)}
                                        target={pct(row.close_rate_target)}
                                        ratio={pct(row.close_rate_ratio)}
                                    />
                                    <OptCell value={num(row.avg_order_value)} variant="pink" stacked />
                                    <OptCell value={row.products_per_order ?? ''} variant="pink" stacked />
                                    <OptCell value={num(row.untouched)} variant="green" stacked />
                                    <OptCell value={num(row.revenue_per_contact)} variant="green" stacked />
                                    <td className="text-center ps-opt-td">{num(row.cancelled_revenue)}</td>
                                    <td className="text-center ps-opt-td">{num(row.returned_revenue)}</td>
                                </tr>
                            ))}
                            {!rows.length && <TableEmptyRow colSpan={21} />}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={queryFilters} itemLabel="sale" perPageOptions={[20, 50, 100, 200, 500, 1000]} />

                <OptimizationCatalogDialog
                    open={dialog === 'levels'}
                    onOpenChange={(open) => setDialog(open ? 'levels' : null)}
                    catalogs={catalogs.filter((row) => {
                        if (!draft.sale_leader_id) return true;
                        return row.leader_user_id == null
                            || String(row.leader_user_id) === String(draft.sale_leader_id);
                    })}
                    leaderUserId={draft.sale_leader_id}
                    leaderLabel={selectedLeader?.label ?? selectedLeader?.name ?? ''}
                />
                <OptimizationAlertsDialog
                    open={dialog === 'alerts'}
                    onOpenChange={(open) => setDialog(open ? 'alerts' : null)}
                    thresholds={thresholds}
                />
                <OptimizationTargetsDialog
                    open={dialog === 'targets'}
                    onOpenChange={(open) => setDialog(open ? 'targets' : null)}
                    filterOptions={filterOptions}
                    targetMap={targetMap}
                    draftLeaderId={draft.sale_leader_id}
                    draftTeamId={draft.sale_team_id}
                />

                {dialog === 'receive-bulk' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>{t('reports.sales_optimization.receive_help_title')}</h4>
                            <p>{t('reports.sales_optimization.receive_help_body')}</p>
                            <p className="text-muted">{t('reports.sales_optimization.receive_help_note')}</p>
                            <div className="ps-sales-leader-dialog__actions">
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>
                                    {t('reports.sales_optimization.close')}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
