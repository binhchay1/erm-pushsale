import { Head, Link, router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { CustomerMessagesDialog } from '@/components/customers/CustomerMessagesDialog';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { CustomerPurchaseHistoryDialog } from '@/components/customers/CustomerPurchaseHistoryDialog';
import { OrderOperationHistoryDialog } from '@/components/customers/OrderOperationHistoryDialog';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { ConfirmActionDialog } from '@/components/ui/ConfirmActionDialog';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { vietnamesePhoneError, normalizeVietnamesePhone } from '@/lib/vietnamesePhone';

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
    if (format === 'currency') return currencyFormatter.format(Number(value) || 0);
    if (format === 'number') return isNumeric(value) ? numberFormatter.format(Number(value)) : String(value);
    if (format === 'percent') return `${numberFormatter.format(Number(value) || 0)} %`;
    if (format === 'datetime') return formatDate(value, true);
    if (format === 'date') return formatDate(value, false);
    return String(value);
}

function StatusValue({ value }) {
    const normalized = String(value ?? '').toLowerCase();
    const tone = normalized.includes('không thành công') || normalized.includes('thất bại') || normalized === 'failed'
        ? 'danger'
        : normalized.includes('thành công') || normalized.includes('đã phê duyệt') || normalized === 'success'
          ? 'success'
          : normalized.includes('đăng xuất') || normalized === 'logout'
            ? 'default'
            : normalized.includes('hoàn') || normalized.includes('đang') || normalized.includes('áp dụng')
        ? 'success'
        : normalized.includes('chờ') || normalized.includes('mới') || normalized.includes('chưa phê duyệt')
          ? 'warning'
          : normalized.includes('hủy') || normalized.includes('ngừng') || normalized.includes('lỗi')
            ? 'danger'
            : 'default';

    return <span className={`pushsale-status pushsale-status-${tone}`}>{String(value ?? '')}</span>;
}

function CellValue({ column, row, onEdit, onDelete, selectedRecordIds, onToggleSelect }) {
    const value = row[column.key];

    if (column.key === 'select') {
        const recordId = row._record_id ?? null;
        return (
            <input
                type="checkbox"
                aria-label="Chọn dòng"
                disabled={!recordId}
                checked={recordId ? selectedRecordIds.has(String(recordId)) : false}
                onChange={() => recordId && onToggleSelect(recordId)}
            />
        );
    }


    if (column.key === 'actions') {
        return (
            <div className="pushsale-row-actions">
                {row.is_upsell && <span className="pushsale-upsale-badge">UPSALE</span>}
                {row._edit_url && (
                    <Link href={row._edit_url} className="pushsale-icon-action" title="Cập nhật">
                        <i className="fa fa-pencil" aria-hidden="true" />
                    </Link>
                )}
                {row._order_id && (() => {
                    const [customerName = '', customerPhone = ''] = String(row.customer ?? '').split('\n');
                    const order = {
                        id: row._order_id,
                        orderCode: row.order_code ?? '',
                        customerName,
                        customerPhone,
                    };

                    return (
                        <>
                            <OrderOperationHistoryDialog order={order} />
                            <CustomerMessagesDialog order={order} />
                            <CustomerPurchaseHistoryDialog order={order} />
                            <Link href={`/customers?order=${row._order_id}`} className="pushsale-icon-action" title="Mở hồ sơ đầy đủ">
                                <i className="fa fa-eye" aria-hidden="true" />
                            </Link>
                        </>
                    );
                })()}
                {row._record_id && (
                    <>
                        <button type="button" className="pushsale-icon-action" title="Cập nhật" onClick={() => onEdit(row)}>
                            <i className="fa fa-pencil" aria-hidden="true" />
                        </button>
                        <button type="button" className="pushsale-icon-action is-danger" title="Xóa" onClick={() => onDelete(row)}>
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
        return <i className={`fa ${checked ? 'fa-check pushsale-check-yes' : 'fa-times pushsale-check-no'}`} aria-label={checked ? 'Có' : 'Không'} />;
    }

    if (column.format === 'status') return <StatusValue value={value} />;

    if (column.format === 'copy' && value) {
        return (
            <span className="pushsale-copy-value">
                <span>{String(value)}</span>
                <button
                    type="button"
                    className="pushsale-copy-button"
                    title="Sao chép"
                    onClick={() => navigator.clipboard?.writeText(String(value))}
                >
                    <i className="fa fa-copy" aria-hidden="true" />
                </button>
            </span>
        );
    }

    const rendered = displayValue(value, column.format);
    if (typeof rendered === 'string' && rendered.includes('\n')) {
        return (
            <span className="pushsale-multiline">
                {rendered.split('\n').map((line, index) => (
                    <span key={`${column.key}-${index}`}>{line || '\u00a0'}</span>
                ))}
            </span>
        );
    }

    if (row.is_upsell && ['products', 'customer', 'order_info', 'order_code'].includes(column.key)) {
        return (
            <span className="pushsale-cell-with-badge">
                <span>{rendered}</span>
                <span className="pushsale-upsale-badge">UPSALE</span>
            </span>
        );
    }

    return rendered;
}

function TotalsRow({ columns, rows }) {
    if (!rows.length) return null;
    let labelPlaced = false;

    return (
        <tr className="pushsale-total-row">
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

function currentQueryFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function Pagination({ pagination, routeUrl }) {
    return (
        <PushsalePagination
            meta={pagination}
            routeUrl={routeUrl}
            filters={currentQueryFilters()}
            itemLabel="bản ghi"
        />
    );
}

function voucherTypeOptions() {
    return [
        { id: 'inbound', label: 'Nhập kho' },
        { id: 'outbound', label: 'Xuất kho' },
    ];
}

function toNumberInputValue(value, fallback = '') {
    const normalized = String(value ?? '').replaceAll(',', '').trim();
    if (normalized === '') return fallback;
    const number = Number(normalized);
    return Number.isFinite(number) ? number : fallback;
}

function selectedDropdownOption(host, suffixes) {
    const suffixList = Array.isArray(suffixes) ? suffixes : [suffixes];
    for (const suffix of suffixList) {
        const selected = host?.querySelector(`[id$="${suffix}"] .ps-ddl-selected-item`);
        const id = Number(selected?.getAttribute('item-id'));
        if (Number.isFinite(id) && id > 0) {
            let data = null;
            try { data = JSON.parse(selected?.dataset.optionData || 'null'); } catch { data = null; }
            return { id, data };
        }
    }
    return { id: null, data: null };
}

function voucherFieldValue(host, name) {
    return String(host?.querySelector(`[data-pushsale-voucher-field="${name}"]`)?.value ?? '').trim();
}

function VoucherDraftRow({ columns }) {
    const render = (column) => {
        switch (column.key) {
            case 'index':
                return <span className="pushsale-voucher-add-dot">+</span>;
            case 'product':
                return <span className="pushsale-voucher-product-hint">Chọn sản phẩm phía trên rồi nhập số lượng</span>;
            case 'document_quantity':
                return <input className="form-control pushsale-voucher-line-input" type="number" min="1" defaultValue="1" data-pushsale-voucher-field="document_quantity" aria-label="Số lượng chứng từ" />;
            case 'quantity':
                return <input className="form-control pushsale-voucher-line-input" type="number" min="1" defaultValue="1" data-pushsale-voucher-field="quantity" aria-label="Số lượng thực nhập/xuất" />;
            case 'unit_cost':
                return <input className="form-control pushsale-voucher-line-input" type="number" min="0" step="1000" data-pushsale-voucher-field="unit_cost" aria-label="Giá nhập" />;
            case 'batch_code':
                return <input className="form-control pushsale-voucher-line-input" data-pushsale-voucher-field="batch_code" aria-label="Lô" />;
            case 'expiry_date':
                return <input className="form-control pushsale-voucher-line-input" type="date" data-pushsale-voucher-field="expiry_date" aria-label="Ngày hết hạn" />;
            case 'location':
                return <input className="form-control pushsale-voucher-line-input" data-pushsale-voucher-field="location_code" aria-label="Mã vị trí" />;
            case 'note':
                return <input className="form-control pushsale-voucher-line-input" data-pushsale-voucher-field="note" aria-label="Ghi chú dòng phiếu" />;
            case 'total':
                return <span className="pushsale-voucher-total-hint">Tự tính</span>;
            default:
                return '';
        }
    };

    return (
        <tr className="pushsale-voucher-draft-row">
            {columns.map((column) => (
                <td key={`draft-${column.key}`} className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}>
                    {render(column)}
                </td>
            ))}
        </tr>
    );
}

function PushsaleRows({ schema, rows, onEdit, onDelete, selectedRecordIds, onToggleSelect }) {
    const columns = schema.display_columns ?? schema.columns ?? [];
    const showTotals = ['report', 'trend', 'power_dashboard'].includes(schema.kind);
    const isVoucherEntry = String(schema.code) === '5.3.1';

    return (
        <>
            {showTotals && <TotalsRow columns={columns} rows={rows} />}
            {isVoucherEntry && <VoucherDraftRow columns={columns} />}
            {rows.length ? rows.map((row, rowIndex) => (
                <tr key={row._record_id ?? row.id ?? row.order_code ?? `${schema.code}-${rowIndex}`} className={row.is_upsell ? 'pushsale-row-upsale' : ''}>
                    {columns.map((column) => (
                        <td
                            key={column.key}
                            className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}
                            title={typeof row[column.key] === 'string' ? row[column.key] : undefined}
                        >
                            <CellValue column={column} row={row} onEdit={onEdit} onDelete={onDelete} selectedRecordIds={selectedRecordIds} onToggleSelect={onToggleSelect} />
                        </td>
                    ))}
                </tr>
            )) : (!isVoucherEntry && (
                <tr>
                    <td colSpan={Math.max(columns.length, 1)} className="pushsale-empty-cell">
                        Chưa có dữ liệu phù hợp với bộ lọc.
                    </td>
                </tr>
            ))}
        </>
    );
}

function PushsaleFallbackGrid(props) {
    const columns = props.schema.display_columns ?? props.schema.columns ?? [];
    return (
        <div className="pushsale-grid-shell">
            <table className="table table-bordered table-striped pushsale-grid">
                <thead><tr>{columns.map((column) => <th key={column.key}>{column.label}</th>)}</tr></thead>
                <tbody><PushsaleRows {...props} /></tbody>
            </table>
            <Pagination pagination={props.pagination} routeUrl={props.routeUrl} />
        </div>
    );
}

function RankingPodium({ rows }) {
    const top = rows.slice(0, 3);
    if (!top.length) return null;
    const order = [top[1], top[0], top[2]].filter(Boolean);

    return (
        <div className="pushsale-ranking-podium">
            {order.map((row, index) => {
                const actualRank = row.index ?? (index === 1 ? 1 : index === 0 ? 2 : 3);
                return (
                    <div key={row.sale ?? index} className={`pushsale-podium-card rank-${actualRank}`}>
                        <div className="pushsale-podium-avatar"><i className="fa fa-user" /></div>
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
        <div className="pushsale-trend-card">
            <div className="pushsale-chart-legend"><span><i className="is-current" /> Kỳ hiện tại</span><span><i className="is-compare" /> Kỳ so sánh</span></div>
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Biểu đồ xu hướng doanh số">
                {[0, 1, 2, 3, 4].map((line) => {
                    const y = padding + (line * (height - padding * 2)) / 4;
                    return <line key={line} x1={padding} y1={y} x2={width - padding} y2={y} className="pushsale-chart-grid" />;
                })}
                <polyline points={rows.map((row, index) => point(row.comparison, index)).join(' ')} className="pushsale-chart-line is-compare" />
                <polyline points={rows.map((row, index) => point(row.value, index)).join(' ')} className="pushsale-chart-line is-current" />
                {rows.map((row, index) => {
                    const [x, y] = point(row.value, index).split(',');
                    return <circle key={row.period} cx={x} cy={y} r="4" className="pushsale-chart-point" />;
                })}
            </svg>
            <div className="pushsale-chart-labels">{rows.map((row) => <span key={row.period}>{row.period}</span>)}</div>
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
        <div className="pushsale-live-summary">
            <div><span>Doanh số thực tế</span><strong>{currencyFormatter.format(revenue)}</strong></div>
            <div><span>Tổng contact</span><strong>{numberFormatter.format(contacts)}</strong></div>
            <div><span>Đơn chốt</span><strong>{numberFormatter.format(closed)}</strong></div>
            <div><span>Tỷ lệ chốt</span><strong>{rate.toFixed(2)}%</strong></div>
        </div>
    );
}



function TrendMetricChart({ row }) {
    if (!row) return <div className="pushsale-chart-empty">Chưa có dữ liệu trong khoảng thời gian đã chọn.</div>;
    const values = [6, 5, 4, 3, 2, 1, 0].map((offset) => Number(row[`day_${offset}_value`]) || 0);
    const width = 760;
    const height = 220;
    const padding = 28;
    const max = Math.max(1, ...values);
    const points = values.map((value, index) => {
        const x = padding + (index * (width - padding * 2)) / Math.max(1, values.length - 1);
        const y = height - padding - (value / max) * (height - padding * 2);
        return `${x},${y}`;
    });

    return (
        <div className="pushsale-live-trend-chart">
            <div className="pushsale-live-trend-title">{row.period ?? 'Số liệu thực tế'}</div>
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`Biểu đồ ${row.period ?? ''}`}>
                {[0, 1, 2, 3, 4].map((line) => {
                    const y = padding + (line * (height - padding * 2)) / 4;
                    return <line key={line} x1={padding} y1={y} x2={width - padding} y2={y} className="pushsale-chart-grid" />;
                })}
                <polyline points={points.join(' ')} className="pushsale-chart-line is-current" />
                {points.map((point, index) => {
                    const [x, y] = point.split(',');
                    return <g key={index}><circle cx={x} cy={y} r="4" className="pushsale-chart-point" /><text x={x} y={Number(y) - 8} textAnchor="middle" className="pushsale-chart-value">{numberFormatter.format(values[index])}</text></g>;
                })}
            </svg>
            <div className="pushsale-chart-labels">{[6, 5, 4, 3, 2, 1, 0].map((offset) => <span key={offset}>{offset === 0 ? 'Hôm nay' : `-${offset} ngày`}</span>)}</div>
        </div>
    );
}

function LiveDataSummary({ summary = {} }) {
    const entries = Object.entries(summary).filter(([, value]) => value !== null && value !== undefined);
    // Plain table pages only return total_records for pagination bookkeeping.
    // Rendering that as a KPI card makes Pushsale pages look like fake/demo data.
    if (!entries.length || (entries.length === 1 && entries[0][0] === 'total_records')) return null;

    const labels = {
        total_orders: 'Tổng đơn',
        closed_orders: 'Đơn đã chốt',
        total_revenue: 'Doanh số',
        upsell_orders: 'Đơn có upsale',
        total_items: 'Sản phẩm tồn kho',
        stock_quantity: 'Tồn thực tế',
        pending_quantity: 'Chờ xuất',
        cod_amount: 'COD cần thu',
        insufficient_stock: 'Đơn thiếu tồn',
        total_sales: 'Nhân viên sale',
        total_records: 'Tổng bản ghi',
    };
    const currencyKeys = new Set(['total_revenue', 'cod_amount']);

    return (
        <div className="pushsale-live-data-summary" aria-label="Tổng hợp dữ liệu thực tế">
            {entries.map(([key, value]) => (
                <div key={key} className="pushsale-live-data-summary-item">
                    <span>{labels[key] ?? key.replaceAll('_', ' ')}</span>
                    <strong>{currencyKeys.has(key) ? currencyFormatter.format(Number(value) || 0) : numberFormatter.format(Number(value) || 0)}</strong>
                </div>
            ))}
        </div>
    );
}

function optionsForField(field, filterOptions) {
    if (field?.options) {
        return Object.entries(field.options).map(([id, label]) => ({ id, label }));
    }
    return filterOptions?.[field?.option_source] ?? [];
}

function defaultFormPayload(fields, row = {}) {
    const now = new Date();
    return Object.fromEntries((fields ?? []).map((field) => {
        let fallback = field.type === 'checkbox' ? Boolean(field.default ?? false) : field.type === 'multiselect' ? [] : (field.default ?? '');
        if (field.key === 'year' && (fallback === '' || fallback == null)) fallback = now.getFullYear();
        if (field.key === 'month' && (fallback === '' || fallback == null)) fallback = now.getMonth() + 1;
        const raw = row?._form?.[field.key] ?? row?.[field.key] ?? fallback;
        return [field.key, raw ?? fallback];
    }));
}

function collectBoundFormValues(root, fields, current) {
    const payload = { ...current };
    if (!root) return payload;
    root.querySelectorAll('[data-pushsale-field-key]').forEach((control) => {
        const key = control.dataset.pushsaleFieldKey;
        if (!key) return;
        if (control.type === 'checkbox') payload[key] = control.checked;
        else if (control.multiple) payload[key] = [...control.selectedOptions].map((option) => option.value);
        else payload[key] = control.value;
    });
    (fields ?? []).forEach((field) => {
        if (field.type === 'checkbox' && payload[field.key] === undefined) payload[field.key] = false;
    });
    return payload;
}

function bindCapturedControls(root, fields, payload, filterOptions) {
    if (!root) return 0;
    const controls = [...root.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"]), select, textarea')]
        .filter((control) => !control.closest('.pushsale-generated-form') && !control.disabled);
    let bound = 0;
    (fields ?? []).forEach((field, index) => {
        const control = controls[index];
        if (!control) return;
        bound += 1;
        control.dataset.pushsaleFieldKey = field.key;
        control.name = field.key;
        control.setAttribute('aria-label', field.label);
        if (control.tagName === 'SELECT') {
            const options = optionsForField(field, filterOptions);
            if (options.length) {
                const placeholder = control.options[0]?.textContent || `--${field.label}--`;
                control.innerHTML = '';
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = placeholder;
                control.appendChild(empty);
                options.forEach((option) => {
                    const node = document.createElement('option');
                    node.value = String(option.id);
                    node.textContent = String(option.label ?? option.name ?? option.id);
                    control.appendChild(node);
                });
            }
        }
        if (field.type === 'checkbox') control.checked = Boolean(payload[field.key]);
        else if (field.type === 'multiselect') {
            control.multiple = true;
            const values = new Set((payload[field.key] ?? []).map(String));
            [...control.options].forEach((option) => { option.selected = values.has(option.value); });
        } else control.value = payload[field.key] ?? '';
    });
    return bound;
}

function FormField({ field, value, filterOptions, onChange }) {
    const options = optionsForField(field, filterOptions);
    const common = {
        id: `pushsale-field-${field.key}`,
        name: field.key,
        'data-pushsale-field-key': field.key,
        className: 'form-control',
    };

    if (field.type === 'checkbox') {
        return <input {...common} className="pushsale-form-checkbox" type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />;
    }
    if (field.type === 'textarea' || field.type === 'json') {
        const shown = field.type === 'json' && typeof value !== 'string' ? JSON.stringify(value ?? {}, null, 2) : (value ?? '');
        return <textarea {...common} rows={field.type === 'json' ? 5 : 3} value={shown} onChange={(event) => onChange(event.target.value)} />;
    }
    if (field.type === 'select' || field.type === 'multiselect') {
        const current = field.type === 'multiselect' ? (value ?? []).map(String) : String(value ?? '');
        return (
            <select {...common} multiple={field.type === 'multiselect'} value={current} onChange={(event) => onChange(field.type === 'multiselect' ? [...event.target.selectedOptions].map((option) => option.value) : event.target.value)}>
                {field.type !== 'multiselect' && <option value="">--Chọn--</option>}
                {options.map((option) => <option key={option.id} value={String(option.id)}>{option.label ?? option.name ?? option.id}</option>)}
            </select>
        );
    }
    const type = field.type === 'currency' ? 'text' : ['number', 'date', 'time', 'datetime-local', 'tel', 'email'].includes(field.type) ? field.type : 'text';
    return <input {...common} type={type} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />;
}

function PushsaleEditorDialog({ open, schema, row, dialogHtml = '', dialogSchema = null, filterOptions, onClose, onSaved }) {
    const fields = dialogSchema?.fields ?? schema.form_fields ?? [];
    const title = dialogSchema?.title ?? schema.title;
    const [payload, setPayload] = useState(() => defaultFormPayload(fields, row));
    const [saving, setSaving] = useState(false);
    const [dialogError, setDialogError] = useState('');
    const [editingDialogRecord, setEditingDialogRecord] = useState(null);
    const [capturedFieldCount, setCapturedFieldCount] = useState(0);
    const dialogRef = useRef(null);

    useEffect(() => {
        setPayload(defaultFormPayload(fields, row));
        setEditingDialogRecord(null);
        setDialogError('');
    }, [open, row, dialogSchema, schema.code]);

    useEffect(() => {
        if (!open || !dialogHtml) return;
        const frame = requestAnimationFrame(() => setCapturedFieldCount(bindCapturedControls(dialogRef.current, fields, payload, filterOptions)));
        return () => cancelAnimationFrame(frame);
    }, [open, dialogHtml, fields, filterOptions]);

    useEffect(() => {
        if (!open) return undefined;
        const root = dialogRef.current;
        const inputHandler = (event) => {
            const control = event.target.closest?.('[data-pushsale-field-key]');
            if (!control) return;
            const key = control.dataset.pushsaleFieldKey;
            const value = control.type === 'checkbox' ? control.checked : control.multiple ? [...control.selectedOptions].map((option) => option.value) : control.value;
            setPayload((current) => ({ ...current, [key]: value }));
        };
        root?.addEventListener('input', inputHandler);
        root?.addEventListener('change', inputHandler);
        return () => {
            root?.removeEventListener('input', inputHandler);
            root?.removeEventListener('change', inputHandler);
        };
    }, [open]);

    const submit = async () => {
        const finalPayload = collectBoundFormValues(dialogRef.current, fields, payload);
        const missing = [];
        for (const field of fields) {
            if (!field.required) continue;
            const value = finalPayload[field.key];
            const blank = value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
            if (blank) missing.push(field.label || field.key);
        }
        if (missing.length) {
            const message = `Vui lòng nhập: ${missing.join(', ')}.`;
            setDialogError(message);
            toast.error(message);
            return;
        }
        for (const field of fields) {
            const key = String(field.key ?? '');
            const type = String(field.type ?? '');
            const isPhone = type === 'tel' || /(^|_)(phone|sdt|mobile)(_|$)/i.test(key);
            if (!isPhone) continue;
            const phoneError = vietnamesePhoneError(finalPayload[key], { required: Boolean(field.required) });
            if (phoneError) {
                setDialogError(phoneError);
                toast.error(phoneError);
                return;
            }
            if (String(finalPayload[key] ?? '').trim()) {
                finalPayload[key] = normalizeVietnamesePhone(finalPayload[key]) ?? finalPayload[key];
            }
        }

        setSaving(true);
        setDialogError('');
        try {
            await onSaved(finalPayload, editingDialogRecord?.id ?? row?._record_id);
            toast.success(row || editingDialogRecord ? 'Đã cập nhật dữ liệu.' : 'Đã thêm dữ liệu.');
            onClose();
        } catch (exception) {
            const message = exception?.message || 'Không thể lưu dữ liệu.';
            setDialogError(message);
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    useEffect(() => {
        if (!open) return undefined;
        const root = dialogRef.current;
        const handler = (event) => {
            const action = event.target.closest?.('[data-pushsale-action]')?.dataset.pushsaleAction;
            if (action === 'save') { event.preventDefault(); submit(); }
            if (event.target.closest?.('[id$="btnDong"], [data-dismiss], .close')) { event.preventDefault(); onClose(); }
        };
        root?.addEventListener('click', handler);
        return () => root?.removeEventListener('click', handler);
    });

    if (!open) return null;
    const missingFields = fields.slice(capturedFieldCount);

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => { if (!nextOpen) onClose(); }}
            width="1120px"
            title={row || editingDialogRecord ? `Cập nhật ${title}` : title}
            bodyRef={dialogRef}
            className="pushsale-editor-dialog"
            footer={(
                <>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button type="button" className="btn btn-primary btn-sm" disabled={saving} onClick={submit}>
                        <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? 'Đang lưu' : 'Cập nhật'}
                    </button>
                </>
            )}
        >
            {dialogSchema?.records?.length > 0 && (
                <div className="pushsale-dialog-live-records">
                    <div className="pushsale-dialog-live-records-title">Dữ liệu hiện có</div>
                    <div className="table-responsive">
                        <table className="table table-bordered table-striped table-condensed">
                            <thead><tr>
                                <th style={{ width: 55 }}>ID</th>
                                {fields.slice(0, 3).map((field) => <th key={field.key}>{field.label}</th>)}
                                <th style={{ width: 60 }} />
                            </tr></thead>
                            <tbody>
                                {dialogSchema.records.map((record) => (
                                    <tr key={record.id}>
                                        <td>{record.id}</td>
                                        {fields.slice(0, 3).map((field) => <td key={field.key}>{displayValue(record[field.key], field.type === 'currency' ? 'currency' : undefined)}</td>)}
                                        <td className="text-center">
                                            <button type="button" className="pushsale-icon-action" title="Sửa" onClick={() => {
                                                setEditingDialogRecord(record);
                                                setPayload(defaultFormPayload(fields, { _form: record._form ?? record }));
                                            }}>
                                                <i className="fa fa-pencil" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
            {dialogHtml && <div className="pushsale-dialog-source" dangerouslySetInnerHTML={{ __html: dialogHtml }} />}
            {(!dialogHtml || missingFields.length > 0) && (
                <div className="pushsale-generated-form">
                    {(dialogHtml ? missingFields : fields).map((field) => (
                        <label key={field.key} className={`pushsale-form-field pushsale-form-field-${field.type ?? 'text'}`}>
                            <span>{field.label}{field.required ? <> <b className="required">(*)</b></> : null}</span>
                            <FormField field={field} value={payload[field.key]} filterOptions={filterOptions} onChange={(value) => setPayload((current) => ({ ...current, [field.key]: value }))} />
                        </label>
                    ))}
                </div>
            )}
            {dialogError ? <div className="alert alert-danger" style={{ marginTop: 12 }}>{dialogError}</div> : null}
        </PushsaleDialog>
    );

}


function inferFilterKey(node) {
    const source = `${node?.id ?? ''} ${node?.name ?? ''}`.toLowerCase();
    if (!source) return null;
    if (source.includes('tukhoa') || source.includes('keyword') || source.includes('search') || source.includes('username')) return 'search';
    if (source.includes('quanlydangnhap') && source.includes('ddldonvi')) return 'company_id';
    if (source.includes('quanlydangnhap') && (source.includes('ddlchucvu') || source.includes('role'))) return 'role';
    if (source.includes('quanlydangnhap') && (source.includes('ddlusers') || source.includes('ddluser'))) return 'user_id';
    if (source.includes('quanlydangnhap') && source.includes('ddltrangthai')) return 'login_permission_status';
    if (source.includes('quanlydangnhap') && source.includes('ddlngaypheduyet')) return 'sort';
    if (source.includes('lichsudangnhap') && source.includes('ddldonvi')) return 'company_id';
    if (source.includes('lichsudangnhap') && (source.includes('ddlchucvu') || source.includes('role'))) return 'role';
    if (source.includes('lichsudangnhap') && (source.includes('ddlusers') || source.includes('ddluser'))) return 'user_id';
    if (source.includes('lichsudangnhap') && source.includes('ddltrangthai')) return 'login_status';
    if (source.includes('lichsudangnhap') && source.includes('ddlsapxep')) return 'sort';
    if (source.includes('ddlusers') || source.includes('ddluser')) return 'user_id';
    if (source.includes('ddlchucvu') || source.includes('role')) return 'role';
    if (source.includes('ddldonvi') || source.includes('company')) return 'company_id';
    if (source.includes('danhsachsanpham') && source.includes('ddlcatid')) return 'category_id';
    if (source.includes('danhsachsanpham') && source.includes('ddlisngungkinhdoanh')) return 'active_status';
    if (source.includes('danhsachsanpham') && source.includes('ddlmarketing')) return 'available_marketing';
    if (source.includes('danhsachsanpham') && source.includes('ddlsale')) return 'available_sale';
    if (source.includes('danhsachsanpham') && source.includes('ddlcskh')) return 'available_care';
    if (source.includes('sanphamgroup')) return 'parent_product_id';
    if (source.includes('truongnhom')) return 'team_leader_id';
    if (source.includes('ddlsapxep')) return 'sort';
    if (source.includes('tungay') || source.includes('ngaytu') || source.includes('datefrom')) return 'date_from';
    if (source.includes('denngay') || source.includes('ngayden') || source.includes('dateto')) return 'date_to';
    if (source.includes('caredonuserid') || source.includes('careuserid') || source.includes('ddlcskh')) return 'care_user_id';
    if (source.includes('quankho_userid')) return 'warehouse_user_id';
    if (source.includes('leadersale')) return 'sale_leader_id';
    if (source.includes('nhomsale') || source.includes('teamsale') || source.endsWith('ddlnhom')) return 'sale_team_id';
    if (source.includes('saleuserid') || source.includes('idsale')) return 'sale_id';
    if (source.includes('leadermkt') || source.includes('leadermarketing')) return 'marketer_leader_id';
    if (source.includes('nhommkt') || source.includes('teammarketing')) return 'marketer_team_id';
    if (source.includes('marketinguserid') || source.includes('idmarketing') || source.includes('ddlmarketing') || source.includes('ddlmarketings')) return 'marketer_id';
    if ((source.includes('ddlsale') || source.includes('ddlsales')) && !source.includes('leader')) return 'sale_id';
    if (source.includes('sanpham') || source.includes('product')) return 'product_id';
    if (source.includes('landing') || source.includes('nguondulieu') || source.includes('source')) return 'source_id';
    if (
        source.includes('__idkho')
        || source.includes('_idkho')
        || /(^|[^a-z])idkho([^a-z]|$)/.test(source)
        || source.includes('warehouse')
    ) {
        return 'warehouse_id';
    }
    if (source.includes('trangthaichotdon')) return 'closed_status';
    if (source.includes('trangthaigiaohang') || source.includes('deliverystatus')) return 'delivery_status';
    if (source.includes('ketquatacnghiep')) return 'operation_result';
    if (source.includes('trangthaitacnghiep')) return 'operation_state';
    if (source.includes('tacnghiepcaredon')) return 'care_operation_status';
    if (source.includes('idketquatacnghiep') || source.includes('ketquatacnghiep')) return 'operation_result';
    if (source.includes('idtacnghiep') || source.includes('tacnghiep')) return 'operation_stage';
    if (source.includes('kieungay')) return 'date_type';
    if (source.includes('contacttype') || source.includes('loaikhach') || source.includes('trangthaikhachcu')) return 'customer_type';
    if (source.includes('trangthaiphanbo')) return 'allocation_status';
    if (source.includes('phuongthucgiaohang')) return 'shipping_method';
    if (source.includes('doisoatnoibo')) return 'internal_reconciliation_status';
    if (source.includes('trangthaitrungso')) return 'duplicate_status';
    if (source.includes('trangthai')) return 'status';
    return null;
}

function toIsoDate(value) {
    const normalized = String(value ?? '').trim();
    if (!normalized) return '';
    const match = normalized.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})/);
    if (match) return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString().slice(0, 10);
}

function collectTemplateFilters(host) {
    const params = {};
    if (!host) return params;

    host.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.disabled || field.type === 'hidden' || field.closest('.hidden')) return;

        const rawValue = field.type === 'checkbox' ? (field.checked ? '1' : '') : String(field.value ?? '').trim();
        if (!rawValue || ['-1'].includes(rawValue)) return;

        if (field.classList.contains('date-range') || /tungay[_$-]?denngay/i.test(`${field.id ?? ''} ${field.name ?? ''}`)) {
            const parts = rawValue.split(/\s+-\s+/);
            const dateFrom = toIsoDate(parts[0]);
            const dateTo = toIsoDate(parts[1] ?? parts[0]);
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
            return;
        }

        const key = inferFilterKey(field);
        if (!key) return;
        if (rawValue !== '0' || ['closed_status', 'customer_type', 'operation_state', 'allocation_status'].includes(key)) {
            params[key] = rawValue;
        }
    });

    host.querySelectorAll('.ps-ddl').forEach((dropdown) => {
        const selected = dropdown.querySelector('.ps-ddl-selected-item');
        const value = selected?.getAttribute('item-id') ?? '';
        const key = inferFilterKey(dropdown);
        if (key && value && !['-1', '0'].includes(value)) params[key] = value;
    });

    return params;
}

function optionsForControl(id, filterOptions) {
    const source = String(id ?? '').toLowerCase();
    const combine = (...groups) => {
        const unique = new Map();
        groups.flat().forEach((option) => unique.set(String(option.id), option));
        return [...unique.values()];
    };
    if (source.includes('lichsudangnhap') && source.includes('ddldonvi')) return filterOptions?.companies ?? [];
    if (source.includes('lichsudangnhap') && source.includes('ddlchucvu')) return filterOptions?.roles ?? [];
    if (source.includes('lichsudangnhap') && (source.includes('ddlusers') || source.includes('ddluser'))) return filterOptions?.loginUsers ?? filterOptions?.users ?? [];
    if (source.includes('lichsudangnhap') && source.includes('ddltrangthai')) return filterOptions?.loginStatuses ?? [];
    if (source.includes('lichsudangnhap') && source.includes('ddlsapxep')) return filterOptions?.loginSorts ?? [];
    if (source.includes('ddlusers') || source.includes('ddluser')) return filterOptions?.users ?? [];
    if (source.includes('ddlchucvu') || source.includes('role')) return filterOptions?.roles ?? [];
    if (source.includes('ddldonvi') || source.includes('company')) return filterOptions?.companies ?? [];
    if (source.includes('danhsachsanpham') && source.includes('ddlcatid')) return filterOptions?.productCategories ?? [];
    if (source.includes('danhsachsanpham') && source.includes('ddlmarketing')) return filterOptions?.availabilityOptions ?? [];
    if (source.includes('danhsachsanpham') && source.includes('ddlsale')) return filterOptions?.availabilityOptions ?? [];
    if (source.includes('danhsachsanpham') && source.includes('ddlcskh')) return filterOptions?.availabilityOptions ?? [];
    if (source.includes('sanphamgroup')) return filterOptions?.productGroups ?? filterOptions?.products ?? [];
    if (source.includes('truongnhom')) return filterOptions?.teamLeaders ?? filterOptions?.users ?? [];
    if (source.includes('leadersaleormarketing')) return combine(filterOptions?.saleLeaders ?? [], filterOptions?.marketingLeaders ?? []);
    if (source.includes('caredonuserid') || source.includes('careuserid') || source.includes('ddlcskh')) return filterOptions?.careUsers ?? filterOptions?.users ?? [];
    if (source.includes('quankho_userid')) return filterOptions?.warehouseUsers ?? filterOptions?.users ?? [];
    if (source.includes('leadersale')) return filterOptions?.saleLeaders ?? filterOptions?.sales ?? [];
    if (source.includes('nhomsale') || source.includes('teamsale') || source.endsWith('ddlnhom')) return filterOptions?.saleTeams ?? filterOptions?.teams ?? [];
    if (source.includes('saleuserid') || source.includes('idsale')) return filterOptions?.sales ?? [];
    if (source.includes('leadermkt')) return filterOptions?.marketingLeaders ?? filterOptions?.marketers ?? [];
    if (source.includes('nhommkt') || source.includes('teammarketing')) return filterOptions?.marketingTeams ?? filterOptions?.teams ?? [];
    if (source.includes('marketinguserid') || source.includes('idmarketing') || source.includes('ddlmarketing') || source.includes('ddlmarketings')) return filterOptions?.marketers ?? [];
    if ((source.includes('ddlsale') || source.includes('ddlsales')) && !source.includes('leader')) return filterOptions?.sales ?? [];
    if (source.includes('nghiepvukho') || source.includes('loaiphieu')) return filterOptions?.warehouseVoucherTypes ?? voucherTypeOptions();
    // Tránh match nhầm mọi control có "CapNhatPhieuXuatNhapKho" chứa chữ "kho".
    if (source.includes('sanphamkho') || source.includes('id_san_pham_kho')) return filterOptions?.products ?? [];
    if (source.includes('sanpham') || source.includes('product')) return filterOptions?.products ?? [];
    if (source.includes('landing') || source.includes('nguon')) return filterOptions?.sources ?? [];
    if (
        source.includes('__idkho')
        || source.includes('_idkho')
        || /(^|[^a-z])idkho([^a-z]|$)/.test(source)
        || source.includes('warehouse')
    ) {
        return filterOptions?.warehouses ?? [];
    }
    return [];
}


function normalizeTemplateLayout(host) {
    if (!host) return;

    host.classList.add('pushsale-template-host-v83');

    // Captured Pushsale HTML often includes an inner `.content-wrapper` and
    // one-off spacer styles (`padding-top: 50px`, `height: 100px`). In the
    // React/Admin shell those clash with the real fixed content wrapper and
    // create inconsistent blank bands at the top of many pages. Mark them here
    // so the final CSS contract can neutralize only native-template artifacts.
    host.querySelectorAll('.content-wrapper').forEach((node) => {
        node.classList.add('pushsale-nested-content-wrapper');
    });

    host.querySelectorAll('[style]').forEach((node) => {
        const inline = node.getAttribute('style') ?? '';
        if (/padding-top\s*:\s*(?:4[5-9]|[5-9]\d|[1-9]\d{2,})px/i.test(inline)) {
            node.classList.add('pushsale-native-top-spacer');
        }
        const isEmptyVisualSpacer = /height\s*:\s*(?:6\d|[7-9]\d|[1-9]\d{2,})px/i.test(inline)
            && !node.textContent?.trim()
            && !node.querySelector('input, select, textarea, button, a, table, [data-pushsale-grid-anchor], [data-pushsale-pagination-anchor]');
        if (isEmptyVisualSpacer) node.classList.add('pushsale-native-empty-spacer');
    });

    host.querySelectorAll('.content-header').forEach((node) => {
        if (!node.textContent?.trim() && !node.querySelector('input, select, textarea, button, a, table')) {
            node.classList.add('pushsale-template-empty-spacer');
            node.setAttribute('aria-hidden', 'true');
        }
    });

    host.querySelectorAll('.m-header-wrap').forEach((wrap, index) => {
        wrap.classList.toggle('pushsale-primary-header-wrap', index === 0);
        wrap.dataset.pushsaleChromeRole = index === 0 ? 'primary-header' : 'secondary-header';
    });

    host.querySelectorAll('.m-header').forEach((row, index) => {
        row.classList.add('pushsale-header-row');
        row.dataset.pushsaleHeaderRow = index === 0 ? 'primary' : 'secondary';

        [...row.children].forEach((column) => {
            if (!column.matches?.('[class*="col-"]')) return;
            const hasTitle = Boolean(column.querySelector('[id$="lblModuleTitle"], .module-title, .ps-title, .text'));
            const hasSearchAction = Boolean(column.querySelector('[data-pushsale-action="search"], [id$="btnSearch"], .btn-reload'));
            const hasControls = Boolean(column.querySelector('select, .ps-ddl, input:not([type="hidden"]):not([type="file"]), textarea'));
            column.classList.toggle('pushsale-header-title-col', hasTitle);
            column.classList.toggle('pushsale-header-actions-col', hasSearchAction);
            column.classList.toggle('pushsale-header-filter-col', !hasTitle && !hasSearchAction && hasControls);
        });
    });

    host.querySelectorAll('.box-body .row, .m-header-wrap .row').forEach((row) => {
        if (row.closest('[role="dialog"]')) return;
        const controls = row.querySelectorAll('select, .ps-ddl, input:not([type="hidden"]):not([type="file"]), textarea');
        const hasDataGrid = row.querySelector('table, [data-pushsale-grid-anchor], [data-pushsale-pagination-anchor]');
        const hasBootstrapColumns = [...row.children].some((child) => /(^|\s)col-(xs|sm|md|lg)-\d+/.test(child.className ?? ''));

        if (controls.length >= 1 && hasBootstrapColumns && !hasDataGrid) {
            row.classList.add('pushsale-filter-row');
        }

        if (controls.length >= 1 && hasBootstrapColumns && hasDataGrid) {
            row.classList.add('pushsale-composite-filter-row');
            [...row.children].forEach((column) => {
                const columnHasGrid = Boolean(column.querySelector('table, [data-pushsale-grid-anchor], [data-pushsale-pagination-anchor]'));
                column.classList.toggle('pushsale-main-table-cell', columnHasGrid);
                column.classList.toggle('pushsale-filter-cell', !columnHasGrid);
            });
        }
    });

    host.querySelectorAll('.pushsale-filter-row, .pushsale-composite-filter-row, .pushsale-header-row').forEach((row) => {
        [...row.children].forEach((column) => {
            if (!column.matches?.('[class*="col-"]')) return;
            const hasInteractive = column.querySelector('input:not([type="hidden"]), select, textarea, button, a, .ps-ddl, table');
            const hasText = Boolean(column.textContent?.replace(/\s+/g, ' ').trim());
            if (!hasInteractive && !hasText) column.classList.add('pushsale-empty-column');
        });
    });
}

function LoginUserQuickFilters({ users = [], routeUrl }) {
    const current = new URLSearchParams(window.location.search).get('user_id') ?? '';
    const visit = (userId) => {
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        if (userId) params.set('user_id', String(userId)); else params.delete('user_id');
        router.get(routeUrl, Object.fromEntries(params.entries()), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
    };

    return (
        <div className="pushsale-login-user-summary" aria-label="Nhân viên có trong hệ thống">
            {users.map((user) => (
                <button
                    type="button"
                    key={user.id}
                    className={`pushsale-login-user-chip ${String(user.id) === String(current) ? 'is-active' : ''}`}
                    onClick={() => visit(user.id)}
                    title={`${user.name ?? user.label}${user.role_label ? ` · ${user.role_label}` : ''}`}
                >
                    <span className="flag" aria-hidden="true" />
                    <span className="label">{user.name ?? user.label}</span>
                    <span className="count">({numberFormatter.format(Number(user.login_count ?? 0))})</span>
                </button>
            ))}
            <button
                type="button"
                className={`pushsale-login-user-chip ${current ? '' : 'is-active'}`}
                onClick={() => visit('')}
            >
                <span className="flag" aria-hidden="true" style={{ background: '#b8def2' }} />
                <span className="label">Tất cả</span>
            </button>
        </div>
    );
}

function SalesRankingCards({ rows = [] }) {
    const topRows = rows.slice(0, 10);
    if (!topRows.length) {
        return <div className="pushsale-ranking-empty">Chưa có dữ liệu xếp hạng trong khoảng lọc.</div>;
    }

    return (
        <div className="pushsale-ranking-cards" aria-label="Bảng xếp hạng Sales theo dữ liệu ERM">
            {topRows.map((row, index) => (
                <article className={`pushsale-ranking-card rank-${index + 1}`} key={`${row.sale}-${index}`}>
                    <div className="pushsale-ranking-position">{index + 1}</div>
                    <div className="pushsale-ranking-avatar" aria-hidden="true">
                        {String(row.sale ?? '?').trim().slice(0, 1).toUpperCase()}
                    </div>
                    <div className="pushsale-ranking-copy">
                        <strong>{row.sale ?? 'Chưa phân Sale'}</strong>
                        <span>{currencyFormatter.format(Number(row.revenue ?? row.total ?? 0))}</span>
                    </div>
                </article>
            ))}
        </div>
    );
}

function TemplateHost({ templateHtml = '', schema, rows, pagination, routeUrl, filterOptions, onCreate, onEdit, onDelete, onDeleteSelected, onPushsaleSave, onShowImportGuide, selectedRecordIds, onToggleSelect }) {
    const gridEnabled = schema.grid_enabled !== false;
    const [rowAnchor, setRowAnchor] = useState(null);
    const [paginationAnchor, setPaginationAnchor] = useState(null);
    const [chartAnchors, setChartAnchors] = useState([]);
    const [loginUsersAnchor, setLoginUsersAnchor] = useState(null);
    const [rankingAnchor, setRankingAnchor] = useState(null);
    const hostRef = useRef(null);

    useEffect(() => {
        if (!templateHtml) {
            setRowAnchor(null);
            setPaginationAnchor(null);
            setChartAnchors([]);
            setLoginUsersAnchor(null);
            setRankingAnchor(null);
            return;
        }
        const frame = requestAnimationFrame(() => {
            const host = hostRef.current;
            normalizeTemplateLayout(host);
            setRowAnchor(gridEnabled ? (host?.querySelector('[data-pushsale-grid-anchor="primary"]') ?? null) : null);
            setPaginationAnchor(gridEnabled ? (host?.querySelector('[data-pushsale-pagination-anchor="primary"]') ?? null) : null);
            setChartAnchors(schema.kind === 'trend' && host ? [...host.querySelectorAll('[data-highcharts-chart]')] : []);
            setLoginUsersAnchor(schema.code === '1.7.1' && host ? host.querySelector('[data-pushsale-login-user-summary]') : null);
            setRankingAnchor(null);

            if (schema.kind === 'ranking' && host) {
                const rankingContainer = host.querySelector('.bxh-container');
                if (rankingContainer) {
                    rankingContainer.querySelectorAll(':scope > .hidden-xs').forEach((node) => node.remove());
                    const table = rankingContainer.querySelector('[data-pushsale-grid-table="primary"]');
                    if (table?.parentElement) table.parentElement.style.marginTop = '12px';
                    let anchor = rankingContainer.querySelector('[data-pushsale-ranking-anchor]');
                    if (!anchor) {
                        anchor = document.createElement('div');
                        anchor.dataset.pushsaleRankingAnchor = '1';
                        rankingContainer.prepend(anchor);
                    }
                    setRankingAnchor(anchor);
                }
            }

            if (host) {
                host.querySelectorAll('tbody[data-pushsale-captured-data-removed="1"]').forEach((tbody) => {
                    const table = tbody.closest('table');
                    if (table && !table.querySelector('[data-pushsale-grid-anchor]') && !table.querySelector('input,select,textarea,button')) {
                        table.remove();
                    }
                });
                host.querySelectorAll('[data-pushsale-stale-counter="1"]').forEach((node) => {
                    node.textContent = pagination?.total
                        ? `Hiển thị ${pagination.from ?? 0} - ${pagination.to ?? 0} / ${numberFormatter.format(pagination.total)} bản ghi`
                        : 'Chưa có dữ liệu';
                });
                const walker = document.createTreeWalker(host, NodeFilter.SHOW_TEXT);
                let textNode = walker.nextNode();
                while (textNode) {
                    if (/Số\s*TK\s*:\s*\d+/i.test(textNode.nodeValue ?? '')) {
                        textNode.nodeValue = String(textNode.nodeValue).replace(/Số\s*TK\s*:\s*\d+/gi, `Số TK: ${pagination?.total ?? 0}`);
                    }
                    textNode = walker.nextNode();
                }

                if (schema.code === '1.7.3') {
                    const sideBody = host.querySelector('#dnn_ctr1745_Main_SearchFilterDataChotDonLog_pnlMain > .box-body > .row > .col-sm-4 table tbody');
                    if (sideBody) {
                        sideBody.querySelectorAll('tr[data-pushsale-user-count-row="1"]').forEach((row) => row.remove());
                        const counts = new Map();
                        rows.forEach((row) => {
                            const user = String(row.user || 'Hệ thống').trim() || 'Hệ thống';
                            counts.set(user, (counts.get(user) || 0) + 1);
                        });
                        [...counts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 12).forEach(([user, count], index) => {
                            const tr = document.createElement('tr');
                            tr.dataset.pushsaleUserCountRow = '1';
                            const indexCell = document.createElement('td');
                            indexCell.className = 'text-center';
                            indexCell.textContent = String(index + 1);
                            const userCell = document.createElement('td');
                            userCell.textContent = user;
                            const countCell = document.createElement('td');
                            countCell.className = 'text-center';
                            countCell.textContent = numberFormatter.format(count);
                            const actionCell = document.createElement('td');
                            actionCell.className = 'text-center';
                            actionCell.textContent = '';
                            tr.append(indexCell, userCell, countCell, actionCell);
                            sideBody.appendChild(tr);
                        });
                    }
                }
            }

            if (schema.code === '1.2.3' && host) {
                const controls = [...host.querySelectorAll('input[type="text"]:not([type="hidden"])')].filter((control) => control.offsetParent !== null || !control.style.display);
                const shifts = ['Ca 1', 'Ca 2', 'Ca 3'].map((name) => rows.find((row) => String(row.name ?? '').trim().toLocaleLowerCase('vi') === name.toLocaleLowerCase('vi')));
                shifts.forEach((shift, index) => {
                    if (!shift) return;
                    const from = controls[index * 2];
                    const to = controls[index * 2 + 1];
                    if (from) from.value = String(shift.from_hour ?? '').slice(0, 2).replace(/^0/, '') || '0';
                    if (to) to.value = String(shift.to_hour ?? '').slice(0, 2).replace(/^0/, '') || '0';
                });
            }
        });
        return () => cancelAnimationFrame(frame);
    }, [templateHtml, schema.code, gridEnabled, rows, pagination]);

    useEffect(() => {
        const host = hostRef.current;
        if (!host) return;
        const currentParams = new URLSearchParams(window.location.search);
        host.querySelectorAll('.ps-ddl').forEach((dropdown) => {
            const controlId = dropdown.id || dropdown.getAttribute('name');
            const options = optionsForControl(controlId, filterOptions);
            const result = dropdown.querySelector('.result-items');
            if (!result) return;
            result.innerHTML = '';
            result.classList.toggle('hidden', options.length === 0);
            options.slice(0, 500).forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pushsale-ddl-result';
                button.dataset.pushsaleOptionId = String(option.id);
                button.dataset.pushsaleOptionLabel = String(option.label ?? option.name ?? option.id);
                button.dataset.pushsaleOptionData = JSON.stringify(option);
                button.textContent = button.dataset.pushsaleOptionLabel;
                result.appendChild(button);
            });
            const key = inferFilterKey(dropdown);
            dropdown.classList.toggle('ps-ddl-disabled', options.length === 0);
            const queryValue = key ? currentParams.get(key) : null;
            const selectedOption = options.find((option) => String(option.id) === String(queryValue))
                ?? (options.length === 1 ? options[0] : null);
            const selected = dropdown.querySelector('.ps-ddl-selected-item');
            if (selectedOption && selected) {
                selected.textContent = String(selectedOption.label ?? selectedOption.name ?? selectedOption.id);
                selected.setAttribute('item-id', String(selectedOption.id));
                selected.dataset.optionData = JSON.stringify(selectedOption);
            } else if (selected && !selected.getAttribute('item-id')) {
                selected.textContent = selected.textContent || '-- Chọn --';
                selected.setAttribute('item-id', '-1');
                selected.dataset.optionData = '{}';
            }
            dropdown.classList.toggle('ps-ddl-disabled', options.length === 0);
            const searchBox = dropdown.querySelector('.ps-ddl-search-box');
            if (searchBox) {
                searchBox.disabled = options.length === 0;
                searchBox.classList.toggle('aspNetDisabled', options.length === 0);
            }
        });

        host.querySelectorAll('select').forEach((select) => {
            const options = optionsForControl(select.id || select.name, filterOptions);
            if (!options.length) return;
            const placeholder = select.options[0]?.textContent || '--Chọn--';
            const key = inferFilterKey(select);
            const current = (key ? currentParams.get(key) : null) ?? select.value;
            select.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '-1';
            empty.textContent = placeholder;
            select.appendChild(empty);
            options.slice(0, 2000).forEach((option) => {
                const node = document.createElement('option');
                node.value = String(option.id);
                node.textContent = String(option.label ?? option.name ?? option.id);
                select.appendChild(node);
            });
            const sourceForSelect = `${select.id ?? ''} ${select.name ?? ''}`.toLowerCase();
            const isVoucherTypeSelect = sourceForSelect.includes('nghiepvukho') || sourceForSelect.includes('loaiphieu');
            if ([...select.options].some((option) => option.value === current)) {
                select.value = current;
            } else if (isVoucherTypeSelect && options.length) {
                select.value = String(options[0].id);
            }
            select.disabled = false;
            select.classList.remove('aspNetDisabled');
        });
    }, [filterOptions, templateHtml, schema.code]);

    const runSearch = useCallback(() => {
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        const collected = collectTemplateFilters(hostRef.current);
        const knownKeys = [
            'search', 'date_from', 'date_to', 'sale_leader_id', 'sale_team_id', 'sale_id',
            'marketer_leader_id', 'marketer_team_id', 'marketer_id', 'product_id', 'source_id',
            'warehouse_id', 'closed_status', 'delivery_status', 'operation_result', 'operation_state',
            'operation_stage', 'date_type', 'customer_type', 'status', 'care_operation_status',
            'allocation_status', 'shipping_method', 'internal_reconciliation_status', 'duplicate_status',
            'company_id', 'role', 'user_id', 'login_status', 'login_permission_status', 'sort', 'care_user_id', 'warehouse_user_id',
            'category_id', 'parent_product_id', 'team_leader_id', 'active_status',
            'available_marketing', 'available_sale', 'available_care',
        ];
        knownKeys.forEach((key) => params.delete(key));
        Object.entries(collected).forEach(([key, value]) => params.set(key, String(value)));
        router.get(routeUrl, Object.fromEntries(params.entries()), { preserveState: false, preserveScroll: false, replace: true });
    }, [routeUrl]);

    useEffect(() => {
        const host = hostRef.current;
        if (!host) return undefined;
        const click = (event) => {
            const text = String(event.target?.textContent ?? '').trim().toLocaleLowerCase('vi');
            if (['1.10', '2.6.1'].includes(String(schema.code)) && text.includes('xem hướng dẫn')) {
                event.preventDefault();
                onShowImportGuide?.();
                return;
            }

            const closeButton = event.target.closest?.('[id$="btnDong"]');
            if (closeButton) {
                event.preventDefault();
                if (String(schema.code) === '5.3.1') {
                    router.visit('/admin/warehouse/vouchers');
                    return;
                }
            }

            const summaryToggle = event.target.closest?.('[id$="btnToggleSummary"], .btnToggleSummary, .ps-header-toggle');
            if (summaryToggle) {
                event.preventDefault();
                const wrap = summaryToggle.closest('.m-header-wrap');
                const target = wrap?.nextElementSibling;
                if (target) target.classList.toggle('hidden');
                const icon = summaryToggle.querySelector?.('i') || summaryToggle;
                if (icon?.classList) {
                    icon.classList.toggle('fa-angle-double-up');
                    icon.classList.toggle('fa-angle-double-down');
                }
                return;
            }

            const actionNode = event.target.closest?.('[data-pushsale-action]');
            const action = actionNode?.dataset.pushsaleAction;
            if (action) {
                event.preventDefault();
                if (action === 'search') runSearch();
                else if (action === 'guide') onShowImportGuide?.();
                else if (action === 'reload') router.reload({ preserveScroll: true });
                else if (action === 'export') {
                    const params = new URLSearchParams(window.location.search);
                    params.set('export', '1');
                    window.location.assign(`${routeUrl}?${params.toString()}`);
                } else if (action === 'print') window.print();
                else if (action === 'download_template') window.location.assign(schema.template_url || '/admin/leads/import-template');
                else if (action === 'import') {
                    if (String(schema.code) === '5.3.1') {
                        let fileInput = host.querySelector('input[data-pushsale-voucher-import]');
                        if (!fileInput) {
                            fileInput = document.createElement('input');
                            fileInput.type = 'file';
                            fileInput.accept = '.csv,text/csv';
                            fileInput.dataset.pushsaleVoucherImport = '1';
                            fileInput.className = 'hidden';
                            fileInput.addEventListener('change', async () => {
                                const file = fileInput.files?.[0];
                                if (!file) return;
                                try {
                                    const text = await file.text();
                                    const rows = text.replace(/^\uFEFF/, '').split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
                                    if (rows.length < 2) throw new Error('File CSV trống hoặc thiếu dữ liệu.');
                                    const headers = rows[0].split(',').map((cell) => cell.replace(/^"|"$/g, '').trim().toLowerCase());
                                    const skuIdx = headers.findIndex((h) => ['sku', 'mã sản phẩm', 'ma san pham', 'mã sp'].includes(h));
                                    const qtyIdx = headers.findIndex((h) => ['quantity', 'số lượng', 'so luong', 'sl'].includes(h));
                                    const costIdx = headers.findIndex((h) => ['unit_cost', 'giá nhập', 'gia nhap', 'đơn giá', 'don gia'].includes(h));
                                    const warehouseId = Number(host.querySelector('[id$="__IdKho"]')?.value) || null;
                                    const typeRaw = String(host.querySelector('[id$="__IdNghiepVuKho"]')?.value ?? '1');
                                    const type = ['1', 'inbound', 'nhap', 'nhập kho'].includes(typeRaw.toLocaleLowerCase('vi')) ? 'inbound' : 'outbound';
                                    if (!warehouseId || warehouseId < 1) throw new Error('Vui lòng chọn kho trước khi import.');
                                    const products = filterOptions?.products ?? [];
                                    let imported = 0;
                                    for (const row of rows.slice(1)) {
                                        const cells = row.match(/("([^"]|"")*"|[^,]*)/g)?.map((cell) => cell.replace(/^"|"$/g, '').replace(/""/g, '"').trim()) ?? [];
                                        const sku = skuIdx >= 0 ? cells[skuIdx] : cells[0];
                                        const quantity = Number(qtyIdx >= 0 ? cells[qtyIdx] : cells[1]) || 0;
                                        const unitCost = Number(costIdx >= 0 ? cells[costIdx] : cells[2]) || 0;
                                        if (!sku || quantity <= 0) continue;
                                        const product = products.find((item) => String(item.sku ?? '').toLowerCase() === sku.toLowerCase()
                                            || String(item.label ?? '').toLowerCase().includes(sku.toLowerCase()));
                                        if (!product) continue;
                                        await requestJson(`${routeUrl}/records`, 'POST', {
                                            payload: {
                                                warehouse_id: warehouseId,
                                                code: `${type === 'inbound' ? 'PNK' : 'PXK'}-IMP-${Date.now()}-${imported}`,
                                                type,
                                                document_date: new Date().toISOString().slice(0, 10),
                                                product_id: product.id,
                                                document_quantity: quantity,
                                                quantity,
                                                unit_cost: unitCost,
                                            },
                                        });
                                        imported += 1;
                                    }
                                    if (!imported) throw new Error('Không import được dòng nào. Kiểm tra SKU và số lượng trong CSV.');
                                    router.reload({ preserveScroll: true, only: ['rows', 'pagination', 'summary'] });
                                } catch (exception) {
                                    window.alert(exception.message || 'Không thể import Excel/CSV phiếu kho.');
                                } finally {
                                    fileInput.value = '';
                                }
                            });
                            host.appendChild(fileInput);
                        }
                        fileInput.click();
                        return;
                    }
                    onPushsaleSave?.(host);
                }
                else if (action === 'delete') onDeleteSelected?.();
                else if (['create', 'add', 'new'].includes(action)) onCreate(actionNode);
                else if (action === 'save') onPushsaleSave ? onPushsaleSave(host) : onCreate(actionNode);
                return;
            }

            const uploadButton = event.target.closest?.('.app-btn-upload-file');
            if (uploadButton) {
                event.preventDefault();
                uploadButton.closest('.app-file-upload')?.querySelector('input[type="file"]')?.click();
                return;
            }

            const option = event.target.closest?.('[data-pushsale-option-id]');
            if (option) {
                event.preventDefault();
                event.stopPropagation();
                const dropdown = option.closest('.ps-ddl');
                const selected = dropdown?.querySelector('.ps-ddl-selected-item');
                if (selected) {
                    selected.textContent = option.dataset.pushsaleOptionLabel || '';
                    selected.setAttribute('item-id', option.dataset.pushsaleOptionId || '');
                    selected.dataset.optionData = option.dataset.pushsaleOptionData || '{}';
                }
                dropdown?.querySelector('.ps-ddl-search-area')?.classList.add('hidden');
                dropdown?.classList.remove('is-open');
                return;
            }
            const dropdown = event.target.closest?.('.ps-ddl');
            if (dropdown && !event.target.closest('input')) {
                if (dropdown.classList.contains('ps-ddl-disabled')) return;
                dropdown.classList.toggle('is-open');
                dropdown.querySelector('.ps-ddl-search-area')?.classList.toggle('hidden');
            }
        };
        const input = (event) => {
            if (event.target.matches?.('input[type="file"]')) {
                const label = event.target.closest('.app-file-upload')?.querySelector('label.app-file-upload-chooser');
                if (label) label.textContent = event.target.files?.[0]?.name || 'Chọn file...';
                return;
            }
            const search = event.target.closest?.('.ps-ddl-search-box');
            if (!search) return;
            const needle = String(search.value ?? '').trim().toLocaleLowerCase('vi');
            search.closest('.ps-ddl-search-area')?.querySelectorAll('[data-pushsale-option-id]').forEach((option) => {
                option.hidden = needle !== '' && !String(option.dataset.pushsaleOptionLabel ?? '').toLocaleLowerCase('vi').includes(needle);
            });
        };
        host.addEventListener('click', click);
        host.addEventListener('input', input);
        return () => {
            host.removeEventListener('click', click);
            host.removeEventListener('input', input);
        };
    }, [filterOptions, onCreate, onDeleteSelected, onPushsaleSave, onShowImportGuide, routeUrl, runSearch, schema.code, schema.template_url]);

    const rowProps = { schema, rows, onEdit, onDelete, selectedRecordIds, onToggleSelect };
    return (
        <>
            <div
                className={`pushsale-template-host pushsale-template-page-${String(schema.code ?? '').replaceAll('.', '-')}`}
                data-page-code={schema.code}
                ref={hostRef}
                dangerouslySetInnerHTML={{ __html: templateHtml }}
            />
            {gridEnabled && rowAnchor && createPortal(<PushsaleRows {...rowProps} />, rowAnchor)}
            {gridEnabled && paginationAnchor && createPortal(<Pagination pagination={pagination} routeUrl={routeUrl} />, paginationAnchor)}
            {chartAnchors.map((anchor, index) => createPortal(<TrendMetricChart row={rows[index]} />, anchor, `trend-${index}`))}
            {loginUsersAnchor && createPortal(<LoginUserQuickFilters users={filterOptions?.loginUsers ?? filterOptions?.users ?? []} routeUrl={routeUrl} />, loginUsersAnchor)}
            {rankingAnchor && createPortal(<SalesRankingCards rows={rows} />, rankingAnchor)}
            {gridEnabled && !rowAnchor && templateHtml && <div className="pushsale-template-fallback"><PushsaleFallbackGrid {...rowProps} pagination={pagination} routeUrl={routeUrl} /></div>}
            {!templateHtml && <div className="pushsale-template-loading"><i className="fa fa-spinner fa-spin" /> Chưa có nội dung template cho mã {schema.code}.</div>}
        </>
    );
}


function ImportGuideDialog({ open, onClose, templateUrl }) {
    if (!open) return null;

    return (
        <div className="ps-import-guide-modal" role="dialog" aria-modal="true" aria-label="Hướng dẫn import contact" onClick={onClose}>
            <div className="ps-import-guide-dialog" onClick={(event) => event.stopPropagation()}>
                <div className="ps-import-guide-header">
                    <h3>Hướng dẫn import contact</h3>
                    <button type="button" onClick={onClose} aria-label="Đóng"><i className="fa fa-times" /></button>
                </div>
                <div className="ps-import-guide-body">
                    <div className="ps-import-guide-note">
                        <b>Quy trình chuẩn</b>
                        <ol>
                            <li>Tải file mẫu Excel từ nút <b>B1. Tải mẫu Excel</b>.</li>
                            <li>Nhập dữ liệu theo đúng các cột trong file mẫu, không đổi tên cột bắt buộc.</li>
                            <li>Chọn file ở bước <b>B2</b>, sau đó bấm <b>Upload</b> ở bước <b>B3</b>.</li>
                            <li>Bật <b>Kiểm trùng hệ thống</b> khi cần so số điện thoại với dữ liệu đang có trong hệ thống trước khi chia sale.</li>
                            <li>Sau khi import, vào <b>Lịch sử import</b> để kiểm tra số dòng hợp lệ, số trùng, số lỗi và trạng thái xử lý.</li>
                        </ol>
                    </div>
                    <div className="ps-import-guide-grid">
                        <section>
                            <h4>Quy tắc dữ liệu</h4>
                            <ul>
                                <li>File nên dưới 5.000 dòng mỗi lần import để queue xử lý ổn định.</li>
                                <li>Số điện thoại là khóa chống trùng chính; hệ thống sẽ chuẩn hóa số trước khi ghi nhận.</li>
                                <li>Các cột sản phẩm, nguồn dữ liệu, ghi chú, địa chỉ sẽ được map vào lead/customer/order draft theo cấu hình hiện tại.</li>
                                <li>Dòng lỗi sẽ được lưu vào lịch sử import để rà soát, không ghi đè dữ liệu hợp lệ.</li>
                            </ul>
                        </section>
                        <section>
                            <h4>Liên kết nghiệp vụ</h4>
                            <ul>
                                <li>Lead hợp lệ đi vào hàng chờ phân bổ data.</li>
                                <li>Nếu có sản phẩm/nguồn dữ liệu, luật phân quyền Marketing/Sale theo sản phẩm vẫn được áp dụng.</li>
                                <li>Lead trùng hoặc khách cũ được đánh dấu để review, tránh chia trùng cho nhiều sale.</li>
                                <li>Import không tự chốt đơn; sale vẫn phải tác nghiệp và chốt theo luồng hiện tại.</li>
                            </ul>
                        </section>
                    </div>
                    {templateUrl ? (
                        <div className="ps-import-guide-actions">
                            <a className="btn btn-sm btn-primary" href={templateUrl}><i className="fa fa-cloud-download" /> Tải file mẫu</a>
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
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
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const fromErrors = Object.values(body.errors ?? {}).flat().filter(Boolean).join(' ');
            throw new Error(fromErrors || body.message || 'Không thể lưu dữ liệu.');
        }
        return body;
    });
}

function requestFormData(url, formData) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    }).then(async (response) => {
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || Object.values(body.errors ?? {}).flat().join(' ') || 'Không thể nhập dữ liệu.');
        }
        return response.json().catch(() => ({}));
    });
}

export default function PushsaleBusinessPage({ schema, rows = [], pagination, summary = {}, routeUrl, templateHtml = '', dialogTemplates = {}, filterOptions = {}, pageRuntimeError = null }) {
    const [editor, setEditor] = useState({ open: false, row: null, dialogCode: null, dialogSchema: null });
    const [error, setError] = useState('');
    const [importGuideOpen, setImportGuideOpen] = useState(false);
    const [selectedRecordIds, setSelectedRecordIds] = useState(() => new Set());
    const [confirmAction, setConfirmAction] = useState(null);

    const dialogEntries = Object.entries(schema.dialog_resource_schemas ?? {});
    const resolveDialog = useCallback((actionNode = null) => {
        const label = String(actionNode?.textContent ?? actionNode?.value ?? '').toLocaleLowerCase('vi');
        let entry = null;
        if (label.includes('phân loại')) entry = dialogEntries.find(([code]) => code.includes('ph#U00e2n'));
        else if (label.includes('giá trị')) entry = dialogEntries.find(([code]) => code.includes('gi#U00e1'));
        else if (label.includes('thuộc tính')) entry = dialogEntries.find(([code]) => code.includes('thu#U1ed9c'));
        else entry = dialogEntries.find(([, value]) => value.alias === 'create') ?? dialogEntries[0];
        if (!entry) {
            const code = schema.dialogs?.[0] ?? null;
            return { dialogCode: code, dialogSchema: null };
        }
        const [dialogCode, dialogSchema] = entry;
        return { dialogCode, dialogSchema: { ...dialogSchema, title: label ? actionNode.textContent.trim() : schema.title } };
    }, [dialogEntries, schema.dialogs, schema.title]);

    const openCreate = useCallback((actionNode = null) => {
        if (!schema.editable) {
            if (schema.create_url) router.visit(schema.create_url);
            return;
        }
        const resolved = resolveDialog(actionNode);
        setEditor({ open: true, row: null, ...resolved });
    }, [resolveDialog, schema.create_url, schema.editable]);

    const openEdit = useCallback((row) => {
        setEditor({ open: true, row, dialogCode: schema.dialogs?.[0] ?? null, dialogSchema: null });
    }, [schema.dialogs]);

    const editorDialogHtml = editor.dialogCode ? (dialogTemplates[editor.dialogCode] ?? '') : '';

    const save = async (payload, recordId) => {
        const dialogStore = editor.dialogSchema?.store_url;
        const target = dialogStore
            ? (recordId ? `${dialogStore}/${recordId}` : dialogStore)
            : (recordId ? `${routeUrl}/records/${recordId}` : `${routeUrl}/records`);
        await requestJson(target, recordId ? 'PUT' : 'POST', { payload });
        router.reload({ preserveScroll: true, only: ['schema', 'rows', 'pagination', 'summary', 'filterOptions'] });
    };

    const performRemove = useCallback(async (row) => {
        if (!row?._record_id) return;
        setError('');
        try {
            await requestJson(`${routeUrl}/records/${row._record_id}`, 'DELETE');
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (exception) {
            setError(exception.message);
        }
    }, [routeUrl]);

    const remove = useCallback((row) => {
        if (!row?._record_id) return;
        const label = row.name || row.title || row.code || row._display || `#${row._record_id}`;
        setConfirmAction({
            title: 'Xóa dữ liệu?',
            description: `Xóa "${label}"? Nếu dữ liệu này đang được dùng ở tác nghiệp, đơn hàng hoặc báo cáo thì các phần liên quan có thể bị ảnh hưởng.`,
            onConfirm: () => performRemove(row),
        });
    }, [performRemove]);

    const toggleSelectedRecord = useCallback((recordId) => {
        setSelectedRecordIds((current) => {
            const next = new Set(current);
            const key = String(recordId);
            if (next.has(key)) next.delete(key); else next.add(key);
            return next;
        });
    }, []);

    const performRemoveSelected = useCallback(async (ids) => {
        setError('');
        try {
            await Promise.all(ids.map((id) => requestJson(`${routeUrl}/records/${id}`, 'DELETE')));
            setSelectedRecordIds(new Set());
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (exception) {
            setError(exception.message);
        }
    }, [routeUrl]);

    const removeSelected = useCallback(() => {
        const ids = [...selectedRecordIds];
        if (!ids.length) { setError('Vui lòng chọn ít nhất một bản ghi có thể xóa.'); return; }
        setConfirmAction({
            title: 'Xóa các bản ghi đã chọn?',
            description: `Bạn đang xóa ${ids.length} bản ghi. Nếu dữ liệu này đang được dùng ở tác nghiệp, đơn hàng hoặc báo cáo thì các phần liên quan có thể bị ảnh hưởng.`,
            onConfirm: () => performRemoveSelected(ids),
        });
    }, [performRemoveSelected, selectedRecordIds]);

    const handlePushsaleSave = useCallback(async (host) => {
        if (schema.code === '1.2.3') {
            const controls = [...(host?.querySelectorAll('input[type="text"]') ?? [])].filter((control) => !control.disabled && control.type !== 'hidden');
            const values = controls.slice(0, 6).map((control) => Number(String(control.value ?? '').trim()));
            if (values.length < 6 || values.some((value) => !Number.isInteger(value) || value < 0 || value > 24)) {
                setError('Giờ bắt đầu/kết thúc của ba ca phải là số nguyên trong khoảng 0–24.');
                return;
            }
            setError('');
            try {
                await requestJson(`${routeUrl}/schedule`, 'POST', {
                    shifts: [
                        { name: 'Ca 1', from_hour: values[0], to_hour: values[1] },
                        { name: 'Ca 2', from_hour: values[2], to_hour: values[3] },
                        { name: 'Ca 3', from_hour: values[4], to_hour: values[5] },
                    ],
                });
                router.reload({ preserveScroll: true, only: ['rows'] });
            } catch (exception) {
                setError(exception.message);
            }
            return;
        }

        if (schema.code === '1.11') {
            const panel = [...(host?.querySelectorAll('table') ?? [])].find((table) => table.querySelectorAll('input:not([type=hidden]), select').length >= 4 && table.textContent?.includes('Fanpage') && table.textContent?.includes('Creator'));
            const controls = [...(panel?.querySelectorAll('input:not([type=hidden]), select') ?? [])].filter((control) => !control.disabled);
            const [pageIdInput, pageNameInput, creatorInput, marketerSelect] = controls;
            const pageId = String(pageIdInput?.value ?? '').trim();
            const pageName = String(pageNameInput?.value ?? '').trim();
            if (!pageId || !pageName) {
                setError('Vui lòng nhập PageID và tên Fanpage.');
                return;
            }
            setError('');
            try {
                await requestJson(`${routeUrl}/records`, 'POST', {
                    payload: {
                        page_id: pageId,
                        page_name: pageName,
                        creator_name: String(creatorInput?.value ?? '').trim(),
                        marketer_user_id: marketerSelect?.value || null,
                        is_active: true,
                    },
                });
                controls.forEach((control) => { if (control.tagName === 'SELECT') control.value = ''; else control.value = ''; });
                router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
            } catch (exception) {
                setError(exception.message);
            }
            return;
        }


        if (schema.code === '5.3.1') {
            const findValue = (suffix) => String(host?.querySelector(`[id$="${suffix}"]`)?.value ?? '').trim();
            const warehouseId = Number(findValue('__IdKho')) || null;
            const typeRaw = findValue('__IdNghiepVuKho');
            const type = ['1', 'inbound', 'nhap', 'nhập kho'].includes(typeRaw.toLocaleLowerCase('vi')) ? 'inbound' : 'outbound';
            const selectedProduct = selectedDropdownOption(host, ['ddlIdSanPham', 'ddlIdSanPhamTonKho']);
            const quantity = toNumberInputValue(voucherFieldValue(host, 'quantity'), 0) || toNumberInputValue(voucherFieldValue(host, 'document_quantity'), 0);
            const documentQuantity = toNumberInputValue(voucherFieldValue(host, 'document_quantity'), quantity);
            const unitCost = toNumberInputValue(voucherFieldValue(host, 'unit_cost'), 0);
            const codePrefix = type === 'inbound' ? 'PNK' : 'PXK';
            const code = findValue('__MaPhieu') || `${codePrefix}-${Date.now()}`;
            const documentDate = toIsoDate(findValue('__NgayThucHien')) || new Date().toISOString().slice(0, 10);

            if (!warehouseId) { setError('Vui lòng chọn kho cho phiếu nhập / xuất.'); return; }
            if (!selectedProduct.id) { setError('Vui lòng chọn sản phẩm cho phiếu nhập / xuất.'); return; }
            if (!quantity || quantity <= 0) { setError('Vui lòng nhập số lượng lớn hơn 0.'); return; }

            setError('');
            try {
                await requestJson(`${routeUrl}/records`, 'POST', {
                    payload: {
                        warehouse_id: warehouseId,
                        code,
                        type,
                        document_date: documentDate,
                        product_id: selectedProduct.id,
                        document_quantity: documentQuantity || quantity,
                        quantity,
                        unit_cost: unitCost || 0,
                        batch_code: voucherFieldValue(host, 'batch_code'),
                        expiry_date: voucherFieldValue(host, 'expiry_date') || null,
                        location_code: voucherFieldValue(host, 'location_code'),
                        note: voucherFieldValue(host, 'note'),
                    },
                });
                host?.querySelectorAll('[data-pushsale-voucher-field]').forEach((field) => {
                    if (field.dataset.pushsaleVoucherField === 'document_quantity' || field.dataset.pushsaleVoucherField === 'quantity') field.value = '1';
                    else field.value = '';
                });
                router.reload({ preserveScroll: true, only: ['rows', 'pagination', 'summary', 'filterOptions'] });
            } catch (exception) {
                setError(exception.message);
            }
            return;
        }

        if (['1.10', '2.6.1'].includes(schema.code)) {
            const file = host?.querySelector('input[type="file"]')?.files?.[0];
            if (!file) { setError('Vui lòng chọn file Excel cần import.'); return; }
            const formData = new FormData();
            formData.append('file', file);
            setError('');
            try {
                await requestFormData(schema.import_url || '/admin/leads/import', formData);
                router.reload({ preserveScroll: true });
            } catch (exception) {
                setError(exception.message);
            }
            return;
        }

        if (schema.code !== '2.6.2') { openCreate(); return; }
        const findValue = (suffix) => host?.querySelector(`[id$="${suffix}"]`)?.value?.trim() ?? '';
        const sourceNode = host?.querySelector('#dnn_ctr1525_Main_CapNhatContact__IdLandingThuCong .ps-ddl-selected-item');
        const productNode = host?.querySelector('#dnn_ctr1525_Main_CapNhatContact_ddlIdSanPham .ps-ddl-selected-item');
        const sourceId = Number(sourceNode?.getAttribute('item-id')) || null;
        let product = null;
        try { product = JSON.parse(productNode?.dataset.optionData || 'null'); } catch { product = null; }
        const phone = findValue('_KhachHangPhone');
        const phoneError = vietnamesePhoneError(phone, { required: true });
        if (phoneError) { setError(phoneError); toast.error(phoneError); return; }
        setError('');
        router.post('/admin/leads/manual', {
            name: findValue('_KhachHangName'),
            phone: normalizeVietnamesePhone(phone) ?? phone,
            message: findValue('_KhachHangMessage'),
            marketing_source_id: sourceId,
            items: product?.id ? [{ product_id: Number(product.id), item_type: product.type === 'combo' ? 'combo' : 'product', quantity: 1, unit_price: Number(product.unit_price || 0), discount_amount: 0 }] : [],
        }, {
            preserveScroll: true,
            onSuccess: () => {
                host?.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => { field.value = ''; });
                router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
            },
            onError: (errors) => setError(errors.phone ?? errors.items ?? 'Không thể lưu data thủ công.'),
        });
    }, [openCreate, routeUrl, schema.code, schema.import_url]);

    const isVoucherEntryPage = String(schema.code) === '5.3.1';
    const voucherId = summary?.voucher_id ?? summary?.id ?? rows?.[0]?.voucher_id ?? '';
    const voucherTitle = voucherId
        ? `${schema.title} (${voucherId})`
        : schema.title;

    useEffect(() => {
        if (!isVoucherEntryPage || typeof document === 'undefined') return undefined;
        const titleNode = document.querySelector('[data-page-code="5.3.1"] [id$="lblModuleTitle"]');
        const idNode = document.querySelector('[data-page-code="5.3.1"] [id$="lblId"]');
        if (titleNode) titleNode.textContent = schema.title;
        if (idNode) idNode.textContent = voucherId ? String(voucherId) : 'mới';
        return undefined;
    }, [isVoucherEntryPage, schema.title, voucherId]);

    return (
        <AppLayout>
            <Head title={schema.title} />
            <div className={`pushsale-page pushsale-kind-${schema.kind}${isVoucherEntryPage ? ' ps-voucher-entry-page' : ''}`} data-page-code={schema.code}>
                {isVoucherEntryPage ? (
                    <PageHeader
                        title={voucherTitle}
                        pageCode="5.3.1"
                        className="ps-voucher-entry-header"
                        actions={(
                            <button
                                type="button"
                                className="btn btn-sm btn-default"
                                onClick={() => router.visit('/admin/warehouse/vouchers')}
                                aria-label="Đóng"
                            >
                                <i className="fa fa-close" /> Đóng
                            </button>
                        )}
                    />
                ) : null}
                {(error || pageRuntimeError) && <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {error || pageRuntimeError}</div>}
                {!['1.10', '2.6.1', '5.3.1'].includes(String(schema.code)) && <LiveDataSummary summary={summary} />}
                <TemplateHost
                    templateHtml={templateHtml}
                    schema={schema}
                    rows={rows}
                    pagination={pagination}
                    routeUrl={routeUrl}
                    filterOptions={filterOptions}
                    onCreate={openCreate}
                    onEdit={openEdit}
                    onDelete={remove}
                    onDeleteSelected={removeSelected}
                    onPushsaleSave={handlePushsaleSave}
                    onShowImportGuide={() => setImportGuideOpen(true)}
                    selectedRecordIds={selectedRecordIds}
                    onToggleSelect={toggleSelectedRecord}
                />
            </div>
            {['1.10', '2.6.1'].includes(String(schema.code)) ? (
                <ImportGuideDialog open={importGuideOpen} onClose={() => setImportGuideOpen(false)} templateUrl={schema.template_url || '/admin/leads/import-template'} />
            ) : null}
            <PushsaleEditorDialog
                open={editor.open}
                schema={schema}
                row={editor.row}
                dialogHtml={editorDialogHtml}
                dialogSchema={editor.dialogSchema}
                filterOptions={filterOptions}
                onClose={() => setEditor({ open: false, row: null, dialogCode: null, dialogSchema: null })}
                onSaved={save}
            />
            <ConfirmActionDialog
                open={Boolean(confirmAction)}
                title={confirmAction?.title}
                description={confirmAction?.description}
                onCancel={() => setConfirmAction(null)}
                onConfirm={async () => {
                    const action = confirmAction?.onConfirm;
                    setConfirmAction(null);
                    await action?.();
                }}
            />
        </AppLayout>
    );
}

