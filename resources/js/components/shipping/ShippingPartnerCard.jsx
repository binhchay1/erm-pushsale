import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

const providerNames = {
    vnpost: 'VNPOST',
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
};

const helpText = {
    vnpost: ['Cấu hình đối tác tại donhang.vnpost.vn/#/app/cau-hinh-nguoi-dung', 'Lấy mã CRM tại donhang.vnpost.vn', 'Nhập tài khoản, mã CRM sau đó bấm Kết nối', 'Cấu hình bưu cục xử lý rồi bấm Lưu'],
    viettel_post: ['Nhập tài khoản, mật khẩu sau đó bấm Kết nối', 'Bấm “Xem người gửi” sau đó đổi hệ thống tải về danh sách thông tin người gửi', 'Chọn người gửi mặc định sau đó bấm Lưu', 'Cấu hình dịch vụ gia tăng tại viettelpost.vn/cau-hinh-tai-khoan'],
    ghtk: ['Lấy mã API Token tại khachhang.giaohangtietkiem.vn/khach-hang/thong-tin-ca-nhan', 'Nhập tài khoản, API Token sau đó bấm Lưu'],
    ghn: ['Lấy mã API Token tại sso.ghn.vn/ssoLogin?app=api-v3', 'Bấm Kết nối để tải danh sách cửa hàng', 'Chọn cửa hàng mặc định sau đó bấm Lưu'],
    jnt: ['Mã khách hàng chính là tên đăng nhập J&T', 'Nhập mã khách hàng sau đó bấm Kết nối'],
    ems: ['Nhập token tài khoản để đăng đơn', 'Authorization Token dùng để tracking đơn hàng', 'Chọn điểm gửi hàng mặc định sau đó bấm Lưu'],
    holaship: ['Nhập Phone, Password và mã OTP sau đó bấm Xác thực', 'Hệ thống nhận ShopId và Token rồi dùng để tạo đơn/tracking'],
    spx: ['Nhập ID người dùng và khóa bí mật được SPX cấp', 'Bật khai giá/bảo hiểm nếu hãng yêu cầu', 'Chọn phương thức lấy hàng và quyền xem hàng trước khi Lưu'],
    aggregator: ['Dùng khi có bên trung gian đã tích hợp nhiều hãng vận chuyển', 'Nhập Base URL, token, mã hãng và mapping endpoint', 'Webhook của đối tác trung gian vẫn POST về URL chuẩn của ERM'],
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
};

function findCredentialKey(provider, logicalKey) {
    const keys = provider.fields.map((field) => field.key);
    return (credentialAliases[logicalKey] ?? [logicalKey]).find((key) => keys.includes(key)) ?? logicalKey;
}

function Field({ label, required, children, wide = false }) {
    return (
        <div className={`pssp-row${wide ? ' wide' : ''}`}>
            <label>{label}{required && <span className="required"> (*)</span>}</label>
            <div className="pssp-control-wrap">{children}</div>
        </div>
    );
}

function TextInput({ value, onChange, placeholder = '', disabled = false, type = 'text' }) {
    return <input type={type} value={value ?? ''} disabled={disabled} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} autoComplete="off" />;
}

function SelectInput({ value, onChange, children }) {
    return <select value={value ?? ''} onChange={(event) => onChange(event.target.value)}>{children}</select>;
}

function CheckboxInput({ checked, onChange, children }) {
    return (
        <label className="pssp-checkbox">
            <input type="checkbox" checked={Boolean(checked)} onChange={(event) => onChange(event.target.checked)} />
            <span>{children}</span>
        </label>
    );
}

function HelpBox({ provider }) {
    const lines = helpText[provider.provider] ?? helpText.aggregator;
    return (
        <div className="pssp-help">
            <b>+ Hướng dẫn kết nối:</b><br />
            {lines.map((line) => <span key={line}>- {line}<br /></span>)}
            <br />
            <b>+ Hủy kết nối:</b><br />
            <span>- Xóa tài khoản sau đó bấm “Lưu”</span>
        </div>
    );
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
            extra_services: provider.settings?.extra_services ?? [],
            extra_money: provider.settings?.extra_money ?? '',
            discount_code: provider.settings?.discount_code ?? '',
            pickup_time: provider.settings?.pickup_time ?? '',
            order_label: provider.settings?.order_label ?? '',
            failed_delivery_collect_fee: provider.settings?.failed_delivery_collect_fee ?? '',
            callback_url_enabled: Boolean(provider.settings?.callback_url_enabled),
            allow_insurance_order: Boolean(provider.settings?.allow_insurance_order),
        },
    });

    const setCredential = (logicalKey, value) => {
        const key = findCredentialKey(provider, logicalKey);
        form.setData('credentials', { ...form.data.credentials, [key]: value });
    };
    const credential = (logicalKey) => form.data.credentials[findCredentialKey(provider, logicalKey)] ?? '';
    const setSetting = (key, value) => form.setData('settings', { ...form.data.settings, [key]: value });

    const submit = (event) => {
        event.preventDefault();
        form.put(`/admin/shipping-partners/${provider.provider}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Đã lưu cấu hình ${providerNames[provider.provider] ?? provider.label}.`),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu cấu hình.'),
        });
    };

    const connectButton = <button type="button" className="pssp-connect" onClick={() => toast.message('Đã gửi yêu cầu kiểm tra kết nối.')}><i className="fa fa-spinner" /> Kết nối</button>;
    const verifyButton = <button type="button" className="pssp-connect" onClick={() => toast.message('Đã gửi yêu cầu xác thực.')}><i className="fa fa-spinner" /> Xác thực</button>;

    const name = providerNames[provider.provider] ?? provider.label;

    return (
        <form className="pssp-form" onSubmit={submit}>
            <h3>{name}</h3>
            <input type="hidden" value={form.data.integration_mode} readOnly />

            {provider.provider === 'vnpost' && (
                <>
                    <Field label="Tài khoản" required><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} placeholder="Ghi chú để biết đang sử dụng tài khoản VNPOST nào" /></Field>
                    <Field label="Mã khách hàng VNPOST (mã CRM)" required><TextInput value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} />{connectButton}</Field>
                    <Field label="Mã hợp đồng"><TextInput value={credential('contract_code')} onChange={(value) => setCredential('contract_code', value)} placeholder="Mã hợp đồng của khách hàng với VnPost" /></Field>
                    <Field label="Mã bưu cục xử lý (không bắt buộc)"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option><option value="open_and_try">Cho thử hàng</option></SelectInput><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Thu gom tận nơi</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'viettel_post' && (
                <>
                    <Field label="Tài khoản" required><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="Mật khẩu" required><TextInput type="password" value={credential('password')} onChange={(value) => setCredential('password', value)} />{connectButton}</Field>
                    <Field label="Mã Token Viettel Post (tự động cập nhật)"><TextInput value={credential('token')} onChange={(value) => setCredential('token', value)} disabled /></Field>
                    <Field label="Thông tin người gửi (có thể hiểu là kho)" required><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /><a className="pssp-inline-link">Xem người gửi</a></Field>
                    <Field label="Dịch vụ gia tăng (không bắt buộc)"><TextInput value={(form.data.settings.extra_services ?? []).join(', ')} onChange={(value) => setSetting('extra_services', value.split(',').map((item) => item.trim()).filter(Boolean))} placeholder="Các dịch vụ cách nhau bởi dấu phẩy (,)" /></Field>
                    <Field label="Tiền thêm (Extra Money)"><TextInput value={form.data.settings.extra_money} onChange={(value) => setSetting('extra_money', value)} /></Field>
                    <Field label="Mã giảm giá"><TextInput value={form.data.settings.discount_code} onChange={(value) => setSetting('discount_code', value)} /></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                    <Field label="Loại hàng hóa"><SelectInput value={form.data.settings.goods_type} onChange={(value) => setSetting('goods_type', value)}><option value="parcel">Hàng</option><option value="document">Tài liệu</option><option value="fragile">Dễ vỡ</option></SelectInput></Field>
                </>
            )}

            {provider.provider === 'ghtk' && (
                <>
                    <Field label="Tài khoản" required><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="API Token" required><TextInput type="password" value={credential('token')} onChange={(value) => setCredential('token', value)} />{connectButton}</Field>
                    <Field label="Cửa hàng (có thể hiểu là kho)"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} disabled /><a className="pssp-inline-link">Xem danh sách</a></Field>
                    <Field label="Thời gian lấy hàng"><SelectInput value={form.data.settings.pickup_time} onChange={(value) => setSetting('pickup_time', value)}><option value="">-- Lựa chọn thời gian --</option><option value="morning">Sáng</option><option value="afternoon">Chiều</option></SelectInput></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá đến lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Nhãn đơn hàng"><TextInput value={form.data.settings.order_label} onChange={(value) => setSetting('order_label', value)} /></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ghn' && (
                <>
                    <Field label="Tài khoản"><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="API key - Token" required><TextInput type="password" value={credential('token')} onChange={(value) => setCredential('token', value)} /></Field>
                    <Field label="Cửa hàng (có thể hiểu là kho)" required><TextInput value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} /><a className="pssp-inline-link">Xem danh sách</a>{connectButton}</Field>
                    <Field label="Cấu hình call back url (tự động)"><CheckboxInput checked={form.data.settings.callback_url_enabled} onChange={(value) => setSetting('callback_url_enabled', value)}>Cấu hình call back url</CheckboxInput></Field>
                    <Field label="Sử dụng bảo hiểm cho đơn hàng"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput><CheckboxInput checked={form.data.settings.allow_insurance_order} onChange={(value) => setSetting('allow_insurance_order', value)}>Cho phép đăng đơn với [Giá trị bảo hiểm] tối đa là 5,000,000 vnđ</CheckboxInput></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="none">Không cho xem hàng</option><option value="view_only">Cho xem hàng</option></SelectInput></Field>
                    <Field label="Lựa chọn ca lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="">--Chọn ca lấy hàng--</option><option value="carrier_pickup">Lấy tại shop</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput><SelectInput value={form.data.settings.pickup_time} onChange={(value) => setSetting('pickup_time', value)}><option value="">Lựa chọn thời gian lấy hàng</option><option value="morning">Sáng</option><option value="afternoon">Chiều</option></SelectInput></Field>
                    <Field label="Giao hàng thất bại thu tiền"><TextInput value={form.data.settings.failed_delivery_collect_fee} onChange={(value) => setSetting('failed_delivery_collect_fee', value)} /></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'jnt' && (
                <>
                    <Field label="Tài khoản (Dùng để phân biệt nhiều tài khoản kho)"><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} placeholder="Ghi chú để biết đang sử dụng tài khoản J&T nào" /></Field>
                    <Field label="Mã khách hàng J&T" required><TextInput value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} />{connectButton}</Field>
                    <Field label="Key khách hàng J&T"><TextInput value={credential('api_secret')} onChange={(value) => setCredential('api_secret', value)} placeholder="Sử dụng nếu in đơn bởi J&T" /></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá tới lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Giao hàng 1 phần"><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>Cho giao 1 phần</CheckboxInput></Field>
                    <Field label="Sử dụng bảo hiểm cho đơn hàng"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ems' && (
                <>
                    <Field label="Phiên bản" required><SelectInput value={form.data.settings.api_version ?? '1.0'} onChange={(value) => setSetting('api_version', value)}><option value="1.0">1.0</option><option value="2.0">2.0</option></SelectInput></Field>
                    <Field label="Tài khoản"><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="Token tài khoản" required><TextInput type="password" value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder="Dùng để đăng đơn" /></Field>
                    <Field label="Authorization Token (Dùng để tracking đơn hàng)"><TextInput type="password" value={credential('api_secret')} onChange={(value) => setCredential('api_secret', value)} placeholder="Dùng để tracking đơn hàng" /></Field>
                    <Field label="Điểm gửi hàng (có thể hiểu là kho)" required><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /><a className="pssp-inline-link">Xem danh sách</a>{connectButton}</Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="none">Không cho xem hàng</option><option value="view_only">Cho xem hàng</option></SelectInput></Field>
                    <Field label="Đặt làm mặc định"><CheckboxInput checked={form.data.is_enabled} onChange={(value) => form.setData('is_enabled', value)}>Có sử dụng dịch vụ kho của EMS</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'holaship' && (
                <>
                    <Field label="Phone" required><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="Password" required><TextInput type="password" value={credential('password')} onChange={(value) => setCredential('password', value)} />{connectButton}</Field>
                    <Field label="Mã OTP" required><TextInput value={form.data.settings.otp ?? ''} onChange={(value) => setSetting('otp', value)} />{verifyButton}</Field>
                    <Field label="ShopId"><TextInput value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} disabled /></Field>
                    <Field label="Token"><TextInput value={credential('token')} onChange={(value) => setCredential('token', value)} disabled /></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">--Quyền xem hàng của người nhận--</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                    <Field label="Sử dụng bảo hiểm cho đơn hàng"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput></Field>
                    <Field label="Giao hàng 1 phần"><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>Cho giao 1 phần</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'spx' && (
                <>
                    <Field label="ID người dùng" required><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="Khóa bí mật" required><TextInput type="password" value={credential('api_secret')} onChange={(value) => setCredential('api_secret', value)} />{connectButton}</Field>
                    <Field label="Sử dụng bảo hiểm"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput></Field>
                    <Field label="Cho phép đăng đơn với [Giá trị bảo hiểm] tối đa [19,999,999] vnđ"><CheckboxInput checked={form.data.settings.allow_insurance_order} onChange={(value) => setSetting('allow_insurance_order', value)}>Cho phép đăng đơn với [Giá trị bảo hiểm] tối đa [19,999,999] vnđ</CheckboxInput></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá tới lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">--Quyền xem hàng của người nhận--</option><option value="none">Không cho xem hàng</option></SelectInput></Field>
                    <Field label="Giao hàng thất bại thu tiền"><TextInput value={form.data.settings.failed_delivery_collect_fee} onChange={(value) => setSetting('failed_delivery_collect_fee', value)} /></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {!['vnpost', 'viettel_post', 'ghtk', 'ghn', 'jnt', 'ems', 'holaship', 'spx'].includes(provider.provider) && (
                <>
                    <Field label="Tài khoản"><TextInput value={credential('account')} onChange={(value) => setCredential('account', value)} /></Field>
                    <Field label="API Token" required><TextInput type="password" value={credential('token')} onChange={(value) => setCredential('token', value)} />{connectButton}</Field>
                    {provider.provider === 'aggregator' && <Field label="Base URL" required><TextInput value={credential('base_url')} onChange={(value) => setCredential('base_url', value)} placeholder="https://partner.example/api" /></Field>}
                    {provider.provider === 'aggregator' && <Field label="Mã hãng tại bên trung gian"><TextInput value={credential('provider_code')} onChange={(value) => setCredential('provider_code', value)} /></Field>}
                    <Field label="Cửa hàng / kho"><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                    <Field label="Lựa chọn xem hàng"><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">Cho xem hàng</option><option value="none">Không cho xem hàng</option><option value="open_and_try">Cho thử hàng</option></SelectInput></Field>
                    <Field label="Phương thức lấy hàng"><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">Bưu tá tới lấy hàng</option><option value="dropoff">Gửi tại bưu cục</option></SelectInput></Field>
                    <Field label="Sử dụng bảo hiểm cho đơn hàng"><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>Sử dụng bảo hiểm</CheckboxInput></Field>
                    <Field label="Giao hàng 1 phần"><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>Cho giao 1 phần</CheckboxInput></Field>
                    <Field label="Cố định SĐT người nhận khi đăng đơn"><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            <div className="pssp-save-row">
                <button type="submit" disabled={form.processing}><i className="fa fa-save" /> Lưu</button>
                {provider.provider === 'viettel_post' && <a>↻ Kiểm tra người gửi</a>}
            </div>

            <HelpBox provider={provider} />
        </form>
    );
}
