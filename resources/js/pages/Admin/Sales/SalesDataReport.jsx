import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
const DATE_TYPE_OPTIONS = [
    { id: 'sale_received_data', label: 'Ngày sale nhận data' },
    { id: 'data_arrival', label: 'Ngày data về hệ thống' },
    { id: 'care_update', label: 'Ngày sale tác nghiệp' },
    { id: 'closing_date', label: 'Ngày sale chốt đơn' },
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
        date_type: params.get('date_type') || 'sale_received_data',
        date_from: params.get('date_from') || todayIso(),
        date_to: params.get('date_to') || todayIso(),
        sale_leader_id: params.get('sale_leader_id') || '',
        sale_team_id: params.get('sale_team_id') || '',
        sale_id: params.get('sale_id') || '',
        product_id: params.get('product_id') || '',
        per_page: params.get('per_page') || '50',
    };
}

function num(value) {
    return numberFormatter.format(Number(value) || 0);
}

function pct(value) {
    const numeric = Number(value) || 0;
    return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(2)}%`;
}

function ProgressCell({ value, format = 'number', maxRatio = 100 }) {
    const numeric = Number(value) || 0;
    const width = format === 'percent' ? Math.min(100, Math.max(0, numeric)) : Math.min(100, maxRatio);
    return (
        <td className="tdProgress">
            <div className="box-progress">
                <div className="progress">
                    <div className="progress-bar" style={{ width: `${width}%` }} />
                </div>
                <span className="progress-text">{format === 'percent' ? pct(value) : num(value)}</span>
            </div>
        </td>
    );
}

function SaleName({ row }) {
    const sale = String(row.sale ?? '').trim() || 'Chưa phân sale';
    const account = String(row.sale_account ?? '').trim();
    return <span>{sale}{account ? <small> ({account})</small> : null}</span>;
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
    const [draft, setDraft] = useState(buildInitialFilters);
    const [selected, setSelected] = useState([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const queryFilters = useMemo(() => cleanPayload(draft), [draft]);
    const totals = summary?.totals || null;
    const form = useForm({ sale_ids: [], receive_data: true });
    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => {
        router.get(routeUrl, { ...cleanPayload(draft), page: 1 }, { preserveScroll: true, preserveState: false, replace: true });
    };
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
                            <PushsaleSelect value={draft.date_type} options={DATE_TYPE_OPTIONS} placeholder="Ngày sale nhận data" onChange={(value) => set('date_type', value)} />
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
                            <div className="ps-adv-filter-row">
                                <PushsaleSelect value={draft.sale_leader_id} options={filterOptions.saleLeaders ?? []} placeholder="--Trưởng nhóm--" onChange={(value) => set('sale_leader_id', value)} />
                                <PushsaleSelect value={draft.sale_team_id} options={filterOptions.saleTeams ?? filterOptions.teams ?? []} placeholder="--Chọn nhóm--" onChange={(value) => set('sale_team_id', value)} />
                                <PushsaleSelect value={draft.sale_id} options={filterOptions.sales ?? filterOptions.salesUsers ?? []} placeholder="-- Chọn sale --" onChange={(value) => set('sale_id', value)} />
                                <PushsaleSelect value={draft.product_id} options={filterOptions.products ?? []} placeholder="-- Sản phẩm --" onChange={(value) => set('product_id', value)} />
                                <PushsaleSelect value={draft.per_page} options={PER_PAGE_OPTIONS} placeholder="50" onChange={(value) => set('per_page', value)} />
                                <a className="ps-sales-data-unassigned" href="/admin/hr/lead-distribution">
                                    Số contact chưa chia (<span>{num(summary?.unassigned_contacts ?? 0)}</span>)
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
                                    <td className="text-center font-weight-bold">{num(totals.received)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.duplicate)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.unique)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{num(totals.yesterday_revenue)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{num(totals.last_month_revenue)}</td>
                                    <td className="text-center font-weight-bold">—</td>
                                    <td className="text-center font-weight-bold">{num(totals.this_month_revenue)}</td>
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
                                    <td><SaleName row={row} /></td>
                                    <ProgressCell value={row.received} />
                                    <ProgressCell value={row.duplicate} />
                                    <ProgressCell value={row.unique} />
                                    <ProgressCell value={row.yesterday_rate} format="percent" />
                                    <ProgressCell value={row.yesterday_revenue} />
                                    <ProgressCell value={row.last_month_rate} format="percent" />
                                    <ProgressCell value={row.last_month_revenue} />
                                    <ProgressCell value={row.this_month_rate} format="percent" />
                                    <ProgressCell value={row.this_month_revenue} />
                                    <td className="text-center">{row.receive_data ? 'Có' : 'Không'}</td>
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr><td colSpan={12} className="text-center">Chưa có dữ liệu phù hợp với bộ lọc.</td></tr>
                            )}
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
