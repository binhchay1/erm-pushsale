import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { useConfirm } from '@/hooks/use-confirm';

const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

function formatCurrency(value) {
    return currencyFormatter.format(Number(value) || 0);
}

function emptyForm(ruleType) {
    return {
        id: null,
        rule_type: ruleType,
        order_from: '0',
        discount_value: '0',
        calculation_type: 'fixed',
        is_active: true,
    };
}

function normalizePayload(form) {
    return {
        rule_type: form.rule_type,
        order_from: Math.max(0, Number(form.order_from || 0)),
        discount_value: Math.max(0, Number(form.discount_value || 0)),
        calculation_type: form.rule_type === 'cod' ? 'fixed' : (form.calculation_type || 'fixed'),
        cod_from: form.rule_type === 'cod' ? Math.max(0, Number(form.order_from || 0)) : null,
        cod_to: form.rule_type === 'cod' ? Math.max(0, Number(form.discount_value || 0)) : null,
        is_active: Boolean(form.is_active),
    };
}

function RuleTable({ title, type, rows, routeUrl, onSaved }) {
    const { ask } = useConfirm();
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
            order_from: String(Number(data.order_from ?? row.order_from ?? 0)),
            discount_value: String(Number(data.discount_value ?? row.discount_value ?? 0)),
            calculation_type: data.calculation_type ?? row.calculation_type_value ?? 'fixed',
            is_active: data.is_active ?? row.is_active ?? true,
        });
    };

    const reset = () => {
        setError('');
        setForm(emptyForm(type));
    };

    const save = () => {
        const payload = normalizePayload(form);
        if (Number.isNaN(payload.order_from) || payload.order_from < 0) {
            setError('Giá trị đơn hàng không hợp lệ.');
            toast.error('Giá trị đơn hàng không hợp lệ.');
            return;
        }
        if (Number.isNaN(payload.discount_value) || payload.discount_value < 0) {
            setError('Giá trị cấu hình không hợp lệ.');
            toast.error('Giá trị cấu hình không hợp lệ.');
            return;
        }

        setSaving(true);
        setError('');

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onSaved?.();
            },
            onError: (errors) => {
                const first = Object.values(errors || {})[0];
                const message = first || 'Không lưu được cấu hình.';
                setError(message);
                toast.error(message);
            },
            onFinish: () => setSaving(false),
        };

        if (form.id) {
            router.put(`${routeUrl}/records/${form.id}`, { payload }, options);
            return;
        }

        router.post(`${routeUrl}/records`, { payload }, options);
    };

    const destroy = async (row) => {
        const id = row._record_id ?? row.id;
        if (!id) return;
        const ok = await ask({
            title: 'Xóa cấu hình',
            description: 'Bạn chắc chắn muốn xóa cấu hình này? Hành động này không thể hoàn tác.',
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;

        setSaving(true);
        setError('');
        router.delete(`${routeUrl}/records/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (String(form.id) === String(id)) reset();
                onSaved?.();
            },
            onError: (errors) => {
                const first = Object.values(errors || {})[0];
                const message = first || 'Không xóa được cấu hình.';
                setError(message);
                toast.error(message);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <section className="ps-discount-panel">
            <div className="pu-caption">{title}</div>
            {error ? (
                <div className="alert alert-danger ps-discount-error">
                    <i className="fa fa-exclamation-triangle" /> {error}
                </div>
            ) : null}
            <div className="ps-discount-table-wrap">
                <table className="table table-bordered table-condensed ps-discount-table">
                    <thead>
                        <tr>
                            <th style={{ width: 60 }}>STT</th>
                            <th>Giá trị đơn hàng từ (trở lên)</th>
                            <th>{isDiscount ? 'Giá trị chiết khấu' : 'COD thu của khách'}</th>
                            {isDiscount ? <th style={{ width: 120 }}>Tính theo</th> : null}
                            <th style={{ width: 112 }}>Cập nhật</th>
                            <th style={{ width: 130 }}>
                                <button type="button" className="btn btn-xs btn-primary ps-discount-add-btn" onClick={reset}>
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
                                <td className="text-right">
                                    {isDiscount && row.calculation_type_value === 'percent'
                                        ? `${Number(row.discount_value || 0)}%`
                                        : formatCurrency(row.discount_value)}
                                </td>
                                {isDiscount ? <td className="text-center">{row.calculation_type}</td> : null}
                                <td className="text-center">
                                    <button type="button" className="btn btn-xs btn-primary" onClick={() => edit(row)} title="Cập nhật">
                                        <i className="fa fa-pencil" />
                                    </button>
                                    {' '}
                                    <button type="button" className="btn btn-xs btn-danger" onClick={() => destroy(row)} title="Xóa">
                                        <i className="fa fa-trash" />
                                    </button>
                                </td>
                                <td className="text-center">
                                    <span className={row.is_active ? 'pushsale-status pushsale-status-success' : 'pushsale-status pushsale-status-default'}>
                                        {row.is_active ? 'Áp dụng' : 'Tắt'}
                                    </span>
                                </td>
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={isDiscount ? 6 : 5} className="text-center text-muted">Chưa có cấu hình.</td>
                            </tr>
                        )}
                        <tr className="ps-discount-edit-row">
                            <td className="text-center">{form.id ? 'Sửa' : 'Mới'}</td>
                            <td>
                                <input
                                    className="form-control text-right"
                                    type="number"
                                    min="0"
                                    value={form.order_from}
                                    onChange={(event) => setForm((current) => ({ ...current, order_from: event.target.value }))}
                                    placeholder="0"
                                />
                            </td>
                            <td>
                                <input
                                    className="form-control text-right"
                                    type="number"
                                    min="0"
                                    value={form.discount_value}
                                    onChange={(event) => setForm((current) => ({ ...current, discount_value: event.target.value }))}
                                    placeholder="0"
                                />
                            </td>
                            {isDiscount ? (
                                <td>
                                    <select
                                        className="form-control"
                                        value={form.calculation_type}
                                        onChange={(event) => setForm((current) => ({ ...current, calculation_type: event.target.value }))}
                                    >
                                        <option value="fixed">Số tiền</option>
                                        <option value="percent">Phần trăm</option>
                                    </select>
                                </td>
                            ) : null}
                            <td className="text-center">
                                <label className="ps-checkbox-inline">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(form.is_active)}
                                        onChange={(event) => setForm((current) => ({ ...current, is_active: event.target.checked }))}
                                    />
                                    <span>Áp dụng</span>
                                </label>
                            </td>
                            <td className="text-center">
                                <button type="button" className="btn btn-sm btn-primary" disabled={saving} onClick={save}>
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

    useEffect(() => {
        if (pageRuntimeError) {
            setError(pageRuntimeError);
            toast.error(pageRuntimeError);
        }
    }, [pageRuntimeError]);

    const reload = () => {
        setError('');
        router.reload({ preserveScroll: true, only: ['rows', 'pagination', 'summary'] });
    };

    return (
        <AppLayout activeMenuCode="1.9">
            <Head title={schema?.title ?? 'Thiết lập chiết khấu, COD'} />
            <PageHeader title={schema?.title ?? 'Thiết lập chiết khấu, COD'} pageCode="1.9" collapsible={false} />

            <div className="pushsale-page ps-discount-cod-page" data-page-code="1.9">
                {error ? (
                    <div className="alert alert-danger ps-discount-page-error">
                        <i className="fa fa-exclamation-triangle" /> {error}
                    </div>
                ) : null}

                <div className="ps-discount-cod-grid">
                    <RuleTable title="Danh sách chiết khấu" type="discount" rows={discountRows} routeUrl={routeUrl} onSaved={reload} />
                    <RuleTable title="Danh sách phí COD thu của khách" type="cod" rows={codRows} routeUrl={routeUrl} onSaved={reload} />
                </div>

                <div className="ps-discount-note">
                    <strong>Chỉ dẫn:</strong><br />
                    - Hệ thống căn cứ thiết lập để tự gợi/áp dụng chiết khấu hoặc COD thu của khách khi đơn chưa có giá trị nhập từ landing/sale.<br />
                    - Combo vẫn là dòng sản phẩm catalog riêng; chiết khấu/COD tính trên tổng giá trị đơn sau khi đã cộng giá combo và các sản phẩm trong đơn.<br />
                    - Nếu không thiết lập phí COD, hệ thống giữ nguyên phí dịch vụ COD/ship theo đơn vị giao hàng hoặc theo dữ liệu nhập tay.
                </div>
            </div>
        </AppLayout>
    );
}
