import { useForm } from '@inertiajs/react';
import { ExternalLink, Save } from 'lucide-react';
import { toast } from 'sonner';

import { CarrierApiTestPanel } from '@/components/shipping/CarrierApiTestPanel';
import { Button } from '@/components/ui/button';

const settingOptions = {
    pickup_mode: [['carrier_pickup', 'Hãng đến lấy'], ['dropoff', 'Gửi tại bưu cục'], ['manual', 'Thủ công']],
    inspection_mode: [['none', 'Không cho xem hàng'], ['view_only', 'Cho xem hàng'], ['open_and_try', 'Cho thử hàng']],
    goods_type: [['parcel', 'Hàng'], ['document', 'Tài liệu'], ['food', 'Đồ ăn'], ['fragile', 'Dễ vỡ']],
};

function ConfigField({ field, value, onChange }) {
    const type = field.is_secret ? 'password' : 'text';
    return (
        <div className="ps-shipping-form-row">
            <label>{field.label} {field.required && <b>(*)</b>}</label>
            <div>
                <input
                    type={type}
                    value={value ?? ''}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={field.is_secret && field.is_set ? field.masked : ''}
                    autoComplete="off"
                />
                {field.source === 'env' && <small>Đang lấy từ biến môi trường; nhập để ghi đè cho doanh nghiệp.</small>}
            </div>
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
        },
    });

    const setCredential = (key, value) => form.setData('credentials', { ...form.data.credentials, [key]: value });
    const setSetting = (key, value) => form.setData('settings', { ...form.data.settings, [key]: value });

    const submit = (event) => {
        event.preventDefault();
        form.put(`/admin/shipping-partners/${provider.provider}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Đã lưu cấu hình ${provider.label}.`),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu cấu hình.'),
        });
    };

    return (
        <form className="ps-shipping-provider-form" onSubmit={submit}>
            <div className="ps-shipping-provider-heading">
                <div>
                    <h3>{provider.label}</h3>
                    <p>{provider.description}</p>
                </div>
                <label className="ps-shipping-enable">
                    <input type="checkbox" checked={form.data.is_enabled} onChange={(event) => form.setData('is_enabled', event.target.checked)} />
                    <span>Kích hoạt</span>
                </label>
            </div>

            <div className="ps-shipping-form-row">
                <label>Kiểu kết nối</label>
                <select value={form.data.integration_mode} onChange={(event) => form.setData('integration_mode', event.target.value)}>
                    <option value="manual">Thủ công</option>
                    <option value="direct">API trực tiếp</option>
                    <option value="direct_generic">API cấu hình</option>
                    <option value="aggregator">Đối tác trung gian</option>
                </select>
            </div>

            {provider.fields.map((field) => (
                <ConfigField
                    key={field.key}
                    field={field}
                    value={form.data.credentials[field.key]}
                    onChange={(value) => setCredential(field.key, value)}
                />
            ))}

            <div className="ps-shipping-form-row">
                <label>Webhook nhận trạng thái</label>
                <div>
                    <input readOnly value={provider.webhook_url} />
                    <small>Hãng vận chuyển hoặc đối tác trung gian phải POST trạng thái, phí và COD vào URL này.</small>
                </div>
            </div>
            <div className="ps-shipping-form-row">
                <label>Khóa xác thực webhook</label>
                <input
                    type="password"
                    value={form.data.webhook_secret}
                    placeholder={provider.webhook_secret_set ? provider.webhook_secret_masked : 'Nhập khóa bí mật'}
                    onChange={(event) => form.setData('webhook_secret', event.target.value)}
                />
            </div>

            {Object.entries(settingOptions).map(([key, options]) => (
                <div className="ps-shipping-form-row" key={key}>
                    <label>{key === 'pickup_mode' ? 'Hình thức gửi hàng' : key === 'inspection_mode' ? 'Cho xem/thử hàng' : 'Loại hàng hóa'}</label>
                    <select value={form.data.settings[key]} onChange={(event) => setSetting(key, event.target.value)}>
                        {options.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </div>
            ))}

            <div className="ps-shipping-form-row">
                <label>Cố định SĐT người nhận khi đăng đơn</label>
                <input value={form.data.settings.fixed_receiver_phone} onChange={(event) => setSetting('fixed_receiver_phone', event.target.value)} />
            </div>
            <div className="ps-shipping-form-row">
                <label>Mã người gửi / kho bên vận chuyển</label>
                <input value={form.data.settings.sender_profile_id} onChange={(event) => setSetting('sender_profile_id', event.target.value)} />
            </div>

            <div className="ps-shipping-switch-grid">
                {[
                    ['insurance_enabled', 'Khai giá bảo hiểm'],
                    ['allow_partial_delivery', 'Cho giao một phần'],
                    ['auto_create_waybill', 'Tự tạo vận đơn khi Sale chốt'],
                    ['auto_restock_return', 'Tự nhập kho khi hãng báo đã hoàn'],
                    ['use_carrier_cod', 'Đối soát COD từ hãng'],
                ].map(([key, label]) => (
                    <label key={key}>
                        <input type="checkbox" checked={Boolean(form.data.settings[key])} onChange={(event) => setSetting(key, event.target.checked)} />
                        <span>{label}</span>
                    </label>
                ))}
            </div>

            {provider.test_actions?.length > 0 && (
                <CarrierApiTestPanel provider={provider.provider} label={provider.label} testActions={provider.test_actions} />
            )}

            <div className="ps-shipping-form-actions">
                {provider.docs_url && (
                    <a href={provider.docs_url} target="_blank" rel="noreferrer"><ExternalLink size={14} /> Tài liệu kết nối</a>
                )}
                <Button type="submit" size="sm" disabled={form.processing}><Save size={14} /> Lưu</Button>
            </div>

            <div className="ps-shipping-help">
                <b>+ Luồng tự động:</b><br />
                - Sale chốt đơn → hệ thống tạo vận đơn theo hãng mặc định (nếu bật tự động).<br />
                - Tạo vận đơn thành công → trừ tồn kho đúng một lần.<br />
                - Webhook giao thành công/đối soát → cập nhật COD, phí giao, phí COD và doanh thu.<br />
                - Webhook hoàn hàng → tạo phiếu nhập hoàn; chỉ sản phẩm còn bán được mới cộng tồn.<br />
                - Các lần gửi lại webhook được chống ghi nhận trùng bằng khóa sự kiện.
            </div>
        </form>
    );
}
