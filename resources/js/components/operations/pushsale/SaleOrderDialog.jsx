import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiGet } from '@/lib/api';
import { formatCurrency } from '@/lib/format';

const EMPTY = {
    marketing_source_id: '',
    warehouse_id: '',
    name: '',
    phone: '',
    message: '',
    address_mode: 'old',
    address_detail: '',
    province_code: '',
    district_code: '',
    ward_code: '',
    shipping_provider: '',
    shipping_service: '',
    shipping_method: 'Thủ công',
    shipping_notes: 'Cho xem hàng, không được thử, không bóc seal. Ko giao được liên hệ sale.',
    receiver_is_customer: true,
    receiver_name: '',
    receiver_phone: '',
    discount: 0,
    shipping_fee_collected: 0,
    deposit: 0,
    vat: 0,
    items: [],
};

const numberValue = (value) => Math.max(0, Number(String(value ?? 0).replace(/[^0-9.-]/g, '')) || 0);
const money = (value) => formatCurrency(numberValue(value));
const key = () => `${Date.now()}-${Math.random()}`;

function newLine(type = 'product') {
    return { key: key(), item_type: type, product_id: '', product_name: '', quantity: 1, unit_price: 0, discount_amount: 0 };
}

function mapOrder(order) {
    if (!order) return EMPTY;
    const geo = order.shippingGeo ?? {};
    return {
        ...EMPTY,
        marketing_source_id: order.marketingSourceId ?? '',
        warehouse_id: order.warehouseId ?? '',
        name: order.customerName ?? '',
        phone: order.customerPhone ?? '',
        message: order.customerNote ?? '',
        address_mode: order.addressMode ?? geo.mode ?? 'old',
        address_detail: geo.address_detail ?? geo.address ?? order.shippingAddress ?? '',
        province_code: geo.province_code ?? '',
        district_code: geo.district_code ?? '',
        ward_code: geo.ward_code ?? '',
        shipping_provider: order.shippingProvider ?? '',
        shipping_service: order.shippingService ?? '',
        shipping_method: order.shippingMethod ?? 'Thủ công',
        shipping_notes: order.shippingNotes ?? EMPTY.shipping_notes,
        receiver_is_customer: !order.hasDifferentReceiver,
        receiver_name: order.receiverName ?? '',
        receiver_phone: order.receiverPhone ?? '',
        discount: numberValue(order.discount),
        shipping_fee_collected: numberValue(order.shippingFeeCollected),
        deposit: numberValue(order.deposit),
        vat: numberValue(order.vat),
        items: (order.products ?? []).map((item) => ({
            key: key(),
            item_type: item.itemType ?? 'product',
            product_id: item.productId ?? '',
            product_name: item.productName ?? '',
            quantity: numberValue(item.quantity) || 1,
            unit_price: numberValue(item.unitPrice),
            discount_amount: numberValue(item.discountAmount),
        })),
    };
}

function FieldLabel({ children, required = false }) {
    return <label className="ps-order-field-label">{children}{required ? <span> (*)</span> : null}</label>;
}

function Select({ value, onChange, children, ...props }) {
    return <select className="form-control" value={value ?? ''} onChange={(event) => onChange(event.target.value)} {...props}>{children}</select>;
}

export function SaleOrderDialog({
    order = null,
    open,
    onOpenChange,
    closeIntent = false,
    manualUrl,
    actionBaseUrl,
    sourceOptions = [],
    warehouseOptions = [],
    productOptions = [],
    carrierOptions = [],
    shippingServiceOptions = {},
}) {
    const auth = usePage().props.auth;
    const [form, setForm] = useState(EMPTY);
    const [processing, setProcessing] = useState(false);
    const [provinces, setProvinces] = useState([]);
    const [districts, setDistricts] = useState([]);
    const [wards, setWards] = useState([]);

    useEffect(() => {
        if (open) setForm(mapOrder(order));
    }, [open, order]);

    useEffect(() => {
        if (!open) return;
        apiGet(`/geo/provinces?mode=${form.address_mode || 'old'}`).then(setProvinces).catch(() => setProvinces([]));
    }, [open, form.address_mode]);

    useEffect(() => {
        if (!form.province_code || form.address_mode === 'new') {
            setDistricts([]);
            return;
        }
        apiGet(`/geo/provinces/${form.province_code}/districts`).then(setDistricts).catch(() => setDistricts([]));
    }, [form.address_mode, form.province_code]);

    useEffect(() => {
        if (form.address_mode === 'new' && form.province_code) {
            apiGet(`/geo/provinces/${form.province_code}/wards`).then(setWards).catch(() => setWards([]));
            return;
        }
        if (form.district_code) {
            apiGet(`/geo/districts/${form.district_code}/wards`).then(setWards).catch(() => setWards([]));
        } else setWards([]);
    }, [form.address_mode, form.district_code, form.province_code]);

    const catalog = useMemo(() => ({
        products: productOptions.filter((item) => item.type !== 'combo'),
        combos: productOptions.filter((item) => item.type === 'combo'),
    }), [productOptions]);

    const subtotal = useMemo(() => form.items.reduce((sum, item) => (
        sum + numberValue(item.quantity) * numberValue(item.unit_price) - numberValue(item.discount_amount)
    ), 0), [form.items]);
    const lineDiscount = useMemo(() => form.items.reduce((sum, item) => sum + numberValue(item.discount_amount), 0), [form.items]);
    const total = Math.max(0, subtotal - numberValue(form.discount) + numberValue(form.vat) + numberValue(form.shipping_fee_collected));
    const collect = Math.max(0, total - numberValue(form.deposit));

    const update = (field, value) => setForm((current) => ({ ...current, [field]: value }));
    const updateLine = (lineKey, patch) => setForm((current) => ({ ...current, items: current.items.map((item) => item.key === lineKey ? { ...item, ...patch } : item) }));
    const removeLine = (lineKey) => setForm((current) => ({ ...current, items: current.items.filter((item) => item.key !== lineKey) }));

    const chooseProduct = (line, productId) => {
        const list = line.item_type === 'combo' ? catalog.combos : catalog.products;
        const product = list.find((item) => String(item.id) === String(productId));
        updateLine(line.key, {
            product_id: product?.id ?? '',
            product_name: product?.name ?? '',
            unit_price: numberValue(product?.unit_price),
        });
    };

    const appendCatalogItem = (type, productId) => {
        if (!productId) return;
        const list = type === 'combo' ? catalog.combos : catalog.products;
        const product = list.find((item) => String(item.id) === String(productId));
        if (!product) return;
        setForm((current) => ({
            ...current,
            items: [...current.items, {
                ...newLine(type),
                product_id: product.id,
                product_name: product.name,
                unit_price: numberValue(product.unit_price),
            }],
        }));
    };

    const payload = () => ({
        marketing_source_id: form.marketing_source_id ? Number(form.marketing_source_id) : null,
        name: form.name.trim(),
        phone: form.phone.trim(),
        message: form.message.trim(),
        address: form.address_detail.trim(),
        shipping_address: form.address_detail.trim(),
        customer_name: form.name.trim(),
        customer_phone: form.phone.trim(),
        customer_note: form.message.trim(),
        warehouse_id: form.warehouse_id ? Number(form.warehouse_id) : null,
        shipping_provider: form.shipping_provider || null,
        shipping_service: form.shipping_service || null,
        shipping_method: form.shipping_method || null,
        shipping_notes: form.shipping_notes || null,
        address_mode: form.address_mode,
        address_detail: form.address_detail.trim(),
        province_code: form.province_code || null,
        district_code: form.address_mode === 'new' ? null : (form.district_code || null),
        ward_code: form.ward_code || null,
        receiver_is_customer: Boolean(form.receiver_is_customer),
        receiver_name: form.receiver_is_customer ? null : form.receiver_name.trim(),
        receiver_phone: form.receiver_is_customer ? null : form.receiver_phone.trim(),
        discount: numberValue(form.discount),
        vat: numberValue(form.vat),
        shipping_fee_collected: numberValue(form.shipping_fee_collected),
        deposit: numberValue(form.deposit),
        items: form.items.filter((item) => item.product_id).map((item) => ({
            product_id: Number(item.product_id),
            product_name: item.product_name,
            item_type: item.item_type,
            quantity: Math.max(1, numberValue(item.quantity)),
            unit_price: numberValue(item.unit_price),
            discount_amount: numberValue(item.discount_amount),
        })),
    });

    const validate = () => {
        if (!form.marketing_source_id && !order) return 'Vui lòng chọn nguồn dữ liệu.';
        if (!form.name.trim()) return 'Vui lòng nhập họ tên khách hàng.';
        if (!form.phone.trim()) return 'Vui lòng nhập số điện thoại.';
        if (!form.items.some((item) => item.product_id)) return 'Vui lòng chọn ít nhất một sản phẩm hoặc combo.';
        return null;
    };

    const submit = (shouldClose) => {
        const error = validate();
        if (error) return toast.error(error);
        const data = payload();
        setProcessing(true);

        if (!order) {
            data.close_order = shouldClose;
            if (auth?.user?.role === 'sales') {
                data.allocation_mode = 'manual';
                data.sale_user_ids = [Number(auth.user.id)];
            }
            router.post(manualUrl, data, {
                preserveScroll: true,
                onSuccess: () => { toast.success(shouldClose ? 'Đã tạo và chốt đơn.' : 'Đã lưu đơn mới.'); onOpenChange(false); },
                onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể lưu đơn.'),
                onFinish: () => setProcessing(false),
            });
            return;
        }

        router.post(`${actionBaseUrl}/orders/${order.id}/details`, data, {
            preserveScroll: true,
            onSuccess: () => {
                if (!shouldClose) {
                    toast.success('Đã cập nhật đơn.');
                    onOpenChange(false);
                    setProcessing(false);
                    return;
                }
                router.post(`${actionBaseUrl}/orders/${order.id}/close`, {
                    warehouse_id: data.warehouse_id,
                    shipping_provider: data.shipping_provider,
                    shipping_method: data.shipping_method,
                    shipping_address: data.shipping_address,
                    amount_to_collect: collect,
                    confirm_insufficient_stock: false,
                }, {
                    preserveScroll: true,
                    onSuccess: () => { toast.success('Đã chốt đơn và sinh mã đơn.'); onOpenChange(false); },
                    onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Không thể chốt đơn.'),
                    onFinish: () => setProcessing(false),
                });
            },
            onError: (errors) => { toast.error(Object.values(errors)[0] ?? 'Không thể cập nhật đơn.'); setProcessing(false); },
        });
    };

    const selectedWarehouse = warehouseOptions.find((item) => String(item.id) === String(form.warehouse_id));
    const services = shippingServiceOptions?.[form.shipping_provider] ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-sale-dialog ps-sale-modal ps-sale-order-modal ps-sale-order-dialog" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>{order ? `${closeIntent ? 'Chốt đơn' : 'Cập nhật đơn'}: ${order.orderCode || 'Đơn chưa chốt'}` : 'Nhập đơn mới: --'}</DialogTitle></DialogHeader>
                <div className="ps-sale-order-body">
                    <section className="ps-order-left-panel">
                        <div className="ps-order-field ps-full"><FieldLabel required>Nguồn dữ liệu</FieldLabel><Select value={form.marketing_source_id} onChange={(value) => update('marketing_source_id', value)}><option value="">--Chọn nguồn dữ liệu--</option>{sourceOptions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel required>Họ tên khách hàng</FieldLabel><input className="form-control" value={form.name} onChange={(event) => update('name', event.target.value)} /></div>
                        <div className="ps-order-field"><FieldLabel required>Số điện thoại</FieldLabel><input className="form-control" value={form.phone} onChange={(event) => update('phone', event.target.value)} /></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Tin nhắn</FieldLabel><textarea className="form-control" rows={2} maxLength={1000} value={form.message} onChange={(event) => update('message', event.target.value)} /></div>
                        <div className="ps-order-field"><FieldLabel>Số nhà/đường/ngõ/ngách</FieldLabel><input className="form-control" maxLength={200} placeholder="Tìm kiếm (Tối đa 200 ký tự)" value={form.address_detail} onChange={(event) => update('address_detail', event.target.value)} /></div>
                        <div className="ps-order-field"><FieldLabel>Tỉnh/TP</FieldLabel><Select value={form.province_code} onChange={(value) => { update('province_code', value); update('district_code', ''); update('ward_code', ''); }}><option value="">--Chọn Tỉnh/TP--</option>{provinces.map((item) => <option key={item.code ?? item.id} value={item.code ?? item.id}>{item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>Quận/Huyện</FieldLabel><Select value={form.district_code} onChange={(value) => { update('district_code', value); update('ward_code', ''); }} disabled={form.address_mode === 'new'}><option value="">--Chọn Quận/Huyện--</option>{districts.map((item) => <option key={item.code ?? item.id} value={item.code ?? item.id}>{item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>Phường/Xã</FieldLabel><Select value={form.ward_code} onChange={(value) => update('ward_code', value)}><option value="">--Chọn Phường/Xã--</option>{wards.map((item) => <option key={item.code ?? item.id} value={item.code ?? item.id}>{item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>Phương thức giao hàng</FieldLabel><Select value={form.shipping_provider} onChange={(value) => { update('shipping_provider', value); update('shipping_service', ''); update('shipping_method', value || 'Thủ công'); }}><option value="">Thủ công</option>{carrierOptions.map((item) => <option key={item.value ?? item.id} value={item.value ?? item.id}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>Giao hàng bằng</FieldLabel><Select value={form.shipping_service} onChange={(value) => update('shipping_service', value)}><option value="">Giao hàng thủ công</option>{services.map((item) => <option key={item.value ?? item.code} value={item.value ?? item.code}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Mẫu giao hàng ghi chú</FieldLabel><Select value=""><option value="">--Mẫu ghi chú--</option></Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Giao hàng ghi chú</FieldLabel><textarea className="form-control" rows={2} value={form.shipping_notes} onChange={(event) => update('shipping_notes', event.target.value)} /></div>
                        <label className="ps-order-checkbox ps-full"><input type="checkbox" checked={form.receiver_is_customer} onChange={(event) => update('receiver_is_customer', event.target.checked)} /> Khách đặt hàng là người nhận hàng</label>
                        {!form.receiver_is_customer && <><div className="ps-order-field"><FieldLabel>Người nhận</FieldLabel><input className="form-control" value={form.receiver_name} onChange={(event) => update('receiver_name', event.target.value)} /></div><div className="ps-order-field"><FieldLabel>SĐT người nhận</FieldLabel><input className="form-control" value={form.receiver_phone} onChange={(event) => update('receiver_phone', event.target.value)} /></div></>}
                        <label className="ps-order-checkbox"><input type="checkbox" checked={form.address_mode === 'new'} onChange={(event) => update('address_mode', event.target.checked ? 'new' : 'old')} /> Sử dụng địa chỉ 2 cấp</label>
                        <label className="ps-order-checkbox"><input type="checkbox" /> Gợi ý chuyển địa chỉ</label>
                    </section>

                    <section className="ps-order-right-panel">
                        <div className="ps-order-top-grid">
                            <div><FieldLabel>Kho</FieldLabel><Select value={form.warehouse_id} onChange={(value) => update('warehouse_id', value)}><option value="">--Chọn kho--</option>{warehouseOptions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></div>
                            <div><FieldLabel>Địa chỉ lấy hàng</FieldLabel><div className="ps-static-line">{selectedWarehouse?.pickup_address ?? selectedWarehouse?.address ?? '—'}</div></div>
                            <div><Select value="" onChange={(value) => appendCatalogItem('product', value)}><option value="">--Chọn sản phẩm--</option>{catalog.products.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></div>
                            <div><Select value="" onChange={(value) => appendCatalogItem('combo', value)}><option value="">--Chọn combo--</option>{catalog.combos.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></div>
                        </div>
                        <div className="ps-vat-note">(v) Đã bao gồm VAT</div>
                        <div className="table-responsive">
                            <table className="table table-bordered ps-order-product-table">
                                <thead><tr><th>Tên/ Mã SP</th><th>Đơn giá</th><th>SL Tổng</th><th>Thành tiền</th><th>CK 1</th><th>Tiền CK (v)</th><th>CN(g)</th><th /></tr></thead>
                                <tbody>
                                    {form.items.map((line) => {
                                        const options = line.item_type === 'combo' ? catalog.combos : catalog.products;
                                        const lineTotal = numberValue(line.quantity) * numberValue(line.unit_price) - numberValue(line.discount_amount);
                                        return <tr key={line.key}>
                                            <td><Select value={line.product_id} onChange={(value) => chooseProduct(line, value)}><option value="">--Chọn {line.item_type === 'combo' ? 'combo' : 'sản phẩm'}--</option>{options.map((item) => <option key={item.id} value={item.id}>{item.name} {item.sku ? `(${item.sku})` : ''}</option>)}</Select></td>
                                            <td><input className="form-control text-right" type="number" min="0" value={line.unit_price} onChange={(event) => updateLine(line.key, { unit_price: event.target.value })} /></td>
                                            <td><input className="form-control text-center" type="number" min="1" value={line.quantity} onChange={(event) => updateLine(line.key, { quantity: event.target.value })} /></td>
                                            <td className="text-right"><b>{money(lineTotal)}</b></td>
                                            <td><input className="form-control text-right" type="number" min="0" value={line.discount_amount} onChange={(event) => updateLine(line.key, { discount_amount: event.target.value })} /></td>
                                            <td className="text-right">{money(line.discount_amount)}</td><td className="text-center">0</td>
                                            <td className="text-center"><button className="btn-icon text-danger" type="button" onClick={() => removeLine(line.key)}><i className="fa fa-trash" /></button></td>
                                        </tr>;
                                    })}
                                    {!form.items.length && <tr><td colSpan={8} className="text-center ps-empty-products">Chưa chọn sản phẩm</td></tr>}
                                </tbody>
                                <tfoot>
                                    <tr><th colSpan={2} className="text-right">Tổng cộng:</th><th>{form.items.reduce((sum, item) => sum + numberValue(item.quantity), 0)}</th><th className="text-right">{money(subtotal + lineDiscount)}</th><th /><th className="text-right">{money(lineDiscount)}</th><th /><th /></tr>
                                    <tr><td colSpan={3} className="text-right">Chiết khấu sản phẩm (v):</td><td className="text-right">{money(lineDiscount)}</td><td colSpan={4} /></tr>
                                    <tr><td><label><input type="checkbox" /> Tự nhập CK</label></td><td colSpan={2} className="text-right">Chiết khấu theo đơn (v):</td><td><input className="form-control text-right" type="number" value={form.discount} onChange={(event) => update('discount', event.target.value)} /></td><td colSpan={4} /></tr>
                                    <tr><td><label><input type="checkbox" /> Người nhận trả phí VC trực tiếp cho đơn vị VC</label></td><td colSpan={2} className="text-right">Phí VC thu của khách (v):</td><td><input className="form-control text-right" type="number" value={form.shipping_fee_collected} onChange={(event) => update('shipping_fee_collected', event.target.value)} /></td><td colSpan={2}>Phí VC tạm tính:<br /><span className="text-muted">0</span></td><td colSpan={2} /></tr>
                                    <tr><td><label><input type="checkbox" /> Tự nhập phí VC thu của khách</label></td><td colSpan={2} className="text-right">Khách đã đặt cọc:</td><td><input className="form-control text-right" type="number" value={form.deposit} onChange={(event) => update('deposit', event.target.value)} /></td><td colSpan={4} /></tr>
                                    <tr><th colSpan={3} className="text-right">Tổng tiền đơn:</th><th className="text-right">{money(total)}</th><th colSpan={2}>Phải thu của khách:<br /><span className="text-success">{money(collect)}</span></th><th colSpan={2} /></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div className="ps-order-actions">
                            <button type="button" className="btn btn-default"><i className="fa fa-calculator" /> Tính phí VC</button>
                            <button type="button" className="btn btn-default"><i className="fa fa-calendar-minus-o" /> Tính CK</button>
                            <button type="button" className="btn btn-default" disabled={processing} onClick={() => submit(false)}><i className="fa fa-floppy-o" /> Lưu đơn</button>
                            <button type="button" className="btn btn-default" onClick={() => setForm((current) => ({ ...current, items: [] }))}><i className="fa fa-cube" /> Làm mới SP</button>
                            <button type="button" className="btn btn-primary" disabled={processing || Boolean(order?.closedAt)} onClick={() => submit(true)}><i className="fa fa-calendar-check-o" /> Chốt đơn</button>
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
