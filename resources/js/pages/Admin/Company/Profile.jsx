import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { vietnamesePhoneError } from '@/lib/vietnamesePhone';

const provinceOptions = ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ'];
const districtOptions = ['Quận Ba Đình', 'Quận Hoàn Kiếm', 'Quận Đống Đa', 'Quận Hà Đông', 'Quận Cầu Giấy'];
const wardOptions = ['Phường Phúc Xá', 'Phường Trúc Bạch', 'Phường Cống Vị', 'Phường Điện Biên', 'Phường Đội Cấn'];
const productFieldOptions = ['Bán lẻ', 'Thời trang', 'Mỹ phẩm', 'Gia dụng', 'Thực phẩm', 'Dịch vụ'];

const EMAIL_HOST_PATTERN = /^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i;

function valueOrDefault(value, fallback = '') {
    const normalized = String(value ?? '').trim();
    return normalized || fallback;
}

function normalizeEmailHost(value) {
    return String(value ?? '').trim().replace(/^@+/, '').toLowerCase();
}

function validateEmailHost(value) {
    const normalized = normalizeEmailHost(value);
    if (!normalized) {
        return 'Hậu tố email đăng nhập không được để trống.';
    }
    if (!EMAIL_HOST_PATTERN.test(normalized)) {
        return 'Hậu tố không hợp lệ. Ví dụ: saleops.local hoặc ten-cong-ty.saleops.local';
    }
    return null;
}

function validateOptionalPhone(value) {
    return vietnamesePhoneError(value, { required: false }) || null;
}

function FieldRow({ label, required = false, error, hint, children }) {
    return (
        <div className="ps-unit-form-row">
            <label className="ps-unit-label">
                {label} {required ? <span>(*)</span> : null}
            </label>
            <div className="ps-unit-control-wrap">
                {children}
                {hint ? <p className="ps-unit-field-hint">{hint}</p> : null}
                {error ? <div className="ps-unit-error">{error}</div> : null}
            </div>
        </div>
    );
}

function Select({ value, onChange, children, disabled = false }) {
    return (
        <select className="form-control ps-unit-control" value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
            {children}
        </select>
    );
}

export default function CompanyProfile({ company, emailIdentity = {} }) {
    const inferredProvince = company?.province_name ?? (String(company?.address ?? '').toLocaleLowerCase('vi').includes('hn') ? 'Hà Nội' : '');
    const isInternal = Boolean(company?.is_internal ?? emailIdentity?.isInternal);
    const [emailHostError, setEmailHostError] = useState(null);
    const [phoneError, setPhoneError] = useState(null);

    const form = useForm({
        name: valueOrDefault(company?.name),
        contact_phone: valueOrDefault(company?.contact_phone),
        product_field: valueOrDefault(company?.product_field),
        address: valueOrDefault(company?.address),
        address_2: valueOrDefault(company?.address_2),
        use_two_level_address: Boolean(company?.use_two_level_address),
        province_name: valueOrDefault(inferredProvince),
        district_name: valueOrDefault(company?.district_name),
        ward_name: valueOrDefault(company?.ward_name),
        contact_email: valueOrDefault(company?.contact_email),
        email_login_host: valueOrDefault(company?.email_login_host ?? emailIdentity?.host),
        tax_code: valueOrDefault(company?.tax_code),
        website: valueOrDefault(company?.website),
        representative_name: valueOrDefault(company?.representative_name),
        representative_title: valueOrDefault(company?.representative_title),
    });

    const submit = (event) => {
        event.preventDefault();

        const hostError = validateEmailHost(form.data.email_login_host);
        const nextPhoneError = validateOptionalPhone(form.data.contact_phone);
        setEmailHostError(hostError);
        setPhoneError(nextPhoneError);
        if (hostError || nextPhoneError) {
            return;
        }

        form.put('/admin/company/profile', { preserveScroll: true });
    };

    const onEmailHostChange = (value) => {
        form.setData('email_login_host', value);
        if (emailHostError) {
            setEmailHostError(validateEmailHost(value));
        }
    };

    const onEmailHostBlur = () => {
        setEmailHostError(validateEmailHost(form.data.email_login_host));
    };

    const optionWithCurrent = (options, current) => {
        const normalized = String(current ?? '').trim();
        return normalized && !options.includes(normalized) ? [normalized, ...options] : options;
    };

    const emailSuffixPreview = form.data.email_login_host
        ? `@${normalizeEmailHost(form.data.email_login_host)}`
        : (emailIdentity?.suffix ?? '');

    return (
        <AppLayout>
            <Head title="Thông tin đơn vị" />
            <PushsalePageShell
                title="Thông tin đơn vị"
                className="ps-unit-profile-page"
                data-page-code="1.1.1"
                collapsible={false}
            >
                {isInternal ? (
                    <div className="alert alert-info ps-unit-internal-notice">
                        <i className="fa fa-info-circle" aria-hidden="true" />
                        {' '}
                        Đây là <strong>đơn vị nội bộ ERM</strong> — dùng để vận hành hệ thống, không phải hồ sơ doanh nghiệp thương mại.
                        Email đăng nhập nhân sự dùng hậu tố <code>{emailSuffixPreview || emailIdentity?.suffix || '@saleops.local'}</code>.
                    </div>
                ) : null}

                <form className="ps-unit-form" onSubmit={submit}>
                    <FieldRow label="Tên đơn vị" required error={form.errors.name}>
                        <input
                            className="form-control ps-unit-control"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            required
                        />
                    </FieldRow>

                    <FieldRow
                        label="Hậu tố email đăng nhập"
                        required
                        error={emailHostError || form.errors.email_login_host}
                        hint={`Ví dụ tài khoản: admin${emailSuffixPreview || '@saleops.local'}. Chỉ dùng chữ, số, dấu gạch ngang và dấu chấm.`}
                    >
                        <div className="ps-unit-email-host">
                            <span className="ps-unit-email-at">@</span>
                            <input
                                className="form-control ps-unit-control"
                                value={form.data.email_login_host}
                                onChange={(event) => onEmailHostChange(event.target.value)}
                                onBlur={onEmailHostBlur}
                                placeholder={emailIdentity?.defaultHost ?? 'saleops.local'}
                                required
                            />
                        </div>
                    </FieldRow>

                    <FieldRow label="Số điện thoại" error={phoneError || form.errors.contact_phone}>
                        <input
                            className="form-control ps-unit-control"
                            value={form.data.contact_phone}
                            onChange={(event) => {
                                form.setData('contact_phone', event.target.value);
                                if (phoneError) {
                                    setPhoneError(validateOptionalPhone(event.target.value));
                                }
                            }}
                            onBlur={() => setPhoneError(validateOptionalPhone(form.data.contact_phone))}
                            placeholder="Ví dụ: 0912345678"
                        />
                    </FieldRow>

                    <FieldRow label="Lĩnh vực sản phẩm" error={form.errors.product_field}>
                        <Select value={form.data.product_field} onChange={(value) => form.setData('product_field', value)}>
                            <option value="">--Chọn lĩnh vực--</option>
                            {optionWithCurrent(productFieldOptions, form.data.product_field).map((item) => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </Select>
                    </FieldRow>

                    <FieldRow label="Địa chỉ" error={form.errors.address}>
                        <input
                            className="form-control ps-unit-control"
                            value={form.data.address}
                            onChange={(event) => form.setData('address', event.target.value)}
                        />
                    </FieldRow>

                    <FieldRow label="Địa chỉ 2 cấp" error={form.errors.address_2}>
                        <label className="ps-unit-checkbox">
                            <input
                                type="checkbox"
                                checked={form.data.use_two_level_address}
                                onChange={(event) => form.setData('use_two_level_address', event.target.checked)}
                            />
                            <span>Sử dụng địa chỉ 2 cấp</span>
                        </label>
                        {form.data.use_two_level_address ? (
                            <input
                                className="form-control ps-unit-control ps-unit-address-2"
                                value={form.data.address_2}
                                onChange={(event) => form.setData('address_2', event.target.value)}
                            />
                        ) : null}
                    </FieldRow>

                    <FieldRow label="Tỉnh/TP" error={form.errors.province_name}>
                        <Select value={form.data.province_name} onChange={(value) => form.setData('province_name', value)}>
                            <option value="">--Chọn Tỉnh/TP--</option>
                            {optionWithCurrent(provinceOptions, form.data.province_name).map((item) => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </Select>
                    </FieldRow>

                    <FieldRow label="Quận/Huyện" error={form.errors.district_name}>
                        <Select value={form.data.district_name} onChange={(value) => form.setData('district_name', value)}>
                            <option value="">--Chọn Quận/Huyện--</option>
                            {optionWithCurrent(districtOptions, form.data.district_name).map((item) => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </Select>
                    </FieldRow>

                    <FieldRow label="Xã/Phường" error={form.errors.ward_name}>
                        <Select value={form.data.ward_name} onChange={(value) => form.setData('ward_name', value)}>
                            <option value="">--Chọn Xã/Phường--</option>
                            {optionWithCurrent(wardOptions, form.data.ward_name).map((item) => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </Select>
                    </FieldRow>

                    <div className="ps-unit-actions">
                        <button type="submit" className="btn btn-sm btn-primary" disabled={form.processing}>
                            <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu
                        </button>
                    </div>
                </form>
            </PushsalePageShell>
        </AppLayout>
    );
}
