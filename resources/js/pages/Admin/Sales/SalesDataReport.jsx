import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { ReportProgressCell } from '@/components/reports/ReportProgressCell';
import { SaleNameCell } from '@/components/reports/SaleNameCell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { formatReportNumber } from '@/components/reports/reportFormat';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
} from '@/components/reports/PushsaleReportChrome';
import { cleanInertiaFilters, readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';

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
        sale_id: '',
        product_id: '',
        per_page: '50',
    });
}

export default function SalesDataReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/data',
    pageRuntimeError = null,
}) {
    const title = schema?.title ?? 'Báo cáo data sale';
    const { draft, set, apply: search } = useInertiaFilters(routeUrl, buildInitialFilters(), {
        sync: false,
        clean: true,
    });
    const [selected, setSelected] = useState([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const queryFilters = useMemo(() => cleanInertiaFilters(draft), [draft]);
    const totals = summary?.totals || null;
    const form = useForm({ sale_ids: [], receive_data: true });
    const toggleRow = (saleId) => {
        setSelected((current) => (
            current.includes(saleId) ? current.filter((id) => id !== saleId) : [...current, saleId]
        ));
    };
    const submitReceive = (receiveData) => {
        form.transform(() => ({ sale_ids: selected, receive_data: receiveData }));
        form.post('/admin/sales/reports/data/receive-data', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setSelected([]);
            },
        });
    };

    return (
        <AppLayout activeMenuCode="4.6.4">
            <Head title={title} />
            <div className="pushsale-page ps-sales-leader-report ps-sales-data-report" data-page-code="4.6.4">
                {pageRuntimeError && (
                    <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>
                )}

                <PageHeader
                    title={title}
                    pageCode="4.6.4"
                    className="ps-sales-leader-header"
                    defaultCollapsed={false}
                    filters={(
                        <div className="ps-sales-leader-primary">
                            <ReportFilterField field="date_type" draft={draft} onChange={set} filterOptions={filterOptions} />
                            <PushsaleDateRange filters={draft} onChange={set} />
                        </div>
                    )}
                    actions={(
                        <>
                            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                            <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label="Xuất Excel" />
                            <button type="button" className="btn btn-sm btn-default" disabled={!selected.length} onClick={() => setDialogOpen(true)}>
                                <i className="fa fa-gears" /> Cập nhật nhận dữ liệu
                            </button>
                        </>
                    )}
                    advanced={(
                        <div className="ps-sales-leader-advanced ps-adv-filter-panel">
                            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 6 }}>
                                <ReportFilterField field="sale_leader_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="sale_team_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="sale_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="per_page" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <a className="ps-sales-data-unassigned" href="/admin/hr/lead-distribution">
                                    Số contact chưa chia (<span>{formatReportNumber(summary?.unassigned_contacts ?? 0)}</span>)
                                </a>
                            </div>
                        </div>
                    )}
                />

                <div className="dragscroll1 tableFixHead ps-sales-leader-table-wrap">
                    <table className="table table-bordered table-striped" id="tblData">
                        <thead>
                            <tr>
                                <th className="text-center" rowSpan="2">STT</th>
                                <th className="text-center" rowSpan="2">Sale</th>
                                <th className="text-center" rowSpan="2">Contact nhận</th>
                                <th className="text-center" rowSpan="2">Contact trùng</th>
                                <th className="text-center" rowSpan="2">Contact không trùng</th>
                                <th className="text-center" colSpan="2">Chỉ số hôm qua</th>
                                <th className="text-center" colSpan="2">Chỉ số tháng trước</th>
                                <th className="text-center" colSpan="2">Chỉ số tháng này</th>
                                <th className="text-center" rowSpan="2">Nhận dữ liệu</th>
                            </tr>
                            <tr>
                                <th className="text-center">% chốt đơn</th>
                                <th className="text-center">Doanh số</th>
                                <th className="text-center">% chốt đơn</th>
                                <th className="text-center">Doanh số</th>
                                <th className="text-center">% chốt đơn</th>
                                <th className="text-center">Doanh số</th>
                            </tr>
                            {totals && (
                                <tr className="rowsum">
                                    <td colSpan="2" className="text-center font-weight-bold">Tổng:</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.received)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.duplicate)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.unique)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.yesterday_revenue)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.last_month_revenue)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.this_month_revenue)}</td>
                                    <td />
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={`${row.sale_id}-${index}`}>
                                    <td className="text-center">
                                        <label className="ps-sales-data-check">
                                            <input type="checkbox" checked={selected.includes(row.sale_id)} onChange={() => toggleRow(row.sale_id)} />
                                            <span>{(pagination.from || 1) + index}</span>
                                        </label>
                                    </td>
                                    <td><SaleNameCell row={row} /></td>
                                    <ReportProgressCell value={row.received} fillWhenNoMax />
                                    <ReportProgressCell value={row.duplicate} fillWhenNoMax />
                                    <ReportProgressCell value={row.unique} fillWhenNoMax />
                                    <ReportProgressCell value={row.yesterday_rate} format="percent" />
                                    <ReportProgressCell value={row.yesterday_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.last_month_rate} format="percent" />
                                    <ReportProgressCell value={row.last_month_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.this_month_rate} format="percent" />
                                    <ReportProgressCell value={row.this_month_revenue} fillWhenNoMax />
                                    <td className="text-center">{row.receive_data ? 'Có' : 'Không'}</td>
                                </tr>
                            ))}
                            {!rows.length && <TableEmptyRow colSpan={12} />}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={queryFilters} itemLabel="sale" perPageOptions={[20, 50, 100, 200, 500, 1000]} />

                {dialogOpen && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Cập nhật nhận dữ liệu</h4>
                            <p>Đã chọn {selected.length} sale.</p>
                            <div className="ps-sales-leader-dialog__actions">
                                <button type="button" className="btn btn-sm btn-primary" disabled={form.processing} onClick={() => submitReceive(true)}>Bật nhận dữ liệu</button>
                                <button type="button" className="btn btn-sm btn-default" disabled={form.processing} onClick={() => submitReceive(false)}>Tắt nhận dữ liệu</button>
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialogOpen(false)}>Đóng</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
