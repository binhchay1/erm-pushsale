import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

function formatCurrency(value) {
    return currencyFormatter.format(Number(value) || 0);
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
        body: payload ? JSON.stringify({ payload }) : undefined,
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const errors = Object.values(body.errors ?? {}).flat().join(' ');
        throw new Error(errors || body.message || 'Không lưu được cấu hình.');
    }

    return response.json().catch(() => ({}));
}

function emptyForm(ruleType) {
    return {
        id: null,
        rule_type: ruleType,
        order_from: '',
        discount_value: '',
        calculation_type: 'fixed',
        is_active: true,
    };
}

function normalizePayload(form) {
    return {
        rule_type: form.rule_type,
        order_from: Number(form.order_from || 0),
        discount_value: Number(form.discount_value || 0),
        calculation_type: form.rule_type === 'cod' ? 'fixed' : (form.calculation_type || 'fixed'),
        cod_from: form.rule_type === 'cod' ? Number(form.order_from || 0) : null,
        cod_to: form.rule_type === 'cod' ? Number(form.discount_value || 0) : null,
        is_active: Boolean(form.is_active),
    };
}

function RuleTable({ title, type, rows, routeUrl, onSaved }) {
    const [form, setForm] = useState(emptyForm(type));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const isDiscount = type === 'discount';
    const edit = (row) => {
        const data = row._form ?? row;
        setError('');
        setForm({
            id: row._record_id ?? row.id,
            rule_type: type,
            order_from: Number(data.order_from ?? row.order_from ?? 0),
            discount_value: Number(data.discount_value ?? row.discount_value ?? 0),
            calculation_type: data.calculation_type ?? row.calculation_type_value ?? 'fixed',
            is_active: data.is_active ?? row.is_active ?? true,
        });
    };

    const reset = () => {
        setError('');
        setForm(emptyForm(type));
    };

    const save = async () => {
        const payload = normalizePayload(form);
        if (payload.order_from < 0) { setError('Giá trị đơn hàng không hợp lệ.'); return; }
        if (payload.discount_value < 0) { setError('Giá trị cấu hình không hợp lệ.'); return; }

        setSaving(true);
        setError('');
        try {
            const url = form.id ? `${routeUrl}/records/${form.id}` : `${routeUrl}/records`;
            await requestJson(url, form.id ? 'PUT' : 'POST', payload);
            reset();
            onSaved?.();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setSaving(false);
        }
    };

    const destroy = async (row) => {
        const id = row._record_id ?? row.id;
        if (!id || !window.confirm('Xóa cấu hình này?')) return;
        setSaving(true);
        setError('');
        try {
            await requestJson(`${routeUrl}/records/${id}`, 'DELETE');
            if (String(form.id) === String(id)) reset();
            onSaved?.();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className="ps-discount-panel">
            <div className="pu-caption">{title}</div>
            {error && <div className="alert alert-danger ps-discount-error"><i className="fa fa-exclamation-triangle" /> {error}</div>}
            <div className="ps-discount-table-wrap">
                <table className="table table-bordered table-condensed ps-discount-table">
                    <thead>
                        <tr>
                            <th style={{ width: 60 }}>STT</th>
                            <th>{isDiscount ? 'Giá trị đơn hàng từ (trở lên)' : 'Giá trị đơn hàng từ (trở lên)'}</th>
                            <th>{isDiscount ? 'Giá trị chiết khấu' : 'COD thu của khách'}</th>
                            {isDiscount && <th style={{ width: 120 }}>Tính theo</th>}
                            <th style={{ width: 112 }}>Cập nhật</th>
                            <th style={{ width: 130 }}>
                                <button type="button" className="ps-btn-link" onClick={reset}>
                                    <i className="fa fa-plus" /> Thêm
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => (
                            <tr key={row._record_id ?? row.id}>
                                <td className="text-center">{index + 1}</td>
                                <td className="text-right">{formatCurrency(row.order_from)}</td>
                                <td className="text-right">{isDiscount && row.calculation_type_value === 'percent' ? `${Number(row.discount_value || 0)}%` : formatCurrency(row.discount_value)}</td>
                                {isDiscount && <td className="text-center">{row.calculation_type}</td>}
                                <td className="text-center">
                                    <button type="button" className="pushsale-icon-action" onClick={() => edit(row)} title="Cập nhật"><i className="fa fa-pencil" /></button>
                                    <button type="button" className="pushsale-icon-action is-danger" onClick={() => destroy(row)} title="Xóa"><i className="fa fa-trash" /></button>
                                </td>
                                <td className="text-center"><span className={row.is_active ? 'pushsale-status pushsale-status-success' : 'pushsale-status pushsale-status-default'}>{row.is_active ? 'Áp dụng' : 'Tắt'}</span></td>
                            </tr>
                        )) : (
                            <tr><td colSpan={isDiscount ? 6 : 5} className="text-center text-muted">Chưa có cấu hình.</td></tr>
                        )}
                        <tr className="ps-discount-edit-row">
                            <td className="text-center">{form.id ? 'Sửa' : 'Mới'}</td>
                            <td><input className="form-control text-right" type="number" min="0" value={form.order_from} onChange={(event) => setForm((current) => ({ ...current, order_from: event.target.value }))} placeholder="0" /></td>
                            <td><input className="form-control text-right" type="number" min="0" value={form.discount_value} onChange={(event) => setForm((current) => ({ ...current, discount_value: event.target.value }))} placeholder="0" /></td>
                            {isDiscount && (
                                <td>
                                    <select className="form-control" value={form.calculation_type} onChange={(event) => setForm((current) => ({ ...current, calculation_type: event.target.value }))}>
                                        <option value="fixed">Số tiền</option>
                                        <option value="percent">Phần trăm</option>
                                    </select>
                                </td>
                            )}
                            <td className="text-center">
                                <label className="ps-checkbox-inline">
                                    <input type="checkbox" checked={Boolean(form.is_active)} onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))} />
                                    <span>Áp dụng</span>
                                </label>
                            </td>
                            <td className="text-center">
                                <button type="button" className="ps-btn-link" disabled={saving} onClick={save}>
                                    <i className={`fa ${saving ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Page({ schema, rows = [], routeUrl, pageRuntimeError = null }) {
    const [error, setError] = useState(pageRuntimeError || '');
    const discountRows = useMemo(() => rows.filter((row) => (row.rule_type ?? 'discount') === 'discount'), [rows]);
    const codRows = useMemo(() => rows.filter((row) => row.rule_type === 'cod'), [rows]);

    const reload = () => {
        setError('');
        router.reload({ preserveScroll: true, only: ['rows', 'pagination', 'summary'] });
    };

    return (
        <AppLayout activeMenuCode="1.9">
            <Head title={schema?.title ?? 'Thiết lập chiết khấu, COD'} />
            <div className="pushsale-page ps-discount-cod-page" data-page-code="1.9">
                <div className="ps-page-heading ps-discount-cod-heading">
                    <div className="ps-page-title">Thiết lập chiết khấu, COD</div>
                </div>

                {error && <div className="alert alert-danger ps-discount-page-error"><i className="fa fa-exclamation-triangle" /> {error}</div>}

                <div className="ps-discount-cod-grid">
                    <RuleTable title="Danh sách chiết khấu" type="discount" rows={discountRows} routeUrl={routeUrl} onSaved={reload} />
                    <RuleTable title="Danh sách phí COD thu của khách" type="cod" rows={codRows} routeUrl={routeUrl} onSaved={reload} />
                </div>

                <div className="ps-discount-note">
                    <strong>Chỉ dẫn:</strong><br />
                    - Hệ thống sẽ căn cứ vào thiết lập để tự gợi/áp dụng chiết khấu hoặc COD thu của khách khi đơn chưa có giá trị nhập từ landing/sale.<br />
                    - Combo vẫn là dòng sản phẩm catalog riêng; chiết khấu/COD tính trên tổng giá trị đơn sau khi đã cộng giá combo và các sản phẩm trong đơn.<br />
                    - Nếu không thiết lập phí COD, hệ thống giữ nguyên phí dịch vụ COD/ship theo đơn vị giao hàng hoặc theo dữ liệu nhập tay.
                </div>
            </div>
        </AppLayout>
    );
}
