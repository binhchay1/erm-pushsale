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
    { id: 'posting_date', label: 'Ngày đăng đơn' },
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
        parent_product_id: params.get('parent_product_id') || '',
        product_id: params.get('product_id') || '',
        include_saturday: params.get('include_saturday') === '1',
        include_sunday: params.get('include_sunday') === '1',
        per_page: params.get('per_page') || '50',
    };
}

function num(value) {
    if (value === null || value === undefined || value === '') return '';
    return numberFormatter.format(Number(value) || 0);
}

function pct(value) {
    if (value === null || value === undefined || value === '') return '';
    const numeric = Number(value) || 0;
    return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(2)}%`;
}

function SaleName({ row }) {
    const sale = String(row.sale ?? '').trim() || 'Chưa phân sale';
    const account = String(row.sale_account ?? '').trim();
    return <span>{sale}{account ? <small> ({account})</small> : null}</span>;
}

function MetricCell({ actual, target = null, ratio = null, tone = 'average' }) {
    return (
        <td className={`text-center ps-opt-metric tone-${tone}`}>
            <div className="ps-opt-metric__actual">{actual}</div>
            {target !== null && target !== undefined && target !== '' && (
                <div className="ps-opt-metric__target">{target}</div>
            )}
            {ratio !== null && ratio !== undefined && ratio !== '' && (
                <div className="ps-opt-metric__ratio">{ratio}</div>
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
    const title = schema?.title ?? 'Báo cáo tối ưu Sale';
    const [draft, setDraft] = useState(buildInitialFilters);
    const [selected, setSelected] = useState([]);
    const [dialog, setDialog] = useState(null);
    const queryFilters = useMemo(() => {
        const payload = {
            ...draft,
            include_saturday: draft.include_saturday ? '1' : '',
            include_sunday: draft.include_sunday ? '1' : '',
        };
        return cleanPayload(payload);
    }, [draft]);
    const totals = summary?.totals || null;
    const thresholds = summary?.thresholds || { low: 80, high: 100 };
    const levels = Array.isArray(summary?.levels) ? summary.levels : [];
    const alertForm = useForm({ low_ratio: thresholds.low, high_ratio: thresholds.high });
    const targetForm = useForm({
        targets: [{ sale_user_id: '', metric_key: 'close_rate', target_value: 100 }],
    });
    const receiveForm = useForm({ sale_ids: [], receive_data: true });
    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => {
        router.get(routeUrl, { ...queryFilters, page: 1 }, { preserveScroll: true, preserveState: false, replace: true });
    };
    const toggleRow = (saleId) => {
        setSelected((current) => (current.includes(saleId) ? current.filter((id) => id !== saleId) : [...current, saleId]));
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
                            <PushsaleSelect value={draft.date_type} options={DATE_TYPE_OPTIONS} placeholder="Ngày sale nhận data" onChange={(value) => set('date_type', value)} />
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
                                <PushsaleSelect value={draft.sale_leader_id} options={filterOptions.saleLeaders ?? []} placeholder="--Trưởng nhóm--" onChange={(value) => set('sale_leader_id', value)} />
                                <PushsaleSelect value={draft.sale_team_id} options={filterOptions.saleTeams ?? filterOptions.teams ?? []} placeholder="--Chọn nhóm--" onChange={(value) => set('sale_team_id', value)} />
                                <PushsaleSelect value={draft.parent_product_id} options={filterOptions.productGroups ?? []} placeholder="--Sản phẩm cha--" onChange={(value) => set('parent_product_id', value)} />
                                <PushsaleSelect value={draft.product_id} options={filterOptions.products ?? []} placeholder="-- Sản phẩm --" onChange={(value) => set('product_id', value)} />
                                <PushsaleSelect value={draft.per_page} options={PER_PAGE_OPTIONS} placeholder="50" onChange={(value) => set('per_page', value)} />
                            </div>
                        </div>
                    )}
                />

                <div className="ps-sales-opt-toolbar">
                    <div className="ps-sales-opt-toolbar__actions">
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('levels')}>
                            <i className="fa fa-list" /> Thiết lập level mục tiêu
                        </button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('alerts')}>
                            <i className="fa fa-asterisk" /> Mức cảnh báo
                        </button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('targets')}>
                            <i className="fa fa-plus" /> Thiết lập mục tiêu sale
                        </button>
                        <button type="button" className="btn btn-sm btn-default" disabled={!selected.length} onClick={() => setDialog('receive')}>
                            <i className="fa fa-gears" /> Cập nhật nhận dữ liệu
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
                            <strong>Số liệu thực tế</strong> - <span>Chỉ tiêu</span> - <span>Tỷ lệ</span>
                        </div>
                    </div>
                </div>

                <div className="dragscroll1 tableFixHead ps-sales-leader-table-wrap">
                    <table className="table table-bordered table-striped" id="tableReport">
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
                                    <td colSpan="2" className="text-center font-weight-bold">Tổng:</td>
                                    <td />
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
                                <tr key={`${row.sale_id}-${index}`} className={`tone-${row.tone || 'average'}`}>
                                    <td className="text-center">
                                        <label className="ps-sales-data-check">
                                            <input type="checkbox" checked={selected.includes(row.sale_id)} onChange={() => toggleRow(row.sale_id)} />
                                            <span>{(pagination.from || 1) + index}</span>
                                        </label>
                                    </td>
                                    <td><SaleName row={row} /></td>
                                    <td className="text-center">{row.receive_data ? 'Có' : 'Không'}</td>
                                    <td className="text-center">{num(row.provisional_revenue)}</td>
                                    <td className="text-center">{num(row.success_revenue)}</td>
                                    <td className="text-center">{num(row.contacts)}</td>
                                    <td className="text-center">{num(row.allocated_total)}</td>
                                    <td className="text-center">{num(row.allocated_duplicate)}</td>
                                    <td className="text-center">{num(row.allocated_unique)}</td>
                                    <td className="text-center" />
                                    <td className="text-center">{row.call_duration_seconds ?? ''}</td>
                                    <td className="text-center">{row.avg_call_seconds ?? ''}</td>
                                    <td className="text-center" />
                                    <td className="text-center">{num(row.closed_contacts)}</td>
                                    <MetricCell
                                        actual={pct(row.close_rate)}
                                        target={pct(row.close_rate_target)}
                                        ratio={pct(row.close_rate_ratio)}
                                        tone={row.tone}
                                    />
                                    <td className="text-center">{num(row.avg_order_value)}</td>
                                    <td className="text-center">{row.products_per_order ?? ''}</td>
                                    <td className="text-center">{num(row.untouched)}</td>
                                    <td className="text-center">{num(row.revenue_per_contact)}</td>
                                    <td className="text-center">{num(row.cancelled_revenue)}</td>
                                    <td className="text-center">{num(row.returned_revenue)}</td>
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr><td colSpan={21} className="text-center">Chưa có dữ liệu phù hợp với bộ lọc.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={queryFilters} itemLabel="sale" perPageOptions={[20, 50, 100, 200, 500, 1000]} />

                {dialog === 'levels' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Thiết lập level mục tiêu</h4>
                            <p>Các cấp độ hiện tại (chỉ đọc — chỉnh ngưỡng cảnh báo để đổi màu):</p>
                            <ul>
                                {levels.map((level) => (
                                    <li key={level.label}>{level.label}: {level.min_ratio}%{level.max_ratio !== null ? ` – ${level.max_ratio}%` : '+'}</li>
                                ))}
                            </ul>
                            <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>Đóng</button>
                        </div>
                    </div>
                )}

                {dialog === 'alerts' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Mức cảnh báo</h4>
                            <label>Dưới (%)
                                <input className="form-control" type="number" value={alertForm.data.low_ratio} onChange={(event) => alertForm.setData('low_ratio', event.target.value)} />
                            </label>
                            <label>Trên (%)
                                <input className="form-control" type="number" value={alertForm.data.high_ratio} onChange={(event) => alertForm.setData('high_ratio', event.target.value)} />
                            </label>
                            <div className="ps-sales-leader-dialog__actions">
                                <button
                                    type="button"
                                    className="btn btn-sm btn-primary"
                                    disabled={alertForm.processing}
                                    onClick={() => alertForm.post('/admin/sales/reports/optimization/alerts', { preserveScroll: true, onSuccess: () => setDialog(null) })}
                                >
                                    Lưu
                                </button>
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>Đóng</button>
                            </div>
                        </div>
                    </div>
                )}

                {dialog === 'targets' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Thiết lập mục tiêu sale</h4>
                            <label>Sale
                                <select
                                    className="form-control"
                                    value={targetForm.data.targets[0]?.sale_user_id ?? ''}
                                    onChange={(event) => targetForm.setData('targets', [{
                                        ...targetForm.data.targets[0],
                                        sale_user_id: event.target.value || null,
                                    }])}
                                >
                                    <option value="">Mặc định toàn công ty</option>
                                    {(filterOptions.sales ?? filterOptions.salesUsers ?? []).map((option) => (
                                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                                    ))}
                                </select>
                            </label>
                            <label>Mục tiêu tỷ lệ chốt (%)
                                <input
                                    className="form-control"
                                    type="number"
                                    value={targetForm.data.targets[0]?.target_value ?? 100}
                                    onChange={(event) => targetForm.setData('targets', [{
                                        ...targetForm.data.targets[0],
                                        target_value: event.target.value,
                                    }])}
                                />
                            </label>
                            <div className="ps-sales-leader-dialog__actions">
                                <button
                                    type="button"
                                    className="btn btn-sm btn-primary"
                                    disabled={targetForm.processing}
                                    onClick={() => targetForm.post('/admin/sales/reports/optimization/targets', { preserveScroll: true, onSuccess: () => setDialog(null) })}
                                >
                                    Lưu
                                </button>
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>Đóng</button>
                            </div>
                        </div>
                    </div>
                )}

                {dialog === 'receive' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Cập nhật nhận dữ liệu</h4>
                            <p>Đã chọn {selected.length} sale.</p>
                            <div className="ps-sales-leader-dialog__actions">
                                <button
                                    type="button"
                                    className="btn btn-sm btn-primary"
                                    disabled={receiveForm.processing}
                                    onClick={() => {
                                        receiveForm.transform(() => ({ sale_ids: selected, receive_data: true }));
                                        receiveForm.post('/admin/sales/reports/optimization/receive-data', {
                                            preserveScroll: true,
                                            onSuccess: () => { setDialog(null); setSelected([]); },
                                        });
                                    }}
                                >
                                    Bật nhận dữ liệu
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-sm btn-default"
                                    disabled={receiveForm.processing}
                                    onClick={() => {
                                        receiveForm.transform(() => ({ sale_ids: selected, receive_data: false }));
                                        receiveForm.post('/admin/sales/reports/optimization/receive-data', {
                                            preserveScroll: true,
                                            onSuccess: () => { setDialog(null); setSelected([]); },
                                        });
                                    }}
                                >
                                    Tắt nhận dữ liệu
                                </button>
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>Đóng</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
