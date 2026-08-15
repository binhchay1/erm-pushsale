import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

export const OPT_METRIC_COLUMNS = [
    { key: 'provisional_revenue', label: 'Doanh số tạm tính' },
    { key: 'success_revenue', label: 'Doanh số thành công' },
    { key: 'allocated_total', label: 'Contact được chia Tổng' },
    { key: 'allocated_duplicate', label: 'Contact được chia trùng' },
    { key: 'allocated_unique', label: 'Contact không trùng' },
    { key: 'closed_contacts', label: 'Contact chốt đơn' },
    { key: 'close_rate', label: 'Tỷ lệ chốt đơn' },
    { key: 'avg_order_value', label: 'Giá trị đơn' },
    { key: 'products_per_order', label: 'Số sản phẩm/Đơn' },
    { key: 'untouched', label: 'Contact chưa tác nghiệp' },
    { key: 'cancelled_revenue', label: 'Doanh số hủy' },
    { key: 'returned_revenue', label: 'Doanh số hoàn' },
];

function emptyMetrics() {
    return Object.fromEntries(OPT_METRIC_COLUMNS.map((col) => [col.key, '']));
}

function emptyCatalogRow(index = 0) {
    return {
        id: null,
        name: '',
        metrics: emptyMetrics(),
        sort_order: index,
        _key: `new-${Date.now()}-${index}`,
    };
}

function emptyTargetRow(sale = null, year, month) {
    return {
        sale_user_id: sale?.id ?? '',
        account: sale?.label ?? sale?.name ?? '',
        catalog_name: '',
        year,
        month,
        working_days: 22,
        metrics: {
            ...emptyMetrics(),
            close_rate: '100',
        },
        _key: `sale-${sale?.id ?? 'new'}-${Date.now()}`,
    };
}

function leaderOptionsFrom(filterOptions = {}) {
    const map = new Map();
    for (const key of ['saleLeaders', 'teamLeaders', 'admins']) {
        for (const option of filterOptions[key] ?? []) {
            map.set(String(option.id), option);
        }
    }
    return Array.from(map.values());
}

function salesOptionsFrom(filterOptions = {}) {
    const map = new Map();
    for (const key of ['salesUsers', 'sales']) {
        for (const option of filterOptions[key] ?? []) {
            map.set(String(option.id), option);
        }
    }
    return Array.from(map.values());
}

function MetricInputs({ metrics, onChange, prefix = '' }) {
    return OPT_METRIC_COLUMNS.map((col) => (
        <td key={`${prefix}${col.key}`} className="text-center">
            <input
                className="form-control input-sm ps-opt-dialog-input"
                value={metrics?.[col.key] ?? ''}
                onChange={(event) => onChange(col.key, event.target.value)}
                placeholder="Chỉ tiêu"
                title={`Nhập chỉ tiêu: ${col.label}`}
                inputMode="decimal"
            />
        </td>
    ));
}

/** Dialog ảnh 2 — Danh mục tối ưu sale */
export function OptimizationCatalogDialog({
    open,
    onOpenChange,
    catalogs = [],
    leaderUserId,
    leaderLabel = '',
}) {
    const [rows, setRows] = useState([]);
    const form = useForm({ leader_user_id: leaderUserId, catalogs: [] });

    useEffect(() => {
        if (!open) return;
        const next = (catalogs ?? []).map((row, index) => ({
            id: row.id ?? null,
            name: row.name ?? '',
            metrics: { ...emptyMetrics(), ...(row.metrics ?? {}) },
            sort_order: row.sort_order ?? index,
            _key: `cat-${row.id ?? index}`,
        }));
        setRows(next.length ? next : [emptyCatalogRow(0)]);
        form.setData('leader_user_id', leaderUserId);
    }, [open, catalogs, leaderUserId]);

    const updateRow = (key, patch) => {
        setRows((current) => current.map((row) => (row._key === key ? { ...row, ...patch } : row)));
    };

    const updateMetric = (key, metricKey, value) => {
        setRows((current) => current.map((row) => (
            row._key === key
                ? { ...row, metrics: { ...row.metrics, [metricKey]: value } }
                : row
        )));
    };

    const save = () => {
        const payload = rows
            .filter((row) => String(row.name || '').trim() !== '')
            .map((row, index) => ({
                id: row.id,
                name: String(row.name).trim(),
                metrics: row.metrics,
                sort_order: index,
            }));

        if (!payload.length) {
            toast.error('Nhập ít nhất một dòng danh mục (cột Tên).');
            return;
        }

        form.transform(() => ({
            leader_user_id: Number(leaderUserId),
            catalogs: payload,
        }));
        form.post('/admin/sales/reports/optimization/catalogs', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã lưu danh mục tối ưu sale.');
                onOpenChange?.(false);
            },
            onError: () => toast.error('Không lưu được danh mục tối ưu sale.'),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title="DANH MỤC TỐI ƯU SALE"
            size="full"
            width="98vw"
            className="ps-opt-wide-dialog"
            bodyClassName="ps-opt-dialog-body"
            footer={(
                <button type="button" className="btn btn-sm btn-default" onClick={() => onOpenChange?.(false)}>Đóng</button>
            )}
        >
            <p className="ps-opt-dialog-hint">
                Đây là form <strong>thiết lập chỉ tiêu mẫu</strong> (không phải số liệu báo cáo thực tế).
                Nhập tên level + các chỉ tiêu rồi bấm <strong>Lưu</strong>.
            </p>
            <div className="ps-opt-dialog-toolbar">
                <strong>Danh mục tối ưu sale</strong>
                <div className="ps-opt-dialog-toolbar__right">
                    <span className="text-muted">Trưởng nhóm: {leaderLabel || leaderUserId}</span>
                    <button
                        type="button"
                        className="btn btn-sm btn-primary"
                        onClick={() => {
                            const next = (catalogs ?? []).map((row, index) => ({
                                id: row.id ?? null,
                                name: row.name ?? '',
                                metrics: { ...emptyMetrics(), ...(row.metrics ?? {}) },
                                sort_order: row.sort_order ?? index,
                                _key: `cat-${row.id ?? index}`,
                            }));
                            setRows(next.length ? next : [emptyCatalogRow(0)]);
                        }}
                    >
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                </div>
            </div>
            <div className="ps-opt-dialog-table-wrap">
                <table className="table table-bordered table-striped ps-opt-dialog-table">
                    <thead>
                        <tr>
                            <th className="text-center" rowSpan="2">STT</th>
                            <th className="text-center" rowSpan="2">Tên</th>
                            <th className="text-center" rowSpan="2">Doanh số tạm tính</th>
                            <th className="text-center" rowSpan="2">Doanh số thành công</th>
                            <th className="text-center" colSpan="2">Contact được chia</th>
                            <th className="text-center" rowSpan="2">Contact không trùng</th>
                            <th className="text-center" rowSpan="2">Contact chốt đơn</th>
                            <th className="text-center" rowSpan="2">Tỷ lệ chốt đơn</th>
                            <th className="text-center" rowSpan="2">Giá trị đơn</th>
                            <th className="text-center" rowSpan="2">Số sản phẩm/Đơn</th>
                            <th className="text-center" rowSpan="2">Contact chưa tác nghiệp</th>
                            <th className="text-center" rowSpan="2">Doanh số hủy</th>
                            <th className="text-center" rowSpan="2">Doanh số hoàn</th>
                            <th className="text-center" rowSpan="2">Cập nhật</th>
                            <th className="text-center" rowSpan="2">
                                <button
                                    type="button"
                                    className="btn btn-xs btn-link"
                                    onClick={() => setRows((current) => [...current, emptyCatalogRow(current.length)])}
                                >
                                    <i className="fa fa-plus" /> Thêm
                                </button>
                            </th>
                        </tr>
                        <tr>
                            <th className="text-center">Tổng</th>
                            <th className="text-center">trùng</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={row._key}>
                                <td className="text-center">{index + 1}</td>
                                <td>
                                    <input
                                        className="form-control input-sm"
                                        value={row.name}
                                        onChange={(event) => updateRow(row._key, { name: event.target.value })}
                                    />
                                </td>
                                <MetricInputs
                                    prefix={row._key}
                                    metrics={row.metrics}
                                    onChange={(metricKey, value) => updateMetric(row._key, metricKey, value)}
                                />
                                <td className="text-center" />
                                <td className="text-center">
                                    <button type="button" className="btn btn-xs btn-link" onClick={save} disabled={form.processing}>
                                        <i className="fa fa-save" /> Lưu
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </PushsaleDialog>
    );
}

/** Dialog ảnh 3 — Cập nhật chỉ số cảnh báo */
export function OptimizationAlertsDialog({ open, onOpenChange, thresholds }) {
    const form = useForm({
        low_ratio: thresholds?.low ?? 80,
        high_ratio: thresholds?.high ?? 100,
    });

    useEffect(() => {
        if (!open) return;
        form.setData({
            low_ratio: thresholds?.low ?? 80,
            high_ratio: thresholds?.high ?? 100,
        });
    }, [open, thresholds?.low, thresholds?.high]);

    const save = () => {
        form.post('/admin/sales/reports/optimization/alerts', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật chỉ số cảnh báo.');
                onOpenChange?.(false);
            },
            onError: () => toast.error('Không cập nhật được chỉ số cảnh báo.'),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title="CẬP NHẬT CHỈ SỐ CẢNH BÁO"
            size="sm"
            width="420px"
            bodyClassName="ps-opt-alert-dialog-body"
        >
            <label className="ps-opt-alert-field">
                <span>Chỉ số dưới (<span className="text-danger">*</span>)</span>
                <input
                    className="form-control"
                    type="number"
                    value={form.data.low_ratio}
                    onChange={(event) => form.setData('low_ratio', event.target.value)}
                />
            </label>
            <label className="ps-opt-alert-field">
                <span>Chỉ số trên (<span className="text-danger">*</span>)</span>
                <input
                    className="form-control"
                    type="number"
                    value={form.data.high_ratio}
                    onChange={(event) => form.setData('high_ratio', event.target.value)}
                />
            </label>
            <div className="ps-opt-alert-actions">
                <button type="button" className="btn btn-sm btn-primary" disabled={form.processing} onClick={save}>
                    <i className="fa fa-save" /> Cập nhật
                </button>
            </div>
        </PushsaleDialog>
    );
}

/** Dialog ảnh 4 — Thêm chỉ số / thiết lập mục tiêu sale */
export function OptimizationTargetsDialog({
    open,
    onOpenChange,
    filterOptions = {},
    targetMap = {},
    draftLeaderId = '',
    draftTeamId = '',
}) {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [leaderId, setLeaderId] = useState(draftLeaderId || '');
    const [teamId, setTeamId] = useState(draftTeamId || '');
    const [saleId, setSaleId] = useState('');
    const [rows, setRows] = useState([]);
    const form = useForm({ targets: [] });

    const leaders = useMemo(() => leaderOptionsFrom(filterOptions), [filterOptions]);
    const teams = useMemo(() => {
        const all = filterOptions.saleTeams ?? filterOptions.teams ?? [];
        if (!leaderId) return all;
        return all.filter((team) => String(team.leader_user_id ?? '') === String(leaderId));
    }, [filterOptions, leaderId]);
    const sales = useMemo(() => salesOptionsFrom(filterOptions), [filterOptions]);

    useEffect(() => {
        if (!open) return;
        setLeaderId(draftLeaderId || '');
        setTeamId(draftTeamId || '');
        setYear(now.getFullYear());
        setMonth(now.getMonth() + 1);
        setSaleId('');
        setRows([]);
    }, [open, draftLeaderId, draftTeamId]);

    const searchSales = () => {
        let list = sales;
        if (teamId) {
            list = list.filter((item) => String(item.team_id ?? '') === String(teamId));
        }
        if (saleId) {
            list = list.filter((item) => String(item.id) === String(saleId));
        }
        // Không có team_id trên user option thì vẫn load toàn bộ sale theo filter sale.
        if (!list.length && !saleId && !teamId) {
            list = sales;
        }
        if (!list.length) {
            setRows([emptyTargetRow(null, year, month)]);
            return;
        }
        setRows(list.map((sale) => {
            const map = targetMap?.[String(sale.id)] || targetMap?.[sale.id] || {};
            return {
                ...emptyTargetRow(sale, year, month),
                metrics: {
                    ...emptyMetrics(),
                    close_rate: map.close_rate ?? 100,
                    ...map,
                },
            };
        }));
    };

    const updateRow = (key, patch) => {
        setRows((current) => current.map((row) => (row._key === key ? { ...row, ...patch } : row)));
    };

    const updateMetric = (key, metricKey, value) => {
        setRows((current) => current.map((row) => (
            row._key === key
                ? { ...row, metrics: { ...row.metrics, [metricKey]: value } }
                : row
        )));
    };

    const save = () => {
        const targets = [];
        rows.forEach((row) => {
            const saleUserId = row.sale_user_id ? Number(row.sale_user_id) : null;
            OPT_METRIC_COLUMNS.forEach((col) => {
                const raw = row.metrics?.[col.key];
                if (raw === '' || raw === null || raw === undefined) return;
                targets.push({
                    sale_user_id: saleUserId,
                    metric_key: col.key,
                    target_value: Number(raw),
                });
            });
        });

        if (!targets.length) {
            toast.error('Nhập ít nhất một chỉ số mục tiêu trước khi lưu.');
            return;
        }

        form.transform(() => ({ targets }));
        form.post('/admin/sales/reports/optimization/targets', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã lưu mục tiêu sale.');
                onOpenChange?.(false);
            },
            onError: () => toast.error('Không lưu được mục tiêu sale.'),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title="THÊM CHỈ SỐ"
            size="full"
            width="98vw"
            className="ps-opt-wide-dialog"
            bodyClassName="ps-opt-dialog-body"
            footer={(
                <button type="button" className="btn btn-sm btn-primary" disabled={form.processing} onClick={save}>
                    <i className="fa fa-save" /> Lưu
                </button>
            )}
        >
            <p className="ps-opt-dialog-hint">
                Form <strong>thiết lập mục tiêu sale</strong>: chọn năm/tháng + sale, nhập chỉ tiêu vào các ô rồi bấm <strong>Lưu</strong>.
                Các cột số là chỉ tiêu cần đạt, không phải số liệu thực tế trên báo cáo.
            </p>
            <div className="ps-opt-dialog-toolbar">
                <strong>Tối ưu sale</strong>
                <select className="form-control input-sm" value={year} onChange={(event) => setYear(Number(event.target.value))}>
                    {[year - 1, year, year + 1].map((value) => (
                        <option key={value} value={value}>Năm {value}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={month} onChange={(event) => setMonth(Number(event.target.value))}>
                    {Array.from({ length: 12 }, (_, index) => index + 1).map((value) => (
                        <option key={value} value={value}>Tháng {value}</option>
                    ))}
                </select>
                <button type="button" className="btn btn-sm btn-primary" onClick={searchSales}>
                    <i className="fa fa-search" /> Tìm kiếm
                </button>
            </div>
            <div className="ps-opt-dialog-toolbar">
                <select className="form-control input-sm" value={leaderId} onChange={(event) => setLeaderId(event.target.value)}>
                    <option value="">-- Trưởng nhóm --</option>
                    {leaders.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={teamId} onChange={(event) => setTeamId(event.target.value)}>
                    <option value="">-- Chọn nhóm --</option>
                    {teams.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={saleId} onChange={(event) => setSaleId(event.target.value)}>
                    <option value="">-- Sales --</option>
                    {sales.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
            </div>
            <div className="ps-opt-dialog-table-wrap">
                <table className="table table-bordered table-striped ps-opt-dialog-table">
                    <thead>
                        <tr>
                            <th className="text-center" rowSpan="2">STT</th>
                            <th className="text-center" rowSpan="2">Tài khoản</th>
                            <th className="text-center" rowSpan="2">Chỉ số mẫu</th>
                            <th className="text-center" rowSpan="2">Năm</th>
                            <th className="text-center" rowSpan="2">Tháng</th>
                            <th className="text-center" rowSpan="2">Số ngày làm việc</th>
                            <th className="text-center" rowSpan="2">Doanh số tạm tính</th>
                            <th className="text-center" rowSpan="2">Doanh số thành công</th>
                            <th className="text-center" colSpan="2">Contact được chia</th>
                            <th className="text-center" rowSpan="2">Contact không trùng</th>
                            <th className="text-center" rowSpan="2">Contact chốt đơn</th>
                            <th className="text-center" rowSpan="2">Tỷ lệ chốt đơn</th>
                            <th className="text-center" rowSpan="2">Giá trị đơn</th>
                            <th className="text-center" rowSpan="2">Số sản phẩm/Đơn</th>
                            <th className="text-center" rowSpan="2">Contact chưa tác nghiệp</th>
                            <th className="text-center" rowSpan="2">Doanh số hủy</th>
                            <th className="text-center" rowSpan="2">Doanh số hoàn</th>
                            <th className="text-center" rowSpan="2">Cập nhật</th>
                            <th className="text-center" rowSpan="2">
                                <button
                                    type="button"
                                    className="btn btn-xs btn-link"
                                    onClick={() => setRows((current) => [...current, emptyTargetRow(null, year, month)])}
                                >
                                    <i className="fa fa-plus" /> Thêm
                                </button>
                            </th>
                        </tr>
                        <tr>
                            <th className="text-center">Tổng</th>
                            <th className="text-center">Trùng</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={row._key}>
                                <td className="text-center">{index + 1}</td>
                                <td>
                                    <select
                                        className="form-control input-sm"
                                        value={row.sale_user_id}
                                        onChange={(event) => {
                                            const selected = sales.find((item) => String(item.id) === event.target.value);
                                            updateRow(row._key, {
                                                sale_user_id: event.target.value,
                                                account: selected?.label ?? selected?.name ?? '',
                                            });
                                        }}
                                    >
                                        <option value="">-- Sales --</option>
                                        {sales.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                                        ))}
                                    </select>
                                </td>
                                <td>
                                    <input
                                        className="form-control input-sm"
                                        value={row.catalog_name}
                                        onChange={(event) => updateRow(row._key, { catalog_name: event.target.value })}
                                        placeholder="Chỉ số mẫu"
                                    />
                                </td>
                                <td className="text-center">
                                    <input className="form-control input-sm" value={row.year} onChange={(event) => updateRow(row._key, { year: event.target.value })} />
                                </td>
                                <td className="text-center">
                                    <input className="form-control input-sm" value={row.month} onChange={(event) => updateRow(row._key, { month: event.target.value })} />
                                </td>
                                <td className="text-center">
                                    <input className="form-control input-sm" value={row.working_days} onChange={(event) => updateRow(row._key, { working_days: event.target.value })} />
                                </td>
                                <MetricInputs
                                    prefix={row._key}
                                    metrics={row.metrics}
                                    onChange={(metricKey, value) => updateMetric(row._key, metricKey, value)}
                                />
                                <td className="text-center">
                                    <button type="button" className="btn btn-xs btn-link" onClick={save} disabled={form.processing}>
                                        <i className="fa fa-save" />
                                    </button>
                                </td>
                                <td className="text-center">
                                    <button
                                        type="button"
                                        className="btn btn-xs btn-link text-danger"
                                        onClick={() => setRows((current) => current.filter((item) => item._key !== row._key))}
                                    >
                                        <i className="fa fa-trash" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!rows.length && (
                            <tr>
                                <td colSpan="20" className="text-center text-muted">Chọn bộ lọc rồi bấm Tìm kiếm để tải sale.</td>
                            </tr>
                        )}
                        <tr className="rowsum">
                            <td colSpan="6" className="text-center font-weight-bold">Tổng:</td>
                            <td colSpan="14" />
                        </tr>
                    </tbody>
                </table>
            </div>
        </PushsaleDialog>
    );
}

export { leaderOptionsFrom };
