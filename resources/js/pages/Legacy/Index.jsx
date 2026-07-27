import { Head, Link, router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { useConfirm } from '@/hooks/use-confirm';

const numberFormatter = new Intl.NumberFormat('vi-VN');
const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

function isNumeric(value) {
    return typeof value === 'number' || (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)));
}

function formatDate(value, withTime = false) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit', second: '2-digit' } : {}),
    }).format(date);
}

function displayValue(value, format) {
    if (value === null || value === undefined || value === '') return '';
    if (format === 'currency') return currencyFormatter.format(Number(value) || 0).replace('₫', '').trim();
    if (format === 'number') return isNumeric(value) ? numberFormatter.format(Number(value)) : String(value);
    if (format === 'percent') return `${numberFormatter.format(Number(value) || 0)} %`;
    if (format === 'datetime') return formatDate(value, true);
    if (format === 'date') return formatDate(value, false);
    return String(value);
}

function StatusValue({ value }) {
    const normalized = String(value ?? '').toLowerCase();
    const tone = normalized.includes('hoàn') || normalized.includes('đang') || normalized.includes('áp dụng')
        ? 'success'
        : normalized.includes('chờ') || normalized.includes('mới')
          ? 'warning'
          : normalized.includes('hủy') || normalized.includes('ngừng') || normalized.includes('lỗi')
            ? 'danger'
            : 'default';

    return <span className={`legacy-status legacy-status-${tone}`}>{String(value ?? '')}</span>;
}

function CellValue({ column, row, onEdit, onDelete }) {
    const value = row[column.key];

    if (column.key === 'select') {
        return <input type="checkbox" aria-label="Chọn dòng" />;
    }

    if (column.key === 'actions') {
        return (
            <div className="legacy-row-actions">
                {row.is_upsell && <span className="legacy-upsale-badge">UPSALE</span>}
                {row._edit_url && (
                    <Link href={row._edit_url} className="legacy-icon-action" title="Cập nhật">
                        <i className="fa fa-pencil" aria-hidden="true" />
                    </Link>
                )}
                {row._order_id && (
                    <Link href={`/customers?order=${row._order_id}`} className="legacy-icon-action" title="Chi tiết khách hàng">
                        <i className="fa fa-eye" aria-hidden="true" />
                    </Link>
                )}
                {row._record_id && (
                    <>
                        <button type="button" className="legacy-icon-action" title="Cập nhật" onClick={() => onEdit(row)}>
                            <i className="fa fa-pencil" aria-hidden="true" />
                        </button>
                        <button type="button" className="legacy-icon-action is-danger" title="Xóa" onClick={() => onDelete(row)}>
                            <i className="fa fa-trash" aria-hidden="true" />
                        </button>
                    </>
                )}
                {!row._edit_url && !row._order_id && !row._record_id && value && <span>{String(value)}</span>}
            </div>
        );
    }

    if (column.format === 'boolean') {
        const checked = value === true || value === 1 || value === '1';
        return <i className={`fa ${checked ? 'fa-check legacy-check-yes' : 'fa-times legacy-check-no'}`} aria-label={checked ? 'Có' : 'Không'} />;
    }

    if (column.format === 'status') return <StatusValue value={value} />;

    const rendered = displayValue(value, column.format);
    if (typeof rendered === 'string' && rendered.includes('\n')) {
        return (
            <span className="legacy-multiline">
                {rendered.split('\n').map((line, index) => (
                    <span key={`${column.key}-${index}`}>{line || '\u00a0'}</span>
                ))}
            </span>
        );
    }

    if (row.is_upsell && ['products', 'customer', 'order_info', 'order_code'].includes(column.key)) {
        return (
            <span className="legacy-cell-with-badge">
                <span>{rendered}</span>
                <span className="legacy-upsale-badge">UPSALE</span>
            </span>
        );
    }

    return rendered;
}

function TotalsRow({ columns, rows }) {
    if (!rows.length) return null;
    let labelPlaced = false;

    return (
        <tr className="legacy-total-row">
            {columns.map((column) => {
                const numeric = ['currency', 'number', 'percent'].includes(column.format);
                if (numeric) {
                    const total = rows.reduce((sum, row) => sum + (Number(row[column.key]) || 0), 0);
                    return <td key={column.key} className="text-right">{displayValue(total, column.format)}</td>;
                }
                if (!labelPlaced && !['select', 'index', 'id'].includes(column.key)) {
                    labelPlaced = true;
                    return <td key={column.key}><strong>Tổng:</strong></td>;
                }
                return <td key={column.key} />;
            })}
        </tr>
    );
}

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function Pagination({ pagination, routeUrl }) {
    return (
        <PushsalePagination
            meta={pagination}
            routeUrl={routeUrl}
            filters={currentFilters()}
            itemLabel="bản ghi"
        />
    );
}

function LegacyGrid({ schema, rows, pagination, routeUrl, onEdit, onDelete }) {
    const columns = schema.columns ?? [];
    const showTotals = ['report', 'trend', 'power_dashboard'].includes(schema.kind);

    return (
        <div className="legacy-grid-shell">
            <table className="table table-bordered table-striped legacy-grid">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}>
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {showTotals && <TotalsRow columns={columns} rows={rows} />}
                    {rows.length ? rows.map((row, rowIndex) => (
                        <tr key={row._record_id ?? row.id ?? row.order_code ?? `${schema.code}-${rowIndex}`} className={row.is_upsell ? 'legacy-row-upsale' : ''}>
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}
                                    title={typeof row[column.key] === 'string' ? row[column.key] : undefined}
                                >
                                    <CellValue column={column} row={row} onEdit={onEdit} onDelete={onDelete} />
                                </td>
                            ))}
                        </tr>
                    )) : (
                        <tr>
                            <td colSpan={Math.max(columns.length, 1)} className="legacy-empty-cell">
                                Chưa có dữ liệu phù hợp với bộ lọc.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
            <Pagination pagination={pagination} routeUrl={routeUrl} />
        </div>
    );
}

function RankingPodium({ rows }) {
    const top = rows.slice(0, 3);
    if (!top.length) return null;
    const order = [top[1], top[0], top[2]].filter(Boolean);

    return (
        <div className="legacy-ranking-podium">
            {order.map((row, index) => {
                const actualRank = row.index ?? (index === 1 ? 1 : index === 0 ? 2 : 3);
                return (
                    <div key={row.sale ?? index} className={`legacy-podium-card rank-${actualRank}`}>
                        <div className="legacy-podium-avatar"><i className="fa fa-user" /></div>
                        <strong>{row.sale}</strong>
                        <span>{row.total}</span>
                        <b>#{actualRank}</b>
                    </div>
                );
            })}
        </div>
    );
}

function TrendChart({ rows }) {
    if (!rows.length) return null;
    const width = 1000;
    const height = 245;
    const padding = 34;
    const max = Math.max(1, ...rows.flatMap((row) => [Number(row.value) || 0, Number(row.comparison) || 0]));
    const point = (value, index) => {
        const x = padding + (index * (width - padding * 2)) / Math.max(1, rows.length - 1);
        const y = height - padding - ((Number(value) || 0) / max) * (height - padding * 2);
        return `${x},${y}`;
    };

    return (
        <div className="legacy-trend-card">
            <div className="legacy-chart-legend"><span><i className="is-current" /> Kỳ hiện tại</span><span><i className="is-compare" /> Kỳ so sánh</span></div>
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Biểu đồ xu hướng doanh số">
                {[0, 1, 2, 3, 4].map((line) => {
                    const y = padding + (line * (height - padding * 2)) / 4;
                    return <line key={line} x1={padding} y1={y} x2={width - padding} y2={y} className="legacy-chart-grid" />;
                })}
                <polyline points={rows.map((row, index) => point(row.comparison, index)).join(' ')} className="legacy-chart-line is-compare" />
                <polyline points={rows.map((row, index) => point(row.value, index)).join(' ')} className="legacy-chart-line is-current" />
                {rows.map((row, index) => {
                    const [x, y] = point(row.value, index).split(',');
                    return <circle key={row.period} cx={x} cy={y} r="4" className="legacy-chart-point" />;
                })}
            </svg>
            <div className="legacy-chart-labels">{rows.map((row) => <span key={row.period}>{row.period}</span>)}</div>
        </div>
    );
}

function LiveSummary({ schema, rows }) {
    if (schema.kind !== 'power_dashboard') return null;
    const revenue = rows.reduce((sum, row) => sum + (Number(row.revenue) || 0), 0);
    const contacts = rows.reduce((sum, row) => sum + (Number(row.contacts) || 0), 0);
    const closed = rows.reduce((sum, row) => sum + (Number(row.closed) || 0), 0);
    const rate = contacts ? (closed / contacts) * 100 : 0;

    return (
        <div className="legacy-live-summary">
            <div><span>Doanh số thực tế</span><strong>{currencyFormatter.format(revenue)}</strong></div>
            <div><span>Tổng contact</span><strong>{numberFormatter.format(contacts)}</strong></div>
            <div><span>Đơn chốt</span><strong>{numberFormatter.format(closed)}</strong></div>
            <div><span>Tỷ lệ chốt</span><strong>{rate.toFixed(2)}%</strong></div>
        </div>
    );
}

function collectVisibleFormValues(root) {
    const payload = {};
    if (!root) return payload;

    root.querySelectorAll('input, select, textarea').forEach((field, index) => {
        if (field.disabled || field.type === 'hidden' || field.closest('.hidden')) return;
        const key = field.name || field.id || `field_${index + 1}`;
        if (field.type === 'checkbox' || field.type === 'radio') payload[key] = field.checked;
        else payload[key] = field.value;
    });

    return payload;
}

function genericPayloadFromColumns(schema, row = {}) {
    return Object.fromEntries((schema.columns ?? [])
        .filter((column) => !['index', 'select', 'actions', 'id', 'created_at', 'updated_at'].includes(column.key))
        .map((column) => [column.key, row[column.key] ?? '']));
}

function LegacyEditorDialog({ open, schema, row, dialogUrl, onClose, onSaved }) {
    const [dialogHtml, setDialogHtml] = useState('');
    const [payload, setPayload] = useState(() => genericPayloadFromColumns(schema, row));
    const [saving, setSaving] = useState(false);
    const dialogRef = useRef(null);

    useEffect(() => {
        setPayload(genericPayloadFromColumns(schema, row));
    }, [schema, row, open]);

    useEffect(() => {
        if (!open || !dialogUrl) {
            setDialogHtml('');
            return;
        }
        let active = true;
        fetch(dialogUrl, { headers: { Accept: 'text/html' } })
            .then((response) => response.ok ? response.text() : '')
            .then((html) => active && setDialogHtml(html.length > 500 ? html : ''))
            .catch(() => active && setDialogHtml(''));
        return () => { active = false; };
    }, [dialogUrl, open]);

    const submit = async () => {
        const dialogValues = collectVisibleFormValues(dialogRef.current);
        const finalPayload = Object.keys(dialogValues).length ? { ...payload, ...dialogValues } : payload;
        setSaving(true);
        try {
            await onSaved(finalPayload, row?._record_id);
            onClose();
        } finally {
            setSaving(false);
        }
    };

    useEffect(() => {
        if (!open) return undefined;
        const handler = (event) => {
            const action = event.target.closest?.('[data-legacy-action]')?.dataset.legacyAction;
            if (action === 'save') {
                event.preventDefault();
                submit();
            }
            if (event.target.closest?.('[id$="btnDong"], [data-dismiss]')) {
                event.preventDefault();
                onClose();
            }
        };
        const node = dialogRef.current;
        node?.addEventListener('click', handler);
        return () => node?.removeEventListener('click', handler);
    });

    if (!open) return null;

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={row ? `Cập nhật ${schema.title}` : `Thêm mới ${schema.title}`}
            width="900px"
            bodyRef={dialogRef}
            bodyClassName="legacy-dialog-body"
            footer={(
                <>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button type="button" className="btn btn-primary btn-sm" disabled={saving} onClick={submit}>
                        <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? 'Đang lưu' : 'Cập nhật'}
                    </button>
                </>
            )}
        >
                    {dialogHtml ? (
                        <div className="legacy-dialog-source" dangerouslySetInnerHTML={{ __html: dialogHtml }} />
                    ) : (
                        <div className="legacy-generic-form">
                            {(schema.columns ?? []).filter((column) => !['index', 'select', 'actions', 'id', 'created_at', 'updated_at'].includes(column.key)).map((column) => (
                                <label key={column.key}>
                                    <span>{column.label}</span>
                                    {column.format === 'boolean' ? (
                                        <input
                                            type="checkbox"
                                            checked={Boolean(payload[column.key])}
                                            onChange={(event) => setPayload((current) => ({ ...current, [column.key]: event.target.checked }))}
                                        />
                                    ) : (
                                        <input
                                            className="form-control"
                                            type={['currency', 'number', 'percent'].includes(column.format) ? 'number' : column.format === 'date' ? 'date' : 'text'}
                                            value={payload[column.key] ?? ''}
                                            onChange={(event) => setPayload((current) => ({ ...current, [column.key]: event.target.value }))}
                                        />
                                    )}
                                </label>
                            ))}
                        </div>
                    )}
        </PushsaleDialog>
    );
}

function TemplateHost({ templateUrl, schema, rows, pagination, routeUrl, filterOptions, onCreate, onEdit, onDelete, onLegacySave }) {
    const [html, setHtml] = useState('');
    const [anchor, setAnchor] = useState(null);
    const hostRef = useRef(null);

    useEffect(() => {
        let active = true;
        setHtml('');
        fetch(templateUrl, { headers: { Accept: 'text/html' } })
            .then((response) => {
                if (!response.ok) throw new Error(`Không tải được template ${templateUrl}`);
                return response.text();
            })
            .then((content) => active && setHtml(content))
            .catch(() => active && setHtml(''));
        return () => { active = false; };
    }, [templateUrl]);

    useEffect(() => {
        if (!html) {
            setAnchor(null);
            return;
        }
        const frame = window.requestAnimationFrame(() => {
            const host = hostRef.current;
            setAnchor(host?.querySelector('[data-legacy-grid-anchor="1"]') ?? null);
        });
        return () => window.cancelAnimationFrame(frame);
    }, [html]);
    useEffect(() => {
        if (!html || !hostRef.current) return;
        const host = hostRef.current;
        host.querySelectorAll('.ps-ddl').forEach((dropdown) => {
            const id = dropdown.id || '';
            const options = id.includes('Landing') || id.includes('Nguon')
                ? (filterOptions?.sources ?? [])
                : id.includes('SanPham') || id.includes('Product')
                  ? (filterOptions?.products ?? [])
                  : id.includes('Kho')
                    ? (filterOptions?.warehouses ?? [])
                    : [];
            const result = dropdown.querySelector('.result-items');
            if (!result || !options.length) return;
            result.innerHTML = '';
            result.classList.remove('hidden');
            options.slice(0, 100).forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'legacy-ddl-result';
                button.dataset.legacyOptionId = String(option.id);
                button.dataset.legacyOptionLabel = String(option.label ?? option.name ?? option.id);
                button.dataset.legacyOptionData = JSON.stringify(option);
                button.textContent = button.dataset.legacyOptionLabel;
                result.appendChild(button);
            });
        });
    }, [filterOptions, html]);

    const runSearch = useCallback(() => {
        const host = hostRef.current;
        const fields = Array.from(host?.querySelectorAll('input:not([type="hidden"]), textarea') ?? [])
            .filter((field) => !field.disabled && !field.closest('.hidden'));
        const searchField = fields.find((field) => field.value?.trim() && !field.classList.contains('date-range')) ?? fields.find((field) => field.value?.trim());
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        if (searchField?.value?.trim()) params.set('search', searchField.value.trim());
        else params.delete('search');
        router.get(routeUrl, Object.fromEntries(params.entries()), { preserveState: true, preserveScroll: true });
    }, [routeUrl]);

    useEffect(() => {
        const host = hostRef.current;
        if (!host) return undefined;

        const handler = (event) => {
            const actionNode = event.target.closest?.('[data-legacy-action]');
            const action = actionNode?.dataset.legacyAction;
            if (!action) return;
            event.preventDefault();
            if (action === 'search') runSearch();
            if (action === 'reload') router.reload({ preserveScroll: true });
            if (action === 'export') {
                const params = new URLSearchParams(window.location.search);
                params.set('export', '1');
                window.location.assign(`${routeUrl}?${params.toString()}`);
            }
            if (['create', 'add', 'new'].includes(action)) onCreate();
            if (action === 'save') {
                if (onLegacySave) onLegacySave(host);
                else onCreate();
            }
        };

        const customDropdown = (event) => {
            const option = event.target.closest?.('[data-legacy-option-id]');
            if (option) {
                event.preventDefault();
                event.stopPropagation();
                const dropdown = option.closest('.ps-ddl');
                const selected = dropdown?.querySelector('.ps-ddl-selected-item');
                if (selected) {
                    selected.textContent = option.dataset.legacyOptionLabel || '';
                    selected.setAttribute('item-id', option.dataset.legacyOptionId || '');
                    selected.dataset.optionData = option.dataset.legacyOptionData || '{}';
                }
                dropdown?.querySelector('.ps-ddl-search-area')?.classList.add('hidden');
                dropdown?.classList.remove('is-open');
                return;
            }
            const dropdown = event.target.closest?.('.ps-ddl');
            if (!dropdown) return;
            if (event.target.closest('input')) return;
            dropdown.classList.toggle('is-open');
            dropdown.querySelector('.ps-ddl-search-area')?.classList.toggle('hidden');
        };

        host.addEventListener('click', handler);
        host.addEventListener('click', customDropdown);
        return () => {
            host.removeEventListener('click', handler);
            host.removeEventListener('click', customDropdown);
        };
    }, [onCreate, routeUrl, runSearch]);

    const grid = <LegacyGrid schema={schema} rows={rows} pagination={pagination} routeUrl={routeUrl} onEdit={onEdit} onDelete={onDelete} />;

    return (
        <>
            <div className="legacy-template-host" ref={hostRef} dangerouslySetInnerHTML={{ __html: html }} />
            {anchor ? createPortal(grid, anchor) : !html ? (
                <div className="legacy-template-loading"><i className="fa fa-spinner fa-spin" /> Đang tải giao diện…</div>
            ) : (
                <div className="legacy-template-fallback">{grid}</div>
            )}
        </>
    );
}

function requestJson(url, method, payload) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload ? JSON.stringify(payload) : undefined,
    }).then(async (response) => {
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Không thể lưu dữ liệu.');
        }
        return response.json().catch(() => ({}));
    });
}

export default function LegacyIndex({ schema, rows = [], pagination, routeUrl, templateUrl, dialogUrls = [], filterOptions = {} }) {
    const { ask } = useConfirm();
    const [editor, setEditor] = useState({ open: false, row: null });
    const [error, setError] = useState('');

    const firstDialogUrl = dialogUrls[0]?.url ?? null;
    const openCreate = useCallback(() => {
        if (schema.create_url) {
            router.visit(schema.create_url);
            return;
        }
        if (schema.editable) setEditor({ open: true, row: null });
    }, [schema]);

    const save = async (payload, recordId) => {
        setError('');
        try {
            await requestJson(recordId ? `${routeUrl}/${recordId}` : routeUrl, recordId ? 'PUT' : 'POST', { payload });
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (exception) {
            setError(exception.message);
            throw exception;
        }
    };

    const remove = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({ description: 'Xóa bản ghi này?', confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        setError('');
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch(`${routeUrl}/${row._record_id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Không thể xóa dữ liệu.');
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (exception) {
            setError(exception.message);
        }
    };

    const handleLegacySave = useCallback((host) => {
        if (schema.code !== '2.6.2') {
            openCreate();
            return;
        }
        const findValue = (suffix) => host?.querySelector(`[id$="${suffix}"]`)?.value?.trim() ?? '';
        const sourceNode = host?.querySelector('#dnn_ctr1525_Main_CapNhatContact__IdLandingThuCong .ps-ddl-selected-item');
        const productNode = host?.querySelector('#dnn_ctr1525_Main_CapNhatContact_ddlIdSanPham .ps-ddl-selected-item');
        const sourceId = Number(sourceNode?.getAttribute('item-id')) || null;
        let product = null;
        try { product = JSON.parse(productNode?.dataset.optionData || 'null'); } catch { product = null; }
        const phone = findValue('_KhachHangPhone');
        if (!phone) {
            setError('Vui lòng nhập số điện thoại khách hàng.');
            return;
        }
        setError('');
        router.post('/admin/leads/manual', {
            name: findValue('_KhachHangName'),
            phone,
            message: findValue('_KhachHangMessage'),
            marketing_source_id: sourceId,
            items: product?.id ? [{
                product_id: Number(product.id),
                item_type: product.type === 'combo' ? 'combo' : 'product',
                quantity: 1,
                unit_price: Number(product.unit_price || 0),
                discount_amount: 0,
            }] : [],
        }, {
            preserveScroll: true,
            onSuccess: () => {
                host?.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => { field.value = ''; });
                router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
            },
            onError: (errors) => setError(errors.phone ?? errors.items ?? 'Không thể lưu data thủ công.'),
        });
    }, [openCreate, schema.code]);

    const operationalUrl = useMemo(() => {
        if (schema.code === '4.2') return '/admin/customers';
        if (schema.code === '5.1') return '/admin/warehouse/operations';
        if (schema.upsell) return '/admin/sales/workspace?mode=upsell';
        return null;
    }, [schema]);

    return (
        <AppLayout>
            <Head title={schema.title} />
            <div className={`legacy-page legacy-kind-${schema.kind}`} data-page-code={schema.code}>
                {error && <div className="legacy-error-banner"><i className="fa fa-exclamation-triangle" /> {error}</div>}
                {(schema.upsell || operationalUrl) && (
                    <div className="legacy-business-strip">
                        {schema.upsell && <span className="legacy-upsale-badge">UPSALE</span>}
                        <span>Luồng dữ liệu, đơn hàng và sản phẩm upsale được giữ trên nghiệp vụ hiện tại.</span>
                        {operationalUrl && <Link href={operationalUrl}>Mở tác nghiệp đầy đủ <i className="fa fa-external-link" /></Link>}
                    </div>
                )}
                {schema.kind === 'ranking' && <RankingPodium rows={rows} />}
                {schema.kind === 'trend' && <TrendChart rows={rows} />}
                <LiveSummary schema={schema} rows={rows} />
                <TemplateHost
                    templateUrl={templateUrl}
                    schema={schema}
                    rows={rows}
                    pagination={pagination}
                    routeUrl={routeUrl}
                    filterOptions={filterOptions}
                    onCreate={openCreate}
                    onEdit={(row) => setEditor({ open: true, row })}
                    onDelete={remove}
                    onLegacySave={handleLegacySave}
                />
                {schema.editable && !schema.create_url && (
                    <button type="button" className="legacy-floating-add" onClick={openCreate} title={`Thêm ${schema.title}`}>
                        <i className="fa fa-plus" />
                    </button>
                )}
            </div>
            <LegacyEditorDialog
                open={editor.open}
                schema={schema}
                row={editor.row}
                dialogUrl={editor.row ? null : firstDialogUrl}
                onClose={() => setEditor({ open: false, row: null })}
                onSaved={save}
            />
        </AppLayout>
    );
}
