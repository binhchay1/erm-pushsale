import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOrderInteractionLock } from '@/hooks/useOrderInteractionLock';
import { apiGet } from '@/lib/api';
import { formatCurrency } from '@/lib/format';
import { normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';

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
    shipping_notes: 'Cho xem hàng, không được thử, không bóc seal. Ko giao được liên hệ Shop. Khách hoàn đơn thu 30K ship, Ko tự ý hoàn, Ko GỌI SHOP SẼ BỒI HOÀN',
    receiver_is_customer: true,
    receiver_name: '',
    receiver_phone: '',
    discount: 0,
    shipping_fee_collected: 0,
    deposit: 0,
    vat: 0,
    operation_result: '',
    next_operation_at: '',
    items: [],
};

const numberValue = (value) => Math.max(0, Number(String(value ?? 0).replace(/[^0-9.-]/g, '')) || 0);
const money = (value) => formatCurrency(numberValue(value));
const key = () => `${Date.now()}-${Math.random()}`;

function newLine(type = 'product') {
    return { key: key(), item_type: type, product_id: '', product_name: '', quantity: 1, unit_price: 0, discount_amount: 0 };
}

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function operationResultValueForSelect(value) {
    const result = String(value ?? '');
    return /^no_answer_[1-6]$/.test(result) ? 'no_answer_auto' : result;
}

function mapOrder(order, operationResult = null) {
    if (!order) return { ...EMPTY, operation_result: operationResultValueForSelect(operationResult?.value) };
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
        operation_result: operationResultValueForSelect(operationResult?.value ?? order.operationResultValue),
        next_operation_at: toDateTimeLocal(order.nextOperationAt),
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

function toSelectOptions(items = []) {
    return items
        .map((item) => {
            const value = item.value ?? item.code ?? item.id;
            if (value === null || value === undefined || value === '') return null;
            return {
                value: String(value),
                label: String(item.label ?? item.name ?? value),
            };
        })
        .filter(Boolean);
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
    operationStatusOptions = [],
    initialOperationResult = null,
}) {
    const auth = usePage().props.auth;
    const [form, setForm] = useState(EMPTY);
    const [formError, setFormError] = useState('');
    const [processing, setProcessing] = useState(false);
    const [manualDiscount, setManualDiscount] = useState(false);
    const [manualShippingFee, setManualShippingFee] = useState(false);
    const [recipientPaysCarrier, setRecipientPaysCarrier] = useState(false);
    const [operationResultSeed, setOperationResultSeed] = useState('');
    const [provinces, setProvinces] = useState([]);
    const [districts, setDistricts] = useState([]);
    const [wards, setWards] = useState([]);
    const ordersBase = `${String(actionBaseUrl || '/sales').replace(/\/$/, '')}/orders`;
    const { token: lockToken, error: lockError, ready: lockReady } = useOrderInteractionLock({
        orderId: order?.id,
        actionApiBase: ordersBase,
        action: closeIntent ? 'close' : 'edit_order',
        enabled: Boolean(open && order?.id),
    });

    useEffect(() => {
        if (open) {
            const nextForm = mapOrder(order, initialOperationResult);
            setForm(nextForm);
            setOperationResultSeed(nextForm.operation_result || '');
            setFormError('');
            setManualDiscount(numberValue(nextForm.discount) > 0);
            setManualShippingFee(numberValue(nextForm.shipping_fee_collected) > 0);
            setRecipientPaysCarrier(false);
        }
    }, [open, order, initialOperationResult]);

    useEffect(() => {
        if (lockError) {
            toast.error(lockError);
            onOpenChange(false);
        }
    }, [lockError, onOpenChange]);

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
    const sourceSelectOptions = useMemo(() => toSelectOptions(sourceOptions), [sourceOptions]);
    const provinceSelectOptions = useMemo(() => toSelectOptions(provinces), [provinces]);
    const districtSelectOptions = useMemo(() => toSelectOptions(districts), [districts]);
    const wardSelectOptions = useMemo(() => toSelectOptions(wards), [wards]);

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
        phone: normalizeVietnamesePhone(form.phone) ?? form.phone.trim(),
        message: form.message.trim(),
        address: form.address_detail.trim(),
        shipping_address: form.address_detail.trim(),
        customer_name: form.name.trim(),
        customer_phone: normalizeVietnamesePhone(form.phone) ?? form.phone.trim(),
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
        receiver_phone: form.receiver_is_customer
            ? null
            : (normalizeVietnamesePhone(form.receiver_phone) ?? form.receiver_phone.trim()),
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
        const phoneError = vietnamesePhoneError(form.phone, { required: true });
        if (phoneError) return phoneError;
        if (!form.receiver_is_customer) {
            const receiverError = vietnamesePhoneError(form.receiver_phone, { required: true });
            if (receiverError) return receiverError;
        }
        if (!form.items.some((item) => item.product_id)) return 'Vui lòng chọn ít nhất một sản phẩm hoặc combo.';
        return null;
    };

    const firstError = (errors) => {
        const value = Object.values(errors ?? {})[0];
        if (Array.isArray(value)) return value[0];
        return value || 'Không thể lưu đơn.';
    };

    const shouldSyncOperationResult = () => Boolean(
        order?.id
        && form.operation_result
        && form.operation_result !== 'closed_success'
        && (form.operation_result !== operationResultSeed || Boolean(initialOperationResult?.value))
    );

    const syncOperationStatus = (onDone) => {
        if (!shouldSyncOperationResult()) {
            onDone?.();
            return;
        }
        router.post(`${ordersBase}/${order.id}/operation-status`, {
            operation_result: form.operation_result,
            next_operation_at: needsNextOperationAt ? form.next_operation_at : null,
            note: '',
            interaction_lock_token: lockToken,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã cập nhật kết quả${selectedOperationResult?.label ? `: ${selectedOperationResult.label}` : ' tác nghiệp'}.`);
                onDone?.();
            },
            onError: (errors) => setFormError(firstError(errors)),
            onFinish: () => setProcessing(false),
        });
    };

    const submit = (shouldClose) => {
        const error = validate();
        if (error) {
            setFormError(error);
            return;
        }
        if (!shouldClose && form.operation_result === 'closed_success') {
            setFormError('Kết quả chốt đơn thành công cần bấm nút Chốt đơn để sinh mã đơn.');
            return;
        }
        if (!shouldClose && needsNextOperationAt && !form.next_operation_at) {
            setFormError('Vui lòng chọn thời gian tác nghiệp tiếp.');
            return;
        }
        setFormError('');
        const data = payload();
        if (order) {
            if (!lockToken) {
                setFormError(lockError || 'Chưa lấy được quyền thao tác đơn.');
                return;
            }
            data.interaction_lock_token = lockToken;
        }
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
                onError: (errors) => setFormError(firstError(errors)),
                onFinish: () => setProcessing(false),
            });
            return;
        }

        router.post(`${actionBaseUrl}/orders/${order.id}/details`, data, {
            preserveScroll: true,
            onSuccess: () => {
                if (!shouldClose) {
                    syncOperationStatus(() => {
                        toast.success('Đã cập nhật đơn.');
                        onOpenChange(false);
                        setProcessing(false);
                    });
                    return;
                }
                router.post(`${actionBaseUrl}/orders/${order.id}/close`, {
                    warehouse_id: data.warehouse_id,
                    shipping_provider: data.shipping_provider,
                    shipping_method: data.shipping_method,
                    shipping_address: data.shipping_address,
                    amount_to_collect: collect,
                    confirm_insufficient_stock: false,
                    interaction_lock_token: lockToken,
                }, {
                    preserveScroll: true,
                    onSuccess: () => { toast.success('Đã chốt đơn và sinh mã đơn.'); onOpenChange(false); },
                    onError: (errors) => setFormError(firstError(errors)),
                    onFinish: () => setProcessing(false),
                });
            },
            onError: (errors) => { setFormError(firstError(errors)); setProcessing(false); },
        });
    };

    const calcOrderDiscount = () => {
        if (manualDiscount) {
            toast.info('Đang bật tự nhập chiết khấu đơn.');
            return;
        }
        update('discount', lineDiscount);
        toast.success('Đã tính chiết khấu theo sản phẩm.');
    };

    const calcShippingFee = () => {
        if (!order?.id) {
            toast.error('Vui lòng lưu đơn trước khi tính phí vận chuyển.');
            return;
        }
        if (!form.warehouse_id || !form.province_code) {
            toast.error('Chọn kho và địa chỉ giao hàng trước khi tính phí VC.');
            return;
        }
        toast.info('Phí VC tạm tính: nhập thủ công hoặc chốt đơn qua kho để tính theo đơn vị vận chuyển.');
    };

    const refreshProducts = () => {
        setForm((current) => ({ ...current, items: [] }));
        toast.success('Đã làm mới danh sách sản phẩm.');
    };

    const selectedWarehouse = warehouseOptions.find((item) => String(item.id) === String(form.warehouse_id));
    const services = shippingServiceOptions?.[form.shipping_provider] ?? [];
    const selectedOperationResult = operationStatusOptions.find((item) => String(item.value) === String(form.operation_result));
    const needsNextOperationAt = form.operation_result === 'callback_scheduled';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="ps-sale-dialog ps-sale-modal ps-sale-order-modal ps-sale-order-dialog"
                aria-describedby={undefined}
                style={{ minHeight: 'min(920px, calc(100dvh - 16px))', maxHeight: 'calc(100dvh - 8px)' }}
            >
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>{order ? `${closeIntent ? 'Chốt đơn' : 'Cập nhật đơn'}: ${order.orderCode || 'Đơn chưa chốt'}` : 'Nhập đơn mới: --'}</DialogTitle></DialogHeader>
                <div className="ps-sale-order-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
                    <section className="ps-order-left-panel">
                        <div className="ps-order-field ps-full">
                            <FieldLabel required>Nguồn dữ liệu</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={sourceSelectOptions}
                                value={form.marketing_source_id}
                                onChange={(value) => update('marketing_source_id', value)}
                                placeholder="--Chọn nguồn dữ liệu--"
                                searchPlaceholder="Gõ tên nguồn để tìm..."
                            />
                        </div>
                        {order && operationStatusOptions.length ? <div className="ps-order-field ps-full ps-order-result-field"><FieldLabel>Kết quả</FieldLabel><Select value={form.operation_result} onChange={(value) => update('operation_result', value)}><option value="">--Chọn kết quả--</option>{operationStatusOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></div> : null}
                        {order && needsNextOperationAt ? <div className="ps-order-field ps-full ps-order-next-operation-field"><FieldLabel required>Tác nghiệp tiếp</FieldLabel><input className="form-control" type="datetime-local" value={form.next_operation_at} onChange={(event) => update('next_operation_at', event.target.value)} /></div> : null}
                        <div className="ps-order-field"><FieldLabel required>Họ tên khách hàng</FieldLabel><input className="form-control" value={form.name} onChange={(event) => update('name', event.target.value)} /></div>
                        <div className="ps-order-field"><FieldLabel required>Số điện thoại</FieldLabel><input className="form-control" value={form.phone} onChange={(event) => update('phone', event.target.value)} /></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Tin nhắn</FieldLabel><textarea className="form-control" rows={2} maxLength={1000} value={form.message} onChange={(event) => update('message', event.target.value)} /></div>
                        <div className="ps-order-field"><FieldLabel>Số nhà/đường/ngõ/ngách</FieldLabel><input className="form-control" maxLength={200} placeholder="Tìm kiếm (Tối đa 200 ký tự)" value={form.address_detail} onChange={(event) => update('address_detail', event.target.value)} /></div>
                        <div className="ps-order-field">
                            <FieldLabel>Tỉnh/TP</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={provinceSelectOptions}
                                value={form.province_code}
                                onChange={(value) => { update('province_code', value); update('district_code', ''); update('ward_code', ''); }}
                                placeholder="--Chọn Tỉnh/TP--"
                                searchPlaceholder="Gõ tỉnh/thành để tìm..."
                            />
                        </div>
                        <div className="ps-order-field">
                            <FieldLabel>Quận/Huyện</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={districtSelectOptions}
                                value={form.district_code}
                                onChange={(value) => { update('district_code', value); update('ward_code', ''); }}
                                placeholder="--Chọn Quận/Huyện--"
                                searchPlaceholder="Gõ quận/huyện để tìm..."
                                disabled={form.address_mode === 'new'}
                            />
                        </div>
                        <div className="ps-order-field">
                            <FieldLabel>Phường/Xã</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={wardSelectOptions}
                                value={form.ward_code}
                                onChange={(value) => update('ward_code', value)}
                                placeholder="--Chọn Phường/Xã--"
                                searchPlaceholder="Gõ phường/xã để tìm..."
                            />
                        </div>
                        <div className="ps-order-field"><FieldLabel>Phương thức giao hàng</FieldLabel><Select value={form.shipping_provider} onChange={(value) => { update('shipping_provider', value); update('shipping_service', ''); update('shipping_method', value || 'Thủ công'); }}><option value="">Thủ công</option>{carrierOptions.map((item) => <option key={item.value ?? item.id} value={item.value ?? item.id}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>Giao hàng bằng</FieldLabel><Select value={form.shipping_service} onChange={(value) => update('shipping_service', value)}><option value="">Giao hàng thủ công</option>{services.map((item) => <option key={item.value ?? item.code} value={item.value ?? item.code}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Mẫu giao hàng ghi chú</FieldLabel><Select value=""><option value="">--Mẫu ghi chú--</option></Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>Giao hàng ghi chú</FieldLabel><textarea className="form-control" rows={2} value={form.shipping_notes} onChange={(event) => update('shipping_notes', event.target.value)} /></div>
                        <label className="ps-order-checkbox ps-full"><input type="checkbox" checked={form.receiver_is_customer} onChange={(event) => update('receiver_is_customer', event.target.checked)} /> Khách đặt hàng là người nhận hàng</label>
                        {!form.receiver_is_customer && <><div className="ps-order-field"><FieldLabel>Người nhận</FieldLabel><input className="form-control" value={form.receiver_name} onChange={(event) => update('receiver_name', event.target.value)} /></div><div className="ps-order-field"><FieldLabel>SĐT người nhận</FieldLabel><input className="form-control" value={form.receiver_phone} onChange={(event) => update('receiver_phone', event.target.value)} /></div></>}
                        <label className="ps-order-checkbox"><input type="checkbox" checked={form.address_mode === 'new'} onChange={(event) => update('address_mode', event.target.checked ? 'new' : 'old')} /> Sử dụng địa chỉ 2 cấp</label>
                        <label className="ps-order-checkbox"><input type="checkbox" checked={form.address_mode === 'new'} readOnly disabled title="Bật địa chỉ 2 cấp để dùng gợi ý chuyển đổi" /> Gợi ý chuyển địa chỉ</label>
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
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={manualDiscount} onChange={(event) => setManualDiscount(event.target.checked)} /> Tự nhập CK
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">Chiết khấu theo đơn (v):</td>
                                        <td>
                                            <input
                                                className="form-control text-right"
                                                type="number"
                                                min="0"
                                                value={form.discount}
                                                onChange={(event) => {
                                                    setManualDiscount(true);
                                                    update('discount', event.target.value);
                                                }}
                                            />
                                        </td>
                                        <td colSpan={4} />
                                    </tr>
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={recipientPaysCarrier} onChange={(event) => setRecipientPaysCarrier(event.target.checked)} /> Người nhận trả phí VC trực tiếp cho đơn vị VC
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">Phí VC thu của khách (v):</td>
                                        <td>
                                            <input
                                                className="form-control text-right"
                                                type="number"
                                                min="0"
                                                value={form.shipping_fee_collected}
                                                onChange={(event) => {
                                                    setManualShippingFee(true);
                                                    update('shipping_fee_collected', event.target.value);
                                                }}
                                            />
                                        </td>
                                        <td colSpan={2}>Phí VC tạm tính:<br /><span className="text-muted">{recipientPaysCarrier ? money(form.shipping_fee_collected) : '0'}</span></td>
                                        <td colSpan={2} />
                                    </tr>
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={manualShippingFee} onChange={(event) => setManualShippingFee(event.target.checked)} /> Tự nhập phí VC thu của khách
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">Khách đã đặt cọc:</td>
                                        <td>
                                            <input
                                                className="form-control text-right"
                                                type="number"
                                                min="0"
                                                value={form.deposit}
                                                onChange={(event) => update('deposit', event.target.value)}
                                            />
                                        </td>
                                        <td colSpan={4} />
                                    </tr>
                                    <tr><th colSpan={3} className="text-right">Tổng tiền đơn:</th><th className="text-right">{money(total)}</th><th colSpan={2}>Phải thu của khách:<br /><span className="text-success">{money(collect)}</span></th><th colSpan={2} /></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div className="ps-order-actions">
                            <button type="button" className="btn btn-default" onClick={calcShippingFee}><i className="fa fa-calculator" /> Tính phí VC</button>
                            <button type="button" className="btn btn-default" onClick={calcOrderDiscount}><i className="fa fa-calendar-minus-o" /> Tính CK</button>
                            <button type="button" className="btn btn-default" disabled={processing} onClick={() => submit(false)}><i className="fa fa-floppy-o" /> Lưu đơn</button>
                            <button type="button" className="btn btn-default" onClick={refreshProducts}><i className="fa fa-cube" /> Làm mới SP</button>
                            <button type="button" className="btn btn-primary" disabled={processing || Boolean(order?.closedAt)} onClick={() => submit(true)}><i className="fa fa-calendar-check-o" /> Chốt đơn</button>
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
