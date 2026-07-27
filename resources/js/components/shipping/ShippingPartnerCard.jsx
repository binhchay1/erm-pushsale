import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { vietnamesePhoneError, normalizeVietnamesePhone } from '@/lib/vietnamesePhone';

const providerNames = {
    vnpost: 'VN Post',
    viettel_post: 'Viettel Post',
    ghtk: 'Giao hàng tiết kiệm',
    ghn: 'Giao hàng nhanh',
    jnt: 'J&T',
    ems: 'EMS',
    supership: 'SuperShip',
    best: 'Best',
    boxme: 'BoxMe',
    chimcat: 'Chim Cắt',
    ship60: 'Ship60',
    holaship: 'HolaShip',
    ahamove: 'AhaMove',
    ninjavan: 'NinjaVan',
    spx: 'SPX Express',
    aggregator: 'Đối tác trung gian',
    tiktok_logistics: 'TikTok',
    shopee_logistics: 'Shopee',
};

const credentialAliases = {
    account: ['account', 'username', 'user_id', 'phone', 'user_mobile', 'partner_code', 'customer_code', 'client_id'],
    password: ['password'],
    token: ['token', 'api_token', 'secret_key'],
    shop_id: ['shop_id', 'pick_address_id', 'sender_profile_id', 'warehouse_id', 'account_id'],
    customer_code: ['customer_code', 'client_code', 'account_id'],
    api_key: ['api_key', 'token'],
    api_secret: ['api_secret', 'client_secret', 'secret_key'],
    contract_code: ['contract_code'],
    base_url: ['base_url'],
    provider_code: ['provider_code'],
    otp: ['otp'],
};

const helpText = {
    vnpost: [
        'Cấu hình đối tác tại donhang.vnpost.vn/#/app/cau-hinh-nguoi-dung.',
        'Lấy mã CRM tại donhang.vnpost.vn, nhập tài khoản và mã CRM rồi bấm Kết nối.',
        'Cấu hình bưu cục xử lý, quyền xem hàng và phương thức thu gom rồi bấm Lưu.',
    ],
    viettel_post: [
        'Nhập tài khoản, mật khẩu rồi bấm Kết nối để hệ thống lấy token.',
        'Bấm Xem người gửi để tải danh sách thông tin người gửi, chọn người gửi mặc định rồi Lưu.',
        'Cấu hình dịch vụ gia tăng tại Viettel Post nếu shop cần bảo hiểm, hoàn hàng hoặc dịch vụ đặc biệt.',
    ],
    ghtk: [
        'Lấy API Token tại trang khách hàng GHTK trong mục thông tin cá nhân.',
        'Nhập tài khoản và API Token, chọn cấu hình lấy hàng/xem hàng rồi bấm Lưu.',
    ],
    ghn: [
        'Lấy API Token tại hệ thống GHN, nhập Shop ID và token rồi bấm Kết nối.',
        'Chọn cửa hàng/kho mặc định, quyền xem hàng và cấu hình COD trước khi Lưu.',
    ],
    jnt: ['Mã khách hàng chính là mã shop/tài khoản J&T được cấp.', 'Nhập API key/secret hoặc mã khách hàng rồi bấm Kết nối.'],
    ems: ['Nhập token tài khoản để đăng đơn.', 'Authorization Token dùng để tracking đơn hàng, chọn điểm gửi hàng mặc định rồi bấm Lưu.'],
    holaship: ['Nhập Phone, Password và mã OTP rồi bấm Xác thực.', 'Hệ thống lưu ShopId/Token để tạo đơn và đồng bộ trạng thái.'],
    spx: ['Nhập User ID, Secret key và Account ID được SPX cấp.', 'Bật khai giá/bảo hiểm nếu cần, chọn phương thức lấy hàng và quyền xem hàng rồi Lưu.'],
    default: ['Nhập đúng thông tin API do đơn vị giao hàng cấp.', 'Sau khi Lưu, hệ thống dùng cấu hình này khi đăng đơn và đồng bộ trạng thái/COD.'],
};

function findCredentialKey(provider, logicalKey) {
    const keys = provider.fields.map((field) => field.key);
    return (credentialAliases[logicalKey] ?? [logicalKey]).find((key) => keys.includes(key)) ?? logicalKey;
}

function firstError(errors) {
    const value = Object.values(errors || {})[0];
    if (Array.isArray(value)) return value[0];
    if (value && typeof value === 'object') return Object.values(value)[0] ?? 'Không thể lưu cấu hình.';
    return value ? String(value) : 'Không thể lưu cấu hình.';
}

function Field({ label, required = false, error = '', children, className = '' }) {
    return (
        <div className={`pssp-row${className ? ` ${className}` : ''}${error ? ' is-invalid' : ''}`}>
            <div className="pssp-control-col">
                <div className="pssp-control-wrap">{children}</div>
                {error ? <small className="pssp-field-error">{error}</small> : null}
            </div>
            <label className="pssp-label">
                {label ? (
                    <span className="h-label">
                        {label}
                        {required ? <span className="text-red"> (*)</span> : null}
                    </span>
                ) : null}
            </label>
        </div>
    );
}

function TextInput({ value, onChange, placeholder = '', disabled = false, type = 'text', required = false, invalid = false }) {
    return (
        <input
            type={type}
            value={value ?? ''}
            disabled={disabled}
            placeholder={placeholder}
            required={required}
            aria-invalid={invalid || undefined}
            onChange={(event) => onChange(event.target.value)}
            className={`form-control${invalid ? ' is-invalid' : ''}`}
            autoComplete="off"
        />
    );
}

function SelectInput({ value, onChange, children }) {
    return (
        <select className="form-control chosen" value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            {children}
        </select>
    );
}

function CheckboxInput({ checked, onChange, children }) {
    return (
        <label className="pssp-checkbox">
            <input type="checkbox" checked={Boolean(checked)} onChange={(event) => onChange(event.target.checked)} />
            <span>{children}</span>
        </label>
    );
}

function ActionButton({ children, onClick, type = 'button' }) {
    return <button type={type} className="btn btn-sm btn-primary pssp-inline-action" onClick={onClick}>{children}</button>;
}

function HelpBox({ provider }) {
    const lines = helpText[provider.provider] ?? helpText.default;
    return (
        <div className="pssp-help-row">
            <div className="pssp-control-col">
                <div className="notice pssp-help">
                    <b>+ Hướng dẫn kết nối:</b><br />
                    {lines.map((line) => <span key={line}>- {line}<br /></span>)}
                    <br />
                    <b>+ Hủy kết nối:</b><br />
                    <span>- Xóa thông tin tài khoản/token hoặc tắt trạng thái sử dụng rồi bấm Lưu.</span>
                </div>
            </div>
            <div className="pssp-label" aria-hidden="true" />
        </div>
    );
}

function GenericCredentialFields({ provider, credential, setCredential, fieldErrors, secretSet }) {
    return provider.fields.map((field) => (
        <Field key={field.key} label={field.label} required={field.required} error={fieldErrors[`credentials.${field.key}`] || fieldErrors[field.key] || ''}>
            <TextInput
                type={field.is_secret ? 'password' : 'text'}
                value={credential(field.key)}
                onChange={(value) => setCredential(field.key, value)}
                placeholder={field.is_secret && field.is_set ? field.masked : ''}
                required={Boolean(field.required) && !(field.is_secret && secretSet.has(field.key))}
                invalid={Boolean(fieldErrors[`credentials.${field.key}`] || fieldErrors[field.key])}
            />
        </Field>
    ));
}

function requiredCredentialKeys(provider) {
    return provider.fields.filter((field) => field.required).map((field) => field.key);
}

export function ShippingPartnerCard({ provider }) {
    const initialCredentials = Object.fromEntries(provider.fields.map((field) => [field.key, field.value ?? '']));
    const form = useForm({
        is_enabled: provider.is_enabled,
        integration_mode: provider.integration_mode ?? 'direct',
        webhook_secret: '',
        credentials: initialCredentials,
        settings: {
            pickup_mode: provider.settings?.pickup_mode ?? 'carrier_pickup',
            inspection_mode: provider.settings?.inspection_mode ?? 'view_only',
            goods_type: provider.settings?.goods_type ?? 'parcel',
            insurance_enabled: Boolean(provider.settings?.insurance_enabled),
            allow_partial_delivery: Boolean(provider.settings?.allow_partial_delivery),
            auto_create_waybill: Boolean(provider.settings?.auto_create_waybill),
            auto_restock_return: provider.settings?.auto_restock_return !== false,
            use_carrier_cod: provider.settings?.use_carrier_cod !== false,
            fixed_receiver_phone: provider.settings?.fixed_receiver_phone ?? '',
            sender_profile_id: provider.settings?.sender_profile_id ?? '',
            callback_url_enabled: Boolean(provider.settings?.callback_url_enabled),
            allow_insurance_order: Boolean(provider.settings?.allow_insurance_order),
            extra_services: provider.settings?.extra_services ?? [],
            extra_money: provider.settings?.extra_money ?? '',
            discount_code: provider.settings?.discount_code ?? '',
            pickup_time: provider.settings?.pickup_time ?? '',
            order_label: provider.settings?.order_label ?? '',
            failed_delivery_collect_fee: provider.settings?.failed_delivery_collect_fee ?? '',
            otp: provider.settings?.otp ?? '',
        },
    });
    const [clientErrors, setClientErrors] = useState({});

    const secretSet = useMemo(
        () => new Set(provider.fields.filter((field) => field.is_secret && field.is_set).map((field) => field.key)),
        [provider.fields],
    );

    const fieldErrors = { ...clientErrors, ...form.errors };

    const setCredential = (logicalKey, value) => {
        const key = findCredentialKey(provider, logicalKey);
        form.setData('credentials', { ...form.data.credentials, [key]: value });
        if (clientErrors[`credentials.${key}`] || clientErrors[key]) {
            setClientErrors((current) => {
                const next = { ...current };
                delete next[`credentials.${key}`];
                delete next[key];
                return next;
            });
        }
    };

    const credential = (logicalKey) => form.data.credentials[findCredentialKey(provider, logicalKey)] ?? '';
    const setSetting = (key, value) => form.setData('settings', { ...form.data.settings, [key]: value });
    const hasField = (logicalKey) => provider.fields.some((field) => (credentialAliases[logicalKey] ?? [logicalKey]).includes(field.key));
    const errorFor = (...keys) => keys.map((key) => fieldErrors[key] || fieldErrors[`credentials.${key}`]).find(Boolean) || '';

    const validateClient = () => {
        const next = {};
        requiredCredentialKeys(provider).forEach((key) => {
            const value = String(form.data.credentials?.[key] ?? '').trim();
            const field = provider.fields.find((item) => item.key === key);
            if (!value && !(field?.is_secret && secretSet.has(key))) {
                next[`credentials.${key}`] = `${field?.label ?? key} bắt buộc.`;
            }
        });

        if (['viettel_post'].includes(provider.provider) && !String(form.data.settings.sender_profile_id ?? '').trim()) {
            next['settings.sender_profile_id'] = 'Thông tin người gửi bắt buộc.';
        }

        const fixedPhone = String(form.data.settings.fixed_receiver_phone ?? '').trim();
        if (fixedPhone) {
            const phoneError = vietnamesePhoneError(fixedPhone);
            if (phoneError) next['settings.fixed_receiver_phone'] = phoneError;
        }

        return next;
    };

    const submit = (event) => {
        event.preventDefault();
        const nextErrors = validateClient();
        if (Object.keys(nextErrors).length) {
            setClientErrors(nextErrors);
            toast.error('Vui lòng kiểm tra các trường bắt buộc.');
            return;
        }

        setClientErrors({});
        const credentials = Object.fromEntries(
            Object.entries(form.data.credentials).filter(([key, value]) => !(secretSet.has(key) && value === '')),
        );
        const fixedPhone = String(form.data.settings.fixed_receiver_phone ?? '').trim();
        const settings = {
            ...form.data.settings,
            fixed_receiver_phone: fixedPhone ? (normalizeVietnamesePhone(fixedPhone) ?? fixedPhone) : '',
        };

        form.transform((data) => ({ ...data, credentials, settings })).put(`/admin/shipping-partners/${provider.provider}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Đã lưu cấu hình ${providerNames[provider.provider] ?? provider.label}.`),
            onError: (errors) => toast.error(firstError(errors)),
        });
    };

    const connectButton = <ActionButton onClick={() => toast.message('Đã gửi yêu cầu kiểm tra kết nối.')}><i className="fa fa-spinner" /> Kết nối</ActionButton>;
    const verifyButton = <ActionButton onClick={() => toast.message('Đã gửi yêu cầu xác thực.')}><i className="fa fa-spinner" /> Xác thực</ActionButton>;
    const providerName = providerNames[provider.provider] ?? provider.label;
    const useGeneric = !['vnpost', 'viettel_post', 'ghtk', 'ghn', 'jnt', 'holaship', 'spx'].includes(provider.provider);

    return (
        <form className="pssp-form tab-pane active" onSubmit={submit} noValidate>
            <Field label="" className="pssp-provider-name-row">
                <span className="dvgh-name">{providerName}</span>
                <label className="pssp-status-toggle">
                    <input type="checkbox" checked={Boolean(form.data.is_enabled)} onChange={(event) => form.setData('is_enabled', event.target.checked)} />
                    <span>Sử dụng kết nối này</span>
                </label>
            </Field>

            {provider.provider === 'vnpost' && (
                <>
                    <Field label="Tài khoản" required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} placeholder="Ghi chú để biết đang sử dụng tài khoản VNPOST nào" invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label="Mã khách hàng VNPOST (mã CRM)" required error={errorFor('customer_code', findCredentialKey(provider, 'customer_code'))}>
                        <TextInput required value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} invalid={Boolean(errorFor('customer_code', findCredentialKey(provider, 'customer_code')))} />
                        {connectButton}
                    </Field>
                    <Field label="Mã hợp đồng"><TextInput value={credential('contract_code')} onChange={(value) => setCredential('contract_code', value)} placeholder="Mã hợp đồng của khách hàng với VnPost" /></Field>
                    <Field label="Mã bưu cục xử lý"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder="Không bắt buộc" /></Field>
                    <Field label="Lựa chọn xem hàng">
                        <SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option><option value="open_and_try">Cho thử hàng</option></SelectInput>
                        <SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Thu gom tận nơi</option><option value="dropoff">Gửi hàng tại bưu cục</option></SelectInput>
                    </Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'viettel_post' && (
                <>
                    <Field label="Tài khoản" required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label="Mật khẩu" required error={errorFor('password', findCredentialKey(provider, 'password'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'password'))} value={credential('password')} onChange={(value) => setCredential('password', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'password'))?.masked ?? ''} invalid={Boolean(errorFor('password', findCredentialKey(provider, 'password')))} />
                        {connectButton}
                    </Field>
                    <Field label="Mã Token Viettel Post"><TextInput value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder="Tự động cập nhật sau khi kết nối" /></Field>
                    <Field label="Thông tin người gửi" required error={fieldErrors['settings.sender_profile_id'] || ''}>
                        <TextInput required value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder="Có thể hiểu là kho" invalid={Boolean(fieldErrors['settings.sender_profile_id'])} />
                        <ActionButton onClick={() => toast.message('Đang tải thông tin người gửi.')}>Xem người gửi</ActionButton>
                    </Field>
                    <Field label="Loại hàng hóa"><SelectInput value={form.data.settings.goods_type} onChange={(value) => setSetting('goods_type', value)}><option value="parcel">Hàng hóa</option><option value="document">Tài liệu</option><option value="fragile">Dễ vỡ</option></SelectInput></Field>
                    <Field label="Dịch vụ gia tăng"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>Giao hàng 1 phần</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ghtk' && (
                <>
                    <Field label="Tài khoản" required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label="Mã API Token" required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'token'))?.masked ?? ''} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                    </Field>
                    <Field label="Giao hàng bằng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">GHTK lấy hàng</option><option value="dropoff">Shop gửi hàng</option></SelectInput></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ghn' && (
                <>
                    <Field label="Token" required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'token'))?.masked ?? ''} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                        {connectButton}
                    </Field>
                    {hasField('shop_id') && (
                        <Field label="Shop ID" required error={errorFor('shop_id', findCredentialKey(provider, 'shop_id'))}>
                            <TextInput required value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} invalid={Boolean(errorFor('shop_id', findCredentialKey(provider, 'shop_id')))} />
                        </Field>
                    )}
                    <Field label="Cửa hàng mặc định"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder="Mã shop / kho GHN" /></Field>
                    <Field label="Gói dịch vụ"><SelectInput value={form.data.settings.goods_type} onChange={(value) => setSetting('goods_type', value)}><option value="parcel">Hàng nhẹ</option><option value="fragile">Hàng dễ vỡ</option><option value="document">Tài liệu</option></SelectInput></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                </>
            )}

            {provider.provider === 'jnt' && (
                <>
                    <Field label="Mã khách hàng" required error={errorFor('customer_code', findCredentialKey(provider, 'customer_code'))}>
                        <TextInput required value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} invalid={Boolean(errorFor('customer_code', findCredentialKey(provider, 'customer_code')))} />
                    </Field>
                    <Field label="API key" required error={errorFor('api_key', findCredentialKey(provider, 'api_key'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'api_key'))} value={credential('api_key')} onChange={(value) => setCredential('api_key', value)} invalid={Boolean(errorFor('api_key', findCredentialKey(provider, 'api_key')))} />
                    </Field>
                    <Field label="API secret" required error={errorFor('api_secret', findCredentialKey(provider, 'api_secret'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'api_secret'))} value={credential('api_secret')} onChange={(value) => setCredential('api_secret', value)} invalid={Boolean(errorFor('api_secret', findCredentialKey(provider, 'api_secret')))} />
                        {connectButton}
                    </Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                </>
            )}

            {provider.provider === 'holaship' && (
                <>
                    <Field label="Phone" required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label="Password" required error={errorFor('password', findCredentialKey(provider, 'password'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'password'))} value={credential('password')} onChange={(value) => setCredential('password', value)} invalid={Boolean(errorFor('password', findCredentialKey(provider, 'password')))} />
                    </Field>
                    <Field label="OTP"><TextInput value={form.data.settings.otp} onChange={(value) => setSetting('otp', value)} />{verifyButton}</Field>
                    <Field label="Shop ID"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                </>
            )}

            {provider.provider === 'spx' && (
                <>
                    <Field label="User ID" required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label="Secret key" required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                    </Field>
                    <Field label="Account ID" required error={errorFor('shop_id', findCredentialKey(provider, 'shop_id'))}>
                        <TextInput required value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} invalid={Boolean(errorFor('shop_id', findCredentialKey(provider, 'shop_id')))} />
                        {connectButton}
                    </Field>
                    <Field label="Sử dụng bảo hiểm"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá tới lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                    <Field label="Giao hàng thất bại thu tiền"><TextInput value={form.data.settings.failed_delivery_collect_fee} onChange={(value) => setSetting('failed_delivery_collect_fee', value)} /></Field>
                </>
            )}

            {useGeneric && (
                <>
                    <GenericCredentialFields provider={provider} credential={credential} setCredential={setCredential} fieldErrors={fieldErrors} secretSet={secretSet} />
                    <Field label="Cửa hàng / kho"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option><option value="open_and_try">Cho thử hàng</option></SelectInput></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá tới lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option><option value="manual">Thủ công</option></SelectInput></Field>
                    <Field label="Dịch vụ"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>Giao hàng 1 phần</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            <div className="pssp-save-row">
                <div className="pssp-control-col">
                    <button type="submit" disabled={form.processing} className="btn btn-sm btn-primary mr15">
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {form.processing ? 'Đang lưu…' : 'Lưu'}
                    </button>
                    {provider.webhook_url && <span className="pssp-webhook">Webhook: {provider.webhook_url}</span>}
                </div>
                <div className="pssp-label" />
            </div>

            <HelpBox provider={provider} />
        </form>
    );
}
