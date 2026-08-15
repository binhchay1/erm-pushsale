import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { useT } from '@/providers/I18nProvider';

export const OPT_METRIC_COLUMNS = [
    { key: 'provisional_revenue', labelKey: 'col_provisional_revenue' },
    { key: 'success_revenue', labelKey: 'col_success_revenue' },
    { key: 'allocated_total', labelKey: 'col_allocated_total' },
    { key: 'allocated_duplicate', labelKey: 'col_allocated_duplicate' },
    { key: 'allocated_unique', labelKey: 'col_allocated_unique' },
    { key: 'closed_contacts', labelKey: 'col_closed_contacts' },
    { key: 'close_rate', labelKey: 'col_close_rate' },
    { key: 'avg_order_value', labelKey: 'col_avg_order_value' },
    { key: 'products_per_order', labelKey: 'col_products_per_order' },
    { key: 'untouched', labelKey: 'col_untouched' },
    { key: 'cancelled_revenue', labelKey: 'col_cancelled_revenue' },
    { key: 'returned_revenue', labelKey: 'col_returned_revenue' },
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
    const t = useT();

    return OPT_METRIC_COLUMNS.map((col) => {
        const label = t(`reports.sales_optimization.${col.labelKey}`);
        return (
            <td key={`${prefix}${col.key}`} className="text-center">
                <input
                    className="form-control input-sm ps-opt-dialog-input"
                    value={metrics?.[col.key] ?? ''}
                    onChange={(event) => onChange(col.key, event.target.value)}
                    placeholder={t('reports.sales_optimization.target_placeholder')}
                    title={t('reports.sales_optimization.target_input_title', { label })}
                    inputMode="decimal"
                />
            </td>
        );
    });
}

/** Dialog ảnh 2 — Danh mục tối ưu sale */
export function OptimizationCatalogDialog({
    open,
    onOpenChange,
    catalogs = [],
    leaderUserId,
    leaderLabel = '',
}) {
    const t = useT();
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
            toast.error(t('reports.sales_optimization.catalog_name_required'));
            return;
        }

        form.transform(() => ({
            leader_user_id: Number(leaderUserId),
            catalogs: payload,
        }));
        form.post('/admin/sales/reports/optimization/catalogs', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('reports.sales_optimization.catalog_saved'));
                onOpenChange?.(false);
            },
            onError: () => toast.error(t('reports.sales_optimization.catalog_save_fail')),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('reports.sales_optimization.catalog_title')}
            size="full"
            width="99vw"
            className="ps-opt-wide-dialog"
            bodyClassName="ps-opt-dialog-body"
            footer={(
                <button type="button" className="btn btn-sm btn-default" onClick={() => onOpenChange?.(false)}>
                    {t('reports.sales_optimization.close')}
                </button>
            )}
        >
            <p className="ps-opt-dialog-hint">
                {t('reports.sales_optimization.catalog_hint')}
            </p>
            <div className="ps-opt-dialog-toolbar">
                <strong>{t('reports.sales_optimization.catalog_heading')}</strong>
                <div className="ps-opt-dialog-toolbar__right">
                    <span className="text-muted">
                        {t('reports.sales_optimization.leader_label', { name: leaderLabel || leaderUserId })}
                    </span>
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
                        <i className="fa fa-search" /> {t('reports.sales_optimization.search')}
                    </button>
                </div>
            </div>
            <div className="ps-opt-dialog-table-wrap">
                <table className="table table-bordered table-striped ps-opt-dialog-table">
                    <thead>
                        <tr>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_stt')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_name')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_provisional_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_success_revenue')}</th>
                            <th className="text-center" colSpan="2">{t('reports.sales_optimization.col_allocated')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_allocated_unique')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_closed_contacts')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_close_rate')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_avg_order_value')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_products_per_order')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_untouched')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_cancelled_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_returned_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.update_col')}</th>
                            <th className="text-center" rowSpan="2">
                                <button
                                    type="button"
                                    className="btn btn-xs btn-link"
                                    onClick={() => setRows((current) => [...current, emptyCatalogRow(current.length)])}
                                >
                                    <i className="fa fa-plus" /> {t('reports.sales_optimization.add')}
                                </button>
                            </th>
                        </tr>
                        <tr>
                            <th className="text-center">{t('reports.sales_optimization.col_allocated_total')}</th>
                            <th className="text-center">{t('reports.sales_optimization.col_allocated_duplicate')}</th>
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
                                        <i className="fa fa-save" /> {t('reports.sales_optimization.save')}
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
    const t = useT();
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
                toast.success(t('reports.sales_optimization.alerts_saved'));
                onOpenChange?.(false);
            },
            onError: () => toast.error(t('reports.sales_optimization.alerts_save_fail')),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('reports.sales_optimization.alerts_title')}
            size="sm"
            width="420px"
            bodyClassName="ps-opt-alert-dialog-body"
        >
            <label className="ps-opt-alert-field">
                <span>{t('reports.sales_optimization.alerts_low')} (<span className="text-danger">*</span>)</span>
                <input
                    className="form-control"
                    type="number"
                    value={form.data.low_ratio}
                    onChange={(event) => form.setData('low_ratio', event.target.value)}
                />
            </label>
            <label className="ps-opt-alert-field">
                <span>{t('reports.sales_optimization.alerts_high')} (<span className="text-danger">*</span>)</span>
                <input
                    className="form-control"
                    type="number"
                    value={form.data.high_ratio}
                    onChange={(event) => form.setData('high_ratio', event.target.value)}
                />
            </label>
            <div className="ps-opt-alert-actions">
                <button type="button" className="btn btn-sm btn-primary" disabled={form.processing} onClick={save}>
                    <i className="fa fa-save" /> {t('reports.sales_optimization.update')}
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
    const t = useT();
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
            toast.error(t('reports.sales_optimization.targets_required'));
            return;
        }

        form.transform(() => ({ targets }));
        form.post('/admin/sales/reports/optimization/targets', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('reports.sales_optimization.targets_saved'));
                onOpenChange?.(false);
            },
            onError: () => toast.error(t('reports.sales_optimization.targets_save_fail')),
        });
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('reports.sales_optimization.targets_title')}
            size="full"
            width="99vw"
            className="ps-opt-wide-dialog"
            bodyClassName="ps-opt-dialog-body"
            footer={(
                <button type="button" className="btn btn-sm btn-primary" disabled={form.processing} onClick={save}>
                    <i className="fa fa-save" /> {t('reports.sales_optimization.save')}
                </button>
            )}
        >
            <p className="ps-opt-dialog-hint">
                {t('reports.sales_optimization.targets_hint')}
            </p>
            <div className="ps-opt-dialog-toolbar">
                <strong>{t('reports.sales_optimization.targets_heading')}</strong>
                <select className="form-control input-sm" value={year} onChange={(event) => setYear(Number(event.target.value))}>
                    {[year - 1, year, year + 1].map((value) => (
                        <option key={value} value={value}>{t('reports.sales_optimization.year', { year: value })}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={month} onChange={(event) => setMonth(Number(event.target.value))}>
                    {Array.from({ length: 12 }, (_, index) => index + 1).map((value) => (
                        <option key={value} value={value}>{t('reports.sales_optimization.month', { month: value })}</option>
                    ))}
                </select>
                <button type="button" className="btn btn-sm btn-primary" onClick={searchSales}>
                    <i className="fa fa-search" /> {t('reports.sales_optimization.search')}
                </button>
            </div>
            <div className="ps-opt-dialog-toolbar">
                <select className="form-control input-sm" value={leaderId} onChange={(event) => setLeaderId(event.target.value)}>
                    <option value="">{t('reports.sales_optimization.choose_leader')}</option>
                    {leaders.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={teamId} onChange={(event) => setTeamId(event.target.value)}>
                    <option value="">{t('reports.sales_optimization.choose_team')}</option>
                    {teams.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
                <select className="form-control input-sm" value={saleId} onChange={(event) => setSaleId(event.target.value)}>
                    <option value="">{t('reports.sales_optimization.choose_sale')}</option>
                    {sales.map((option) => (
                        <option key={option.id} value={option.id}>{option.label ?? option.name}</option>
                    ))}
                </select>
            </div>
            <div className="ps-opt-dialog-table-wrap">
                <table className="table table-bordered table-striped ps-opt-dialog-table">
                    <thead>
                        <tr>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_stt')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.account')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.sample_metric')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_year')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_month')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.working_days')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_provisional_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_success_revenue')}</th>
                            <th className="text-center" colSpan="2">{t('reports.sales_optimization.col_allocated')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_allocated_unique')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_closed_contacts')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_close_rate')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_avg_order_value')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_products_per_order')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_untouched')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_cancelled_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.col_returned_revenue')}</th>
                            <th className="text-center" rowSpan="2">{t('reports.sales_optimization.update_col')}</th>
                            <th className="text-center" rowSpan="2">
                                <button
                                    type="button"
                                    className="btn btn-xs btn-link"
                                    onClick={() => setRows((current) => [...current, emptyTargetRow(null, year, month)])}
                                >
                                    <i className="fa fa-plus" /> {t('reports.sales_optimization.add')}
                                </button>
                            </th>
                        </tr>
                        <tr>
                            <th className="text-center">{t('reports.sales_optimization.col_allocated_total')}</th>
                            <th className="text-center">{t('reports.sales_optimization.col_allocated_duplicate')}</th>
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
                                        <option value="">{t('reports.sales_optimization.choose_sale')}</option>
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
                                        placeholder={t('reports.sales_optimization.sample_metric')}
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
                                <td colSpan="20" className="text-center text-muted">{t('reports.sales_optimization.targets_empty')}</td>
                            </tr>
                        )}
                        <tr className="rowsum">
                            <td colSpan="6" className="text-center font-weight-bold">{t('reports.sales_optimization.total_row')}</td>
                            <td colSpan="14" />
                        </tr>
                    </tbody>
                </table>
            </div>
        </PushsaleDialog>
    );
}

export { leaderOptionsFrom };
