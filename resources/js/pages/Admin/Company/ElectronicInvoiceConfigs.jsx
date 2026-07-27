import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';
import { useConfirm } from '@/hooks/use-confirm';

const emptyForm = {
    account: '',
    password: '',
    invoice_type_code: '',
    tax_code: '',
    invoice_template_code: '',
    invoice_series: '',
    business_name: '',
    address: '',
    phone: '',
    fax: '',
    email: '',
    bank_name: '',
    bank_account: '',
    is_active: true,
};

const REQUIRED_FIELDS = [
    ['account', 'Tài khoản'],
    ['password', 'Mật khẩu'],
    ['invoice_type_code', 'Mã loại hóa đơn'],
    ['tax_code', 'Mã số thuế'],
    ['invoice_template_code', 'Mã mẫu hóa đơn'],
    ['invoice_series', 'Ký hiệu hóa đơn'],
    ['business_name', 'Tên doanh nghiệp'],
    ['phone', 'Điện thoại'],
    ['email', 'Email'],
    ['bank_name', 'Tên ngân hàng'],
    ['address', 'Địa chỉ'],
];

const currentFilters = () => Object.fromEntries(new URLSearchParams(window.location.search).entries());

const fmt = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
};

function flattenErrors(errors = {}) {
    return Object.entries(errors).flatMap(([key, value]) => {
        const messages = Array.isArray(value) ? value : [value];
        return messages.filter(Boolean).map((message) => {
            const field = key.replace(/^payload\./, '');
            return field && field !== key ? `${field}: ${message}` : message;
        });
    });
}

function Field({ label, required = false, error, children }) {
    return (
        <label className="ps-invoice-config-field">
            <span>
                {label}
                {required ? <> <b>(*)</b></> : null}
            </span>
            {children}
            {error ? <small className="ps-field-error">{error}</small> : null}
        </label>
    );
}

export default function ElectronicInvoiceConfigs({
    schema,
    rows = [],
    pagination,
    routeUrl = '/admin/unit/electronic-invoice-configs',
}) {
    const { ask } = useConfirm();
    const params = new URLSearchParams(window.location.search);
    const [keyword, setKeyword] = useState(params.get('search') ?? '');
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const fieldError = (key) => form.errors[key] ?? form.errors[`payload.${key}`] ?? '';

    const search = (event) => {
        event.preventDefault();
        router.get(routeUrl, keyword.trim() ? { search: keyword.trim() } : {}, { replace: true, preserveState: true });
    };

    const create = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const edit = (row) => {
        setEditingId(row._record_id);
        form.setData({
            ...emptyForm,
            ...(row._form ?? {}),
            password: '',
            is_active: Boolean(row._form?.is_active ?? row.is_active ?? true),
        });
        form.clearErrors();
        setOpen(true);
    };

    const validateClient = () => {
        const nextErrors = {};
        REQUIRED_FIELDS.forEach(([key, label]) => {
            if (key === 'password' && editingId) return;
            const value = String(form.data[key] ?? '').trim();
            if (!value) nextErrors[key] = `${label} bắt buộc.`;
        });

        const email = String(form.data.email ?? '').trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            nextErrors.email = 'Email không hợp lệ.';
        }

        const phone = String(form.data.phone ?? '').trim();
        if (phone) {
            const phoneError = vietnamesePhoneError(phone, { required: true });
            if (phoneError) nextErrors.phone = phoneError;
        } else {
            nextErrors.phone = 'Điện thoại bắt buộc.';
        }

        const tax = String(form.data.tax_code ?? '').trim();
        if (tax && !/^[0-9A-Za-z\-]{8,20}$/.test(tax)) {
            nextErrors.tax_code = 'Mã số thuế không hợp lệ.';
        }

        return nextErrors;
    };

    const save = (event) => {
        event.preventDefault();
        form.clearErrors();

        const clientErrors = validateClient();
        if (Object.keys(clientErrors).length) {
            Object.entries(clientErrors).forEach(([key, message]) => form.setError(key, message));
            toast.error('Vui lòng kiểm tra lại các trường bắt buộc.');
            return;
        }

        const payload = {
            ...form.data,
            account: String(form.data.account).trim(),
            tax_code: String(form.data.tax_code).trim(),
            email: String(form.data.email ?? '').trim() || null,
            phone: normalizeVietnamesePhone(form.data.phone) || String(form.data.phone ?? '').trim() || null,
            fax: String(form.data.fax ?? '').trim() || null,
            bank_account: String(form.data.bank_account ?? '').trim() || null,
            password: String(form.data.password ?? '').trim() || null,
            is_active: Boolean(form.data.is_active),
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success(editingId ? 'Đã cập nhật cấu hình hóa đơn.' : 'Đã thêm cấu hình hóa đơn.');
            },
            onError: (errors) => {
                const mapped = {};
                Object.entries(errors ?? {}).forEach(([key, value]) => {
                    mapped[key.replace(/^payload\./, '')] = Array.isArray(value) ? value[0] : value;
                });
                Object.entries(mapped).forEach(([key, message]) => form.setError(key, message));
                toast.error(flattenErrors(errors).join(' · ') || 'Không lưu được cấu hình hóa đơn.');
            },
        };

        if (editingId) {
            router.put(`${routeUrl}/records/${editingId}`, { payload }, options);
        } else {
            router.post(`${routeUrl}/records`, { payload }, options);
        }
    };

    const destroy = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({
            description: `Xóa cấu hình hóa đơn ${row.account}?`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa cấu hình hóa đơn.'),
            onError: () => toast.error('Không xóa được cấu hình hóa đơn.'),
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Quản lý cấu hình hóa đơn'} />
            <PageHeader
                title="Quản lý cấu hình hóa đơn"
                pageCode="1.14.1"
                className="ps-invoice-config-header"
                actions={(
                    <div className="ps-invoice-config-header-actions">
                        <form className="ps-invoice-config-search" onSubmit={search}>
                            <input
                                className="form-control text-center"
                                placeholder="Tên nhóm / tài khoản / MST"
                                value={keyword}
                                onChange={(event) => setKeyword(event.target.value)}
                            />
                            <button className="btn btn-sm btn-primary" type="submit">
                                <i className="fa fa-search" /> Tìm kiếm
                            </button>
                        </form>
                        <button type="button" className="btn btn-sm btn-success" onClick={create}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                    </div>
                )}
            />
            <section className="ps-adminlte-page ps-invoice-config-page" data-page-code="1.14.1">
                <div className="box-body ps-invoice-config-body">
                    <div className="ps-table-scroll ps-invoice-config-table-wrap">
                        <table className="table table-bordered ps-invoice-config-table">
                            <thead>
                                <tr>
                                    <th className="text-center" style={{ width: 60 }}>STT</th>
                                    <th className="text-center no-wrap">Tài khoản</th>
                                    <th className="text-center no-wrap">Mã số thuế</th>
                                    <th className="text-center no-wrap">Ký hiệu mẫu hóa đơn</th>
                                    <th className="text-center no-wrap">Dãy ký hiệu hóa đơn</th>
                                    <th className="text-center no-wrap">Tên đăng ký kinh doanh</th>
                                    <th className="text-center no-wrap">Số điện thoại</th>
                                    <th className="text-center no-wrap">Email</th>
                                    <th className="text-center">Sử dụng</th>
                                    <th className="text-center no-wrap">Cập nhật</th>
                                    <th className="text-center no-wrap ps-action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length ? rows.map((row, index) => (
                                    <tr key={row._record_id ?? row.id}>
                                        <td className="text-center">{index + (pagination?.from ?? 1)}</td>
                                        <td>{row.account}</td>
                                        <td>{row.tax_code}</td>
                                        <td>{row.invoice_template_code}</td>
                                        <td>{row.invoice_series}</td>
                                        <td>{row.business_name}</td>
                                        <td>{row.phone}</td>
                                        <td>{row.email}</td>
                                        <td className="text-center">{row.is_active ? <i className="fa fa-check text-green" /> : ''}</td>
                                        <td className="text-center">{fmt(row.updated_at)}</td>
                                        <td className="text-center ps-row-actions ps-row-actions-cell">
                                            <button type="button" title="Cập nhật" onClick={() => edit(row)}>
                                                <i className="fa fa-pencil-square-o" />
                                            </button>
                                            <button type="button" title="Xóa" onClick={() => destroy(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="11" className="text-center">Không có dữ liệu.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="text-right">
                        <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="cấu hình" />
                    </div>
                </div>
            </section>

            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && setOpen(false)}
                title={editingId ? 'Cập nhật thông tin cấu hình hóa đơn' : 'Thêm mới cấu hình hóa đơn'}
                width="560px"
                className="ps-invoice-config-dialog"
                bodyClassName="ps-invoice-config-dialog-body"
            >
                <form className="ps-invoice-config-form" onSubmit={save} noValidate>
                    <div className="ps-invoice-config-stack">
                        <Field label="Tài khoản" required error={fieldError('account')}>
                            <input className="form-control" value={form.data.account} onChange={(e) => form.setData('account', e.target.value)} autoComplete="off" />
                        </Field>
                        <Field label="Mật khẩu" required={!editingId} error={fieldError('password')}>
                            <input className="form-control" type="password" value={form.data.password ?? ''} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" placeholder={editingId ? 'Để trống nếu giữ mật khẩu cũ' : ''} />
                        </Field>
                        <Field label="Mã loại hóa đơn" required error={fieldError('invoice_type_code')}>
                            <input className="form-control" value={form.data.invoice_type_code ?? ''} onChange={(e) => form.setData('invoice_type_code', e.target.value)} />
                        </Field>
                        <Field label="Mã số thuế" required error={fieldError('tax_code')}>
                            <input className="form-control" value={form.data.tax_code ?? ''} onChange={(e) => form.setData('tax_code', e.target.value)} />
                        </Field>
                        <Field label="Mã mẫu hóa đơn" required error={fieldError('invoice_template_code')}>
                            <input className="form-control" value={form.data.invoice_template_code ?? ''} onChange={(e) => form.setData('invoice_template_code', e.target.value)} />
                        </Field>
                        <Field label="Ký hiệu hóa đơn" required error={fieldError('invoice_series')}>
                            <input className="form-control" value={form.data.invoice_series ?? ''} onChange={(e) => form.setData('invoice_series', e.target.value)} />
                        </Field>
                        <Field label="Tên doanh nghiệp" required error={fieldError('business_name')}>
                            <input className="form-control" value={form.data.business_name ?? ''} onChange={(e) => form.setData('business_name', e.target.value)} />
                        </Field>
                        <Field label="Điện thoại" required error={fieldError('phone')}>
                            <input className="form-control" value={form.data.phone ?? ''} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Số fax" error={fieldError('fax')}>
                            <input className="form-control" value={form.data.fax ?? ''} onChange={(e) => form.setData('fax', e.target.value)} />
                        </Field>
                        <Field label="Email" required error={fieldError('email')}>
                            <input className="form-control" type="email" value={form.data.email ?? ''} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        <Field label="Tên ngân hàng" required error={fieldError('bank_name')}>
                            <input className="form-control" value={form.data.bank_name ?? ''} onChange={(e) => form.setData('bank_name', e.target.value)} />
                        </Field>
                        <Field label="Tài khoản ngân hàng" error={fieldError('bank_account')}>
                            <input className="form-control" value={form.data.bank_account ?? ''} onChange={(e) => form.setData('bank_account', e.target.value)} />
                        </Field>
                        <Field label="Địa chỉ" required error={fieldError('address')}>
                            <input className="form-control" value={form.data.address ?? ''} onChange={(e) => form.setData('address', e.target.value)} />
                        </Field>
                        <label className="ps-invoice-config-field ps-invoice-config-check">
                            <span>Sử dụng</span>
                            <span className="ps-checkbox-inline">
                                <input type="checkbox" checked={Boolean(form.data.is_active)} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                Đang sử dụng
                            </span>
                        </label>
                    </div>
                    {flattenErrors(form.errors).length ? (
                        <div className="alert alert-danger">{flattenErrors(form.errors).join(' · ')}</div>
                    ) : null}
                    <div className="ps-dialog-footer ps-invoice-config-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={() => setOpen(false)}>Đóng</button>
                        <button className="btn btn-primary btn-sm" disabled={form.processing}>
                            <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : (editingId ? 'fa-save' : 'fa-plus')}`} />
                            {' '}
                            {editingId ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
