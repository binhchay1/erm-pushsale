import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const numberFormatter = new Intl.NumberFormat('vi-VN');
const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

function formatCurrency(value) {
    return currencyFormatter.format(Number(value) || 0);
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function requestJson(url, method, payload) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const errors = Object.values(body.errors ?? {}).flat().join(' ');
        throw new Error(errors || body.message || 'Không thể lưu combo.');
    }

    return response.json().catch(() => ({}));
}

function currentParam(key, fallback = '') {
    return new URLSearchParams(window.location.search).get(key) ?? fallback;
}

function toDateTimeRange(from, to) {
    if (!from && !to) return '';
    const fmt = (value, fallbackTime) => {
        if (!value) return '';
        const [date, time] = String(value).split(' ');
        if (date?.includes('-')) {
            const [y, m, d] = date.split('-');
            return `${d}/${m}/${y} ${time || fallbackTime}`;
        }
        return String(value);
    };
    return `${fmt(from, '00:00')} - ${fmt(to || from, '23:59')}`;
}

function parseDateRange(value) {
    const [rawFrom = '', rawTo = ''] = String(value || '').split(/\s+-\s+/);
    const parseOne = (raw) => {
        const match = raw.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (!match) return '';
        return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
    };
    return { date_from: parseOne(rawFrom), date_to: parseOne(rawTo || rawFrom) };
}

function normalizeComboPayload(form) {
    const componentItems = (form.component_items ?? [])
        .map((item) => ({
            product_id: Number(item.product_id || 0),
            quantity: Math.max(1, Number(item.quantity || 1)),
            unit_price: Math.max(0, Number(item.unit_price || 0)),
        }))
        .filter((item) => item.product_id > 0);

    return {
        sku: String(form.sku ?? '').trim(),
        name: String(form.name ?? '').trim(),
        unit_price: Math.max(0, Number(form.unit_price || 0)),
        component_product_ids: componentItems.map((item) => item.product_id),
        component_items: componentItems,
        is_active: Boolean(form.is_active),
    };
}

function ComboDialog({ open, mode, combo, products, onClose, onSaved, routeUrl }) {
    const initial = useMemo(() => {
        const data = combo?._form ?? combo ?? {};
        const items = (data.component_items ?? []).length
            ? data.component_items
            : (data.component_product_ids ?? []).map((id) => {
                const product = products.find((item) => String(item.id) === String(id));
                return { product_id: Number(id), quantity: 1, unit_price: Number(product?.unit_price || 0) };
            });

        return {
            sku: data.sku ?? combo?.code ?? data.code ?? '',
            name: data.name ?? combo?.name ?? '',
            unit_price: Number(data.unit_price ?? data.combo_total ?? 0),
            component_items: items.map((item) => ({
                product_id: Number(item.product_id || item.component_product_id || 0),
                quantity: Math.max(1, Number(item.quantity || 1)),
                unit_price: Math.max(0, Number(item.unit_price || 0)),
            })).filter((item) => item.product_id > 0),
            is_active: data.is_active ?? String(data.status ?? '').toLowerCase().includes('áp dụng') ?? true,
        };
    }, [combo, products]);

    const [form, setForm] = useState(initial);
    const [selectedProductId, setSelectedProductId] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    if (!open) return null;

    const productMap = new Map(products.map((item) => [String(item.id), item]));
    const originalTotal = form.component_items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.unit_price || 0)), 0);
    const difference = originalTotal - Number(form.unit_price || 0);
    const isUpdate = mode === 'update';
    const title = isUpdate ? 'Cập nhật combo' : 'Thêm combo';

    const addProduct = () => {
        if (!selectedProductId) {
            setError('Vui lòng chọn sản phẩm trước khi thêm vào combo.');
            return;
        }
        const product = productMap.get(String(selectedProductId));
        if (!product) {
            setError('Sản phẩm đã chọn không còn tồn tại trong catalog.');
            return;
        }
        if (form.component_items.some((item) => String(item.product_id) === String(product.id))) {
            setError('Sản phẩm này đã nằm trong combo.');
            return;
        }
        setForm((current) => ({
            ...current,
            component_items: [
                ...current.component_items,
                { product_id: Number(product.id), quantity: 1, unit_price: Number(product.unit_price || 0) },
            ],
        }));
        setError('');
        setSelectedProductId('');
    };

    const updateItem = (index, key, value) => {
        setForm((current) => ({
            ...current,
            component_items: current.component_items.map((item, itemIndex) => (
                itemIndex === index ? { ...item, [key]: value } : item
            )),
        }));
    };

    const removeItem = (index) => {
        setForm((current) => ({
            ...current,
            component_items: current.component_items.filter((_, itemIndex) => itemIndex !== index),
        }));
    };

    const submit = async () => {
        const payload = normalizeComboPayload(form);
        if (!payload.sku) { setError('Vui lòng nhập mã combo.'); return; }
        if (!payload.name) { setError('Vui lòng nhập tên combo.'); return; }
        if (payload.component_items.length < 1) { setError('Vui lòng chọn ít nhất một sản phẩm trong combo.'); return; }

        setSaving(true);
        setError('');
        try {
            const url = isUpdate ? `${routeUrl}/records/${combo._record_id ?? combo.id}` : `${routeUrl}/records`;
            await requestJson(url, isUpdate ? 'PUT' : 'POST', { payload });
            onSaved?.();
            onClose();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => { if (!nextOpen) onClose(); }}
            width="1080px"
            title={title}
            className="ps-combo-dialog"
            footer={(
                <>
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button type="button" className="btn btn-primary btn-sm" disabled={saving} onClick={submit}>
                        <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {saving ? 'Đang lưu' : (isUpdate ? 'Cập nhật' : 'Thêm combo')}
                    </button>
                </>
            )}
        >
            <div className="ps-combo-form">
                {error && <div className="alert alert-danger"><i className="fa fa-exclamation-triangle" /> {error}</div>}
                <section className="ps-combo-section">
                    <div className="ps-combo-section-title">Thông tin combo</div>
                    <div className="ps-combo-grid">
                        <label>
                            <span>Mã combo <em>*</em></span>
                            <input
                                className="form-control"
                                value={form.sku}
                                disabled={isUpdate}
                                maxLength={50}
                                onChange={(event) => setForm((current) => ({ ...current, sku: event.target.value }))}
                                placeholder="Ví dụ: CB-GOI-02"
                            />
                            {isUpdate && <small>Mã combo không đổi sau khi tạo để không lệch lịch sử đơn hàng.</small>}
                        </label>
                        <label>
                            <span>Tên combo <em>*</em></span>
                            <input
                                className="form-control"
                                value={form.name}
                                maxLength={255}
                                onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                                placeholder="Nhập tên combo"
                            />
                        </label>
                        <label>
                            <span>Giá combo <em>*</em></span>
                            <input
                                className="form-control text-right"
                                type="number"
                                min="0"
                                value={form.unit_price}
                                onChange={(event) => setForm((current) => ({ ...current, unit_price: event.target.value }))}
                            />
                        </label>
                        <label className="ps-combo-check">
                            <input
                                type="checkbox"
                                checked={Boolean(form.is_active)}
                                onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
                            />
                            <span>Đang áp dụng</span>
                        </label>
                    </div>
                </section>

                <section className="ps-combo-section">
                    <div className="ps-combo-section-title">Sản phẩm trong combo</div>
                    <div className="ps-combo-picker">
                        <select className="form-control" value={selectedProductId} onChange={(event) => { setSelectedProductId(event.target.value); setError(''); }}>
                            <option value="">-- Chọn sản phẩm thêm vào combo --</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>{product.label ?? product.name}</option>
                            ))}
                        </select>
                        <button type="button" className="btn btn-primary btn-sm" onClick={addProduct}>
                            <i className="fa fa-plus" /> Thêm sản phẩm
                        </button>
                    </div>

                    <div className="table-responsive ps-combo-items-wrap">
                        <table className="table table-bordered table-condensed ps-combo-items-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 48 }}>#</th>
                                    <th>Sản phẩm</th>
                                    <th style={{ width: 120 }}>Số lượng</th>
                                    <th style={{ width: 150 }}>Đơn giá</th>
                                    <th style={{ width: 150 }}>Thành tiền</th>
                                    <th style={{ width: 50 }} />
                                </tr>
                            </thead>
                            <tbody>
                                {form.component_items.length ? form.component_items.map((item, index) => {
                                    const product = productMap.get(String(item.product_id));
                                    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);
                                    return (
                                        <tr key={`${item.product_id}-${index}`}>
                                            <td className="text-center">{index + 1}</td>
                                            <td>
                                                <strong>{product?.name ?? product?.label ?? item.product_id}</strong>
                                                {product?.sku && <small>{product.sku}</small>}
                                            </td>
                                            <td><input className="form-control text-right" type="number" min="1" value={item.quantity} onChange={(event) => updateItem(index, 'quantity', event.target.value)} /></td>
                                            <td><input className="form-control text-right" type="number" min="0" value={item.unit_price} onChange={(event) => updateItem(index, 'unit_price', event.target.value)} /></td>
                                            <td className="text-right"><strong>{formatCurrency(lineTotal)}</strong></td>
                                            <td className="text-center"><button type="button" className="btn btn-link text-danger" onClick={() => removeItem(index)}><i className="fa fa-trash" /></button></td>
                                        </tr>
                                    );
                                }) : (
                                    <tr><td colSpan="6" className="text-center text-muted">Chưa chọn sản phẩm trong combo.</td></tr>
                                )}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colSpan="4" className="text-right"><strong>Tổng giá gốc</strong></td>
                                    <td className="text-right"><strong>{formatCurrency(originalTotal)}</strong></td>
                                    <td />
                                </tr>
                                <tr>
                                    <td colSpan="4" className="text-right"><strong>Chênh lệch ưu đãi</strong></td>
                                    <td className="text-right"><strong>{formatCurrency(difference)}</strong></td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p className="ps-combo-note">Giá và thành phần combo lấy từ catalog thật. Khi sale chốt đơn, hệ thống dùng mã combo để giữ lịch sử doanh thu, đồng thời thành phần combo dùng cho xuất kho/tồn kho.</p>
                </section>
            </div>
        </PushsaleDialog>
    );
}

export default function Page({ schema, rows = [], pagination = {}, routeUrl, filterOptions = {}, pageRuntimeError = null }) {
    const productOptions = useMemo(() => (filterOptions.products ?? [])
        .filter((product) => product.type !== 'combo')
        .map((product) => ({ ...product, sku: product.sku ?? String(product.label ?? '').match(/\(([^)]+)\)$/)?.[1] ?? '' })), [filterOptions.products]);

    const [dialog, setDialog] = useState({ open: false, mode: 'create', combo: null });
    const [showTools, setShowTools] = useState(false);
    const [keyword, setKeyword] = useState(currentParam('search'));
    const [productId, setProductId] = useState(currentParam('product_id'));
    const [activeStatus, setActiveStatus] = useState(currentParam('active_status'));
    const [dateRange, setDateRange] = useState(toDateTimeRange(currentParam('date_from'), currentParam('date_to')));
    const [sort, setSort] = useState(currentParam('sort', 'created_desc'));
    const [error, setError] = useState(pageRuntimeError || '');

    const total = Number(pagination.total ?? rows.length ?? 0);
    const currentPage = Number(pagination.current_page || 1);
    const lastPage = Number(pagination.last_page || 1);
    const perPage = Number(pagination.per_page || 20);

    const visit = (page = 1, override = {}) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(page));
        params.set('per_page', String(perPage));
        const range = parseDateRange(override.dateRange ?? dateRange);
        const next = {
            search: override.keyword ?? keyword,
            product_id: override.productId ?? productId,
            active_status: override.activeStatus ?? activeStatus,
            date_from: range.date_from,
            date_to: range.date_to,
            sort: override.sort ?? sort,
        };
        Object.entries(next).forEach(([key, value]) => {
            if (value && !['-1', 'all'].includes(String(value))) params.set(key, String(value)); else params.delete(key);
        });
        router.get(routeUrl, Object.fromEntries(params.entries()), { preserveState: false, preserveScroll: false, replace: true });
    };

    const reloadAfterSave = () => {
        setError('');
        router.reload({ preserveScroll: true, only: ['rows', 'pagination', 'filterOptions'] });
    };

    const destroy = async (row) => {
        if (!row?._record_id || !window.confirm(`Xóa combo "${row.name}"?`)) return;
        setError('');
        try {
            await requestJson(`${routeUrl}/records/${row._record_id}`, 'DELETE');
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (exception) {
            setError(exception.message);
        }
    };

    const exportUrl = () => {
        const params = new URLSearchParams(window.location.search);
        params.set('export', '1');
        return `${routeUrl}?${params.toString()}`;
    };

    return (
        <AppLayout>
            <Head title={schema.title ?? 'Danh sách combo'} />
            <div className="pushsale-page ps-combo-page" data-page-code="1.3.2">
                {error && <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {error}</div>}
                <div className="ps-page-heading ps-combo-heading">
                    <div className="ps-page-title">Danh sách combo</div>
                    <div className="ps-combo-heading-actions">
                        <input className="form-control input-sm" value={keyword} onChange={(event) => setKeyword(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') visit(1); }} placeholder="Nhập từ khóa tìm kiếm" />
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => visit(1)}><i className="fa fa-search" /> Tìm kiếm</button>
                        <div className={`ps-combo-tools ${showTools ? 'open' : ''}`}>
                            <button type="button" className="btn btn-default btn-sm" onClick={() => setShowTools((value) => !value)} title="Chức năng">
                                <i className="fa fa-gear" />
                            </button>
                            {showTools && (
                                <ul className="dropdown-menu dropdown-menu-right">
                                    <li><a href={exportUrl()}><i className="fa fa-file-excel-o" /> Xuất Excel</a></li>
                                    <li><button type="button" onClick={() => router.reload({ preserveScroll: true })}><i className="fa fa-refresh" /> Reload</button></li>
                                </ul>
                            )}
                        </div>
                    </div>
                </div>

                <div className="ps-combo-filter-bar">
                    <select className="form-control" value={productId} onChange={(event) => setProductId(event.target.value)}>
                        <option value="">--Chọn sản phẩm--</option>
                        {productOptions.map((product) => <option key={product.id} value={product.id}>{product.label ?? product.name}</option>)}
                    </select>
                    <select className="form-control" value={activeStatus} onChange={(event) => setActiveStatus(event.target.value)}>
                        <option value="">--Trạng thái áp dụng--</option>
                        <option value="1">Đang áp dụng</option>
                        <option value="0">Không áp dụng</option>
                    </select>
                    <input className="form-control" value={dateRange} onChange={(event) => setDateRange(event.target.value)} placeholder="dd/mm/yyyy 00:00 - dd/mm/yyyy 23:59" />
                    <select className="form-control" value={sort} onChange={(event) => setSort(event.target.value)}>
                        <option value="created_desc">Sắp xếp theo ngày tạo</option>
                        <option value="sku">Sắp xếp theo mã combo</option>
                        <option value="name">Sắp xếp theo tên combo</option>
                    </select>
                    <button type="button" className="btn btn-default btn-sm" onClick={() => { setKeyword(''); setProductId(''); setActiveStatus(''); setDateRange(''); setSort('created_desc'); visit(1, { keyword: '', productId: '', activeStatus: '', dateRange: '', sort: 'created_desc' }); }}>
                        <i className="fa fa-eraser" /> Xóa lọc
                    </button>
                </div>

                <div className="ps-combo-create-row">
                    <button type="button" className="btn btn-primary btn-sm" onClick={() => setDialog({ open: true, mode: 'create', combo: null })}>
                        <i className="fa fa-plus" /> Thêm combo
                    </button>
                </div>

                <div className="ps-combo-table-wrap">
                    <table className="table table-bordered table-striped table-condensed ps-combo-table">
                        <thead>
                            <tr>
                                <th style={{ width: 44 }}>#</th>
                                <th style={{ width: 130 }}>Mã combo</th>
                                <th>Tên combo</th>
                                <th>Sản phẩm trong combo</th>
                                <th style={{ width: 82 }}>Tổng SP</th>
                                <th style={{ width: 120 }}>Giá gốc</th>
                                <th style={{ width: 120 }}>Giá combo</th>
                                <th style={{ width: 105 }}>Ưu đãi</th>
                                <th style={{ width: 105 }}>Trạng thái</th>
                                <th style={{ width: 125 }}>Cập nhật</th>
                                <th style={{ width: 84 }}>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row._record_id ?? row.id}>
                                    <td className="text-center">{pagination.from ? Number(pagination.from) + index : index + 1}</td>
                                    <td><strong>{row.code}</strong></td>
                                    <td>{row.name}</td>
                                    <td className="ps-combo-components-cell">{row.components || '—'}</td>
                                    <td className="text-right">{numberFormatter.format(Number(row.product_count || 0))}</td>
                                    <td className="text-right">{formatCurrency(row.original_total)}</td>
                                    <td className="text-right"><strong>{formatCurrency(row.combo_total)}</strong></td>
                                    <td className="text-right">{formatCurrency((Number(row.original_total || 0) - Number(row.combo_total || 0)))}</td>
                                    <td className="text-center"><span className={`pushsale-status ${row._is_active ? 'pushsale-status-success' : 'pushsale-status-default'}`}>{row.status}</span></td>
                                    <td className="text-center">{formatDateTime(row.updated_at)}</td>
                                    <td className="text-center">
                                        <button type="button" className="pushsale-icon-action" title="Cập nhật combo" onClick={() => setDialog({ open: true, mode: 'update', combo: row })}><i className="fa fa-pencil" /></button>
                                        <button type="button" className="pushsale-icon-action is-danger" title="Xóa combo" onClick={() => destroy(row)}><i className="fa fa-trash" /></button>
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="11" className="text-center text-muted">Chưa có combo phù hợp bộ lọc.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="pushsale-pagination-wrap ps-combo-pagination">
                    <div className="pushsale-record-info">Hiển thị <b>{pagination.from ?? 0}</b> - <b>{pagination.to ?? 0}</b> / <b>{numberFormatter.format(total)}</b> combo</div>
                    <ul className="pagination pagination-sm no-margin">
                        <li className={currentPage <= 1 ? 'disabled' : ''}><button type="button" onClick={() => currentPage > 1 && visit(1)}>«</button></li>
                        <li className={currentPage <= 1 ? 'disabled' : ''}><button type="button" onClick={() => currentPage > 1 && visit(currentPage - 1)}>‹</button></li>
                        <li className="active"><button type="button">{currentPage}</button></li>
                        <li className={currentPage >= lastPage ? 'disabled' : ''}><button type="button" onClick={() => currentPage < lastPage && visit(currentPage + 1)}>›</button></li>
                        <li className={currentPage >= lastPage ? 'disabled' : ''}><button type="button" onClick={() => currentPage < lastPage && visit(lastPage)}>»</button></li>
                    </ul>
                    <label className="pushsale-page-size"><span>Hiển thị</span><select className="form-control input-sm" value={perPage} onChange={(event) => {
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', '1');
                        params.set('per_page', event.target.value);
                        router.get(routeUrl, Object.fromEntries(params.entries()), { preserveState: false, preserveScroll: false, replace: true });
                    }}><option value="10">10</option><option value="20">20</option><option value="50">50</option><option value="100">100</option></select><span>dòng</span></label>
                </div>
            </div>

            <ComboDialog
                key={`${dialog.mode}-${dialog.combo?._record_id ?? 'new'}`}
                open={dialog.open}
                mode={dialog.mode}
                combo={dialog.combo}
                products={productOptions}
                routeUrl={routeUrl}
                onClose={() => setDialog({ open: false, mode: 'create', combo: null })}
                onSaved={reloadAfterSave}
            />
        </AppLayout>
    );
}
