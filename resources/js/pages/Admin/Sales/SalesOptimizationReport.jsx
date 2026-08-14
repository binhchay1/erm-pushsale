import { Head, router, useForm } from '@inertiajs/react';
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

function optToneClass(tone = 'average', variant = 'metric') {
    const normalized = tone === 'bad' || tone === 'good' ? tone : 'average';
    if (normalized === 'average') {
        return '';
    }
    return variant === 'revenue'
        ? `ps-opt-tone-revenue-${normalized}`
        : `ps-opt-tone-metric-${normalized}`;
}

function OptValueCell({ value, tone = 'average', variant = 'metric', revenueLines = false }) {
    const actualClass = revenueLines ? 'ps-opt-metric__actual--revenue' : 'ps-opt-metric__actual';
    return (
        <td className={`text-center ${optToneClass(tone, variant)}`}>
            <div className={actualClass}>{value}</div>
            {revenueLines && (
                <>
                    <div className="ps-opt-metric__target--revenue" />
                    <div className="ps-opt-metric__ratio--revenue" />
                </>
            )}
        </td>
    );
}

function MetricCell({ actual, target = null, ratio = null, tone = 'average' }) {
    return (
        <td className={`text-center ${optToneClass(tone, 'metric')}`}>
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
    const alertForm = useForm({ low_ratio: thresholds.low, high_ratio: thresholds.high });
    const targetForm = useForm({
        targets: [{ sale_user_id: '', metric_key: 'close_rate', target_value: 100 }],
    });
    const search = () => visit({ ...queryFilters, page: 1 });

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
            onSuccess: () => toast.success('Đã cập nhật trạng thái nhận dữ liệu.'),
            onError: () => toast.error('Không cập nhật được trạng thái nhận dữ liệu.'),
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
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('levels')}>
                            <i className="fa fa-list" /> Thiết lập level mục tiêu
                        </button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('alerts')}>
                            <i className="fa fa-asterisk" /> Mức cảnh báo
                        </button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('targets')}>
                            <i className="fa fa-plus" /> Thiết lập mục tiêu sale
                        </button>
                        <button
                            type="button"
                            className="btn btn-sm btn-default"
                            onClick={() => setDialog('receive-bulk')}
                            title="Cập nhật hàng loạt qua popup (Pushsale parity)"
                        >
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
                                    <td><SaleNameCell row={row} /></td>
                                    <td className="text-center">
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
                                    <OptValueCell
                                        value={num(row.provisional_revenue)}
                                        tone={row.tone}
                                        variant="revenue"
                                        revenueLines
                                    />
                                    <OptValueCell value={num(row.success_revenue)} tone={row.tone} />
                                    <td className="text-center">{num(row.contacts)}</td>
                                    <OptValueCell value={num(row.allocated_total)} tone={row.tone} />
                                    <OptValueCell value={num(row.allocated_duplicate)} tone={row.tone} />
                                    <OptValueCell value={num(row.allocated_unique)} tone={row.tone} />
                                    <td className="text-center" />
                                    <td className="text-center">{row.call_duration_seconds ?? ''}</td>
                                    <td className="text-center">{row.avg_call_seconds ?? ''}</td>
                                    <td className="text-center" />
                                    <OptValueCell value={num(row.closed_contacts)} tone={row.tone} />
                                    <MetricCell
                                        actual={pct(row.close_rate)}
                                        target={pct(row.close_rate_target)}
                                        ratio={pct(row.close_rate_ratio)}
                                        tone={row.tone}
                                    />
                                    <OptValueCell value={num(row.avg_order_value)} tone={row.tone} />
                                    <OptValueCell value={row.products_per_order ?? ''} tone={row.tone} />
                                    <OptValueCell value={num(row.untouched)} tone={row.tone} />
                                    <OptValueCell value={num(row.revenue_per_contact)} tone={row.tone} />
                                    <td className="text-center">{num(row.cancelled_revenue)}</td>
                                    <td className="text-center">{num(row.returned_revenue)}</td>
                                </tr>
                            ))}
                            {!rows.length && <TableEmptyRow colSpan={21} />}
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

                {dialog === 'receive-bulk' && (
                    <div className="ps-sales-leader-dialog-backdrop">
                        <div className="ps-sales-leader-dialog">
                            <h4>Cập nhật nhận dữ liệu</h4>
                            <p>Bật/tắt nhận dữ liệu từng sale bằng checkbox cột <strong>Nhận dữ liệu</strong>. Mỗi lần tick sẽ xác nhận và lưu ngay.</p>
                            <p className="text-muted">Cập nhật hàng loạt theo nhóm: dùng trang Nhân sự hoặc popup Pushsale tương đương.</p>
                            <div className="ps-sales-leader-dialog__actions">
                                <button type="button" className="btn btn-sm btn-link" onClick={() => setDialog(null)}>Đóng</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
