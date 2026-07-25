import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const emptyWarehouse = {
    name: '',
    code: '',
    sort_order: '',
    phone: '',
    pick_province: '',
    pick_district: '',
    pick_ward: '',
    address: '',
    use_two_level_address: false,
    manager_user_id: '',
    sender_registration_name: '',
    sender_print_note: '',
    default_delivery_provinces: '',
    vtp_code: '',
    ghtk_pick_address_id: '',
};

const DEFAULT_WARDS = ['Phường Ba Đình', 'Phường Hòa Bình', 'Phường Cầu Giấy', 'Phường Hàng Bài'];
const NORTH_PROVINCES = 'Hà Nội, Phú Thọ, Bắc Ninh, Hải Phòng, Quảng Ninh, Thái Nguyên';
const CENTRAL_PROVINCES = 'Thanh Hóa, Nghệ An, Hà Tĩnh, Đà Nẵng, Khánh Hòa, Gia Lai';
const SOUTH_PROVINCES = 'Hồ Chí Minh, Đồng Nai, Tây Ninh, Cần Thơ, An Giang, Cà Mau';

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function optionNodes(items, placeholder) {
    return (
        <>
            <option value="">{placeholder}</option>
            {(items ?? []).map((item) => {
                const value = typeof item === 'string' ? item : (item.id ?? item.value ?? item.key ?? item.name);
                const label = typeof item === 'string' ? item : (item.name ?? item.label ?? item.title ?? item.key);
                return <option key={String(value)} value={value}>{label}</option>;
            })}
        </>
    );
}

function DialogShell({ title, open, onClose, children, wide = false, className = '' }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width={wide ? 'calc(100vw - 60px)' : '800px'}
            className={`ps-warehouse-dialog ${className}`}
            bodyClassName="ps-source-dialog-body ps-warehouse-dialog-body"
        >
            {children}
        </PushsaleDialog>
    );
}

function WarehouseForm({ form, managers, provinces, districts, editing, onSubmit, onAppendProvinces }) {
    const wards = useMemo(() => {
        const current = form.data.pick_ward ? [form.data.pick_ward] : [];
        return Array.from(new Set([...current, ...DEFAULT_WARDS]));
    }, [form.data.pick_ward]);

    return (
        <form onSubmit={onSubmit} className="ps-warehouse-form">
            <div className="ps-warehouse-form-grid">
                <label><span>Tên kho <b>(*)</b></span><input className="form-control" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                <label><span>Số thứ tự</span><input className="form-control" type="number" min="0" value={form.data.sort_order ?? ''} onChange={(event) => form.setData('sort_order', event.target.value)} /></label>

                <label><span>Mã kho</span><input className="form-control" value={form.data.code ?? ''} onChange={(event) => form.setData('code', event.target.value)} /></label>
                <label><span>Quản kho</span><select className="form-control" value={form.data.manager_user_id ?? ''} onChange={(event) => form.setData('manager_user_id', event.target.value)}>{optionNodes(managers, '--Chọn quản kho--')}</select></label>

                <label className="ps-checkbox-row"><span>Địa chỉ 2 cấp</span><span className="ps-checkbox-inline"><input type="checkbox" checked={Boolean(form.data.use_two_level_address)} onChange={(event) => form.setData('use_two_level_address', event.target.checked)} /> Sử dụng địa chỉ 2 cấp</span></label>
                <label><span>Số ĐT quản kho</span><input className="form-control" value={form.data.phone ?? ''} onChange={(event) => form.setData('phone', event.target.value)} /></label>

                <label><span>Tỉnh/TP <b>(*)</b></span><select className="form-control" required value={form.data.pick_province ?? ''} onChange={(event) => form.setData('pick_province', event.target.value)}>{optionNodes(provinces, '--Chọn Tỉnh/TP--')}</select></label>
                <label><span>Đăng đơn người gửi</span><input className="form-control" value={form.data.sender_registration_name ?? ''} onChange={(event) => form.setData('sender_registration_name', event.target.value)} /></label>

                <label><span>Quận/Huyện <b>(*)</b></span><select className="form-control" required value={form.data.pick_district ?? ''} onChange={(event) => form.setData('pick_district', event.target.value)}>{optionNodes(districts, '--Quận/Huyện--')}</select></label>
                <label className="ps-textarea-label"><span>In đơn người gửi</span><textarea className="form-control" value={form.data.sender_print_note ?? ''} onChange={(event) => form.setData('sender_print_note', event.target.value)} placeholder="Thông tin người gửi khi in đơn" /></label>

                <label><span>Phường/Xã <b>(*)</b></span><select className="form-control" required value={form.data.pick_ward ?? ''} onChange={(event) => form.setData('pick_ward', event.target.value)}>{optionNodes(wards, '--Phường/Xã--')}</select></label>
                <div />

                <label><span>Địa chỉ <b>(*)</b></span><input className="form-control" required value={form.data.address ?? ''} onChange={(event) => form.setData('address', event.target.value)} /></label>
                <div />

                <label><span>Số điện thoại <b>(*)</b></span><input className="form-control" required value={form.data.phone ?? ''} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                <div />

                <label className="span-2"><span>Ghi chú</span><input className="form-control" value={form.data.sender_print_note ?? ''} onChange={(event) => form.setData('sender_print_note', event.target.value)} /></label>

                <label className="span-2"><span>Tỉnh/TP mặc định<br />giao từ kho này</span><input className="form-control" value={form.data.default_delivery_provinces ?? ''} onChange={(event) => form.setData('default_delivery_provinces', event.target.value)} /></label>
            </div>

            <div className="ps-warehouse-region-links">
                <button type="button" onClick={() => onAppendProvinces(NORTH_PROVINCES)}><i className="fa fa-plus" /> Thêm các tỉnh miền Bắc</button>
                <button type="button" onClick={() => onAppendProvinces(CENTRAL_PROVINCES)}><i className="fa fa-plus" /> Thêm các tỉnh miền Trung</button>
                <button type="button" onClick={() => onAppendProvinces(SOUTH_PROVINCES)}><i className="fa fa-plus" /> Thêm các tỉnh miền Nam</button>
                <button type="button" onClick={() => form.setData('default_delivery_provinces', '')}><i className="fa fa-trash" /> Xóa lựa chọn tỉnh</button>
            </div>

            {Object.keys(form.errors).length > 0 && <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div>}
            <div className="ps-warehouse-form-actions"><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-plus" /> {editing ? 'Lưu' : 'Thêm mới'}</button></div>
        </form>
    );
}

function ShippingAccountDialog({ open, warehouse, providers, form, selectedProvider, onSelectProvider, onClose, onSubmit, setProviderField }) {
    const activeProvider = providers.find((item) => item.key === selectedProvider) ?? providers[0];
    const activeSettings = form.data.shipping_account_settings?.[activeProvider?.key] ?? {};
    const services = activeProvider?.services ?? [];

    return (
        <DialogShell open={open} onClose={onClose} wide title={`CẤU HÌNH TÀI KHOẢN GIAO HÀNG CỦA KHO [${warehouse?.name ?? ''}]`} className="ps-shipping-account-dialog">
            <form onSubmit={onSubmit}>
                <div className="ps-shipping-default-panel">
                    <h3>ĐƠN VỊ GIAO HÀNG MẶC ĐỊNH</h3>
                    <div className="ps-shipping-default-row">
                        <label>Phương thức giao hàng mặc định <b>(*)</b></label>
                        <select value={form.data.default_shipping_provider ?? ''} onChange={(event) => { form.setData('default_shipping_provider', event.target.value); onSelectProvider(event.target.value || providers[0]?.key); }}>
                            {optionNodes(providers.map((item) => ({ id: item.key, name: item.label })), '-- Chọn phương thức giao hàng --')}
                        </select>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button>
                    </div>
                    <div className="ps-shipping-default-row">
                        <label>Giao hàng bằng mặc định <b>(*)</b></label>
                        <select value={form.data.default_shipping_service ?? ''} onChange={(event) => form.setData('default_shipping_service', event.target.value)}>
                            {optionNodes(services.map((item) => ({ id: item.code, name: item.label })), '-- Chọn dịch vụ --')}
                        </select>
                    </div>
                </div>

                <div className="ps-shipping-config-panel">
                    <h3>CẤU HÌNH GIAO HÀNG</h3>
                    <div className="ps-shipping-config-body">
                        <div className="ps-shipping-provider-tabs">
                            {providers.map((item) => <button type="button" key={item.key} className={item.key === activeProvider?.key ? 'active' : ''} onClick={() => onSelectProvider(item.key)}>{item.label}</button>)}
                        </div>
                        <div className="ps-shipping-provider-form">
                            <h4>{activeProvider?.label}</h4>
                            <div className="ps-shipping-provider-grid">
                                <label><span>Tài khoản <b>(*)</b></span><input value={activeSettings.account ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'account', event.target.value)} /></label>
                                <label><span>API Token <b>(*)</b></span><input value={activeSettings.api_token ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'api_token', event.target.value)} /></label>
                                <label><span>Cửa hàng (có thể hiểu là kho)</span><input value={activeSettings.shop_id ?? activeSettings.store_code ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'shop_id', event.target.value)} /></label>
                                <label><span>Thời gian lấy hàng</span><select value={activeSettings.pickup_time ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'pickup_time', event.target.value)}>{optionNodes(['Sáng', 'Chiều', 'Tối'], '-- Lựa chọn thời gian --')}</select></label>
                                <label><span>Phương thức lấy hàng</span><select value={activeSettings.pickup_method ?? 'carrier_pickup'} onChange={(event) => setProviderField(activeProvider.key, 'pickup_method', event.target.value)}><option value="carrier_pickup">Bưu tá đến lấy hàng</option><option value="dropoff">Mang hàng ra bưu cục</option><option value="manual">Tự giao / thủ công</option></select></label>
                                <label><span>Nhãn đơn hàng</span><input value={activeSettings.order_label_note ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'order_label_note', event.target.value)} /></label>
                                <label><span>Cố định SĐT người nhận khi đăng đơn</span><input value={activeSettings.fixed_receiver_phone ?? ''} onChange={(event) => setProviderField(activeProvider.key, 'fixed_receiver_phone', event.target.value)} /></label>
                            </div>
                            <div className="ps-shipping-provider-actions">
                                <button type="submit" className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button>
                                <button type="button" className="btn btn-default"><i className="fa fa-chain-broken" /> Kết nối</button>
                                <button type="button" className="btn btn-link">Xem danh sách</button>
                            </div>
                            <div className="ps-shipping-help-box">
                                <p><b>+ Hướng dẫn kết nối:</b></p>
                                <p>- Cấu hình tài khoản giao vận theo từng kho để đăng đơn đúng người gửi/kho lấy hàng.</p>
                                <p>- Nhập tài khoản, API Token sau đó bấm “Lưu”. Khi đăng đơn, hệ thống ưu tiên cấu hình của kho này.</p>
                                <p><b>+ Hủy kết nối:</b></p>
                                <p>- Xóa tài khoản/API Token rồi bấm “Lưu”.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {Object.keys(form.errors).length > 0 && <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div>}
            </form>
        </DialogShell>
    );
}

export default function WarehouseIndex({ warehouses, filters = {}, managers = [], provinces = [], districts = [], shippingProviders = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [manager, setManager] = useState(filters.manager_user_id ?? '');
    const [province, setProvince] = useState(filters.province ?? '');
    const [district, setDistrict] = useState(filters.district ?? '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [shippingOpen, setShippingOpen] = useState(false);
    const [shippingWarehouse, setShippingWarehouse] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(shippingProviders[0]?.key ?? 'manual');
    const form = useForm(emptyWarehouse);
    const shippingForm = useForm({ default_shipping_provider: '', default_shipping_service: '', shipping_account_settings: {} });
    const rows = warehouses?.data ?? [];

    const providerOptions = shippingProviders.length ? shippingProviders : [{ key: 'manual', label: 'Thủ công', services: [{ code: 'manual', label: 'Giao hàng thủ công' }] }];

    const submitFilters = (event) => {
        event.preventDefault();
        router.get('/admin/warehouses', {
            search: search || null,
            manager_user_id: manager || null,
            province: province || null,
            district: district || null,
        }, { preserveState: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyWarehouse);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        setEditing(row.id);
        form.setData({
            name: row.name ?? '',
            code: row.code ?? '',
            sort_order: row.sort_order ?? '',
            phone: row.phone ?? '',
            pick_province: row.pick_province ?? '',
            pick_district: row.pick_district ?? '',
            pick_ward: row.pick_ward ?? '',
            address: row.address ?? '',
            use_two_level_address: Boolean(row.use_two_level_address),
            manager_user_id: row.manager_user_id ?? '',
            sender_registration_name: row.sender_registration_name ?? '',
            sender_print_note: row.sender_print_note ?? '',
            default_delivery_provinces: row.default_delivery_provinces ?? '',
            vtp_code: row.vtp_code ?? '',
            ghtk_pick_address_id: row.ghtk_pick_address_id ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/admin/warehouses/${editing}`, options); else form.post('/admin/warehouses', options);
    };

    const appendProvinces = (value) => {
        const current = form.data.default_delivery_provinces ? `${form.data.default_delivery_provinces}, ` : '';
        form.setData('default_delivery_provinces', `${current}${value}`);
    };

    const openShippingConfig = (row) => {
        const provider = row.default_shipping_provider || providerOptions[0]?.key || 'manual';
        setShippingWarehouse(row);
        setSelectedProvider(provider);
        shippingForm.setData({
            default_shipping_provider: provider,
            default_shipping_service: row.default_shipping_service ?? '',
            shipping_account_settings: row.shipping_account_settings ?? {},
        });
        shippingForm.clearErrors();
        setShippingOpen(true);
    };

    const setProviderField = (provider, key, value) => {
        const settings = { ...(shippingForm.data.shipping_account_settings ?? {}) };
        settings[provider] = { ...(settings[provider] ?? {}), [key]: value };
        shippingForm.setData('shipping_account_settings', settings);
    };

    const saveShipping = (event) => {
        event.preventDefault();
        if (!shippingWarehouse) return;
        shippingForm.put(`/admin/warehouses/${shippingWarehouse.id}/shipping-account`, {
            preserveScroll: true,
            onSuccess: () => setShippingOpen(false),
        });
    };

    return (
        <AppLayout>
            <Head title="Danh sách kho" />
            <section className="ps-adminlte-page ps-warehouse-page" data-page-code="5.2.1">
                <form onSubmit={submitFilters} className="m-header-wrap ps-warehouse-list-header-wrap">
                    <div className="m-header ps-warehouse-header">
                        <div className="ps-title">Danh sách kho</div>
                        <div className="ps-warehouse-filters">
                            <select className="form-control" value={province} onChange={(event) => { setProvince(event.target.value); setDistrict(''); }}>{optionNodes(provinces, '--Chọn Tỉnh/TP')}</select>
                            <select className="form-control" value={district} onChange={(event) => setDistrict(event.target.value)}>{optionNodes(districts, '--Quận/Huyện--')}</select>
                            <select className="form-control" value={manager} onChange={(event) => setManager(event.target.value)}>{optionNodes(managers, '--Quản kho--')}</select>
                            <input className="form-control" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Mã, tên, số điện thoại" />
                            <button className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <div className="box-body ps-toolbar ps-warehouse-toolbar">
                    <button type="button" className="btn btn-sm btn-primary" onClick={openCreate}><i className="fa fa-plus" /> Thêm</button>
                </div>

                <div className="ps-table-scroll ps-warehouse-table-wrap">
                    <table className="table table-bordered ps-source-table ps-warehouse-table">
                        <thead><tr><th>#</th><th>Tên kho</th><th>Số điện thoại</th><th>Tỉnh/TP</th><th>Quận/Huyện</th><th>Phường/Xã</th><th>Địa chỉ</th><th>Quản kho</th><th>Mã VTP</th><th>Mã GHN</th><th>Cập nhật</th><th className="ps-action-th" /></tr></thead>
                        <tbody>{rows.length ? rows.map((row) => <tr key={row.id}>
                            <td className="text-center">{row.id}</td>
                            <td className="text-center no-wrap"><strong>{row.name}</strong>{row.code && <small>({row.code})</small>}</td>
                            <td className="text-center no-wrap">{row.phone}</td>
                            <td className="text-center no-wrap">{row.pick_province}</td>
                            <td className="text-center no-wrap">{row.pick_district}</td>
                            <td className="text-center no-wrap">{row.pick_ward}</td>
                            <td className="text-center">{row.address}</td>
                            <td className="text-center no-wrap">{row.manager_name}</td>
                            <td className="text-center">{row.vtp_code}</td>
                            <td className="text-center">{row.ghtk_pick_address_id}</td>
                            <td className="text-center no-wrap">{row.updated_at}</td>
                            <td className="text-center no-wrap ps-row-actions ps-warehouse-actions">
                                <button type="button" title="Chỉnh sửa" onClick={() => openEdit(row)}><i className="fa fa-edit" /></button>
                                <button type="button" title="Cấu hình tài khoản giao hàng" onClick={() => openShippingConfig(row)}><i className="fa fa-bank" /></button>
                                <button type="button" title="Xóa" onClick={() => window.confirm(`Khi xóa kho các đơn liên quan đến kho sẽ được cập nhật thành không sử dụng kho, lịch sử liên quan đến kho này sẽ bị xóa, sản phẩm kho cũng sẽ bị xóa theo. Bạn có chắc chắn bạn muốn xóa?`) && router.delete(`/admin/warehouses/${row.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button>
                            </td>
                        </tr>) : <tr><td colSpan="12" className="ps-empty">Chưa có kho phù hợp.</td></tr>}</tbody>
                    </table>
                </div>
                <PushsalePagination meta={warehouses} routeUrl="/admin/warehouses" filters={currentFilters()} itemLabel="kho" />
            </section>

            <DialogShell open={open} onClose={() => setOpen(false)} title={editing ? 'Cập nhật kho' : 'Thêm mới kho'}>
                <WarehouseForm form={form} managers={managers} provinces={provinces} districts={districts} editing={Boolean(editing)} onSubmit={save} onAppendProvinces={appendProvinces} />
            </DialogShell>

            <ShippingAccountDialog
                open={shippingOpen}
                warehouse={shippingWarehouse}
                providers={providerOptions}
                form={shippingForm}
                selectedProvider={selectedProvider}
                onSelectProvider={setSelectedProvider}
                onClose={() => setShippingOpen(false)}
                onSubmit={saveShipping}
                setProviderField={setProviderField}
            />
        </AppLayout>
    );
}
