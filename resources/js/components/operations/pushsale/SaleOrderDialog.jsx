import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOrderInteractionLock } from '@/hooks/useOrderInteractionLock';
import { apiGet } from '@/lib/api';
import { formatCurrency } from '@/lib/format';
import { normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';
import { useT } from '@/providers/I18nProvider';

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
    return {
        key: key(),
        item_type: type,
        base_product_id: '',
        product_id: '',
        product_name: '',
        product_sku: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
    };
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

function mapOrder(order, operationResult = null, productOptions = []) {
    if (!order) return { ...EMPTY, operation_result: operationResultValueForSelect(operationResult?.value) };
    const geo = order.shippingGeo ?? {};
    const byId = new Map(productOptions.map((item) => [String(item.id), item]));
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
        items: (order.products ?? []).map((item) => {
            const product = byId.get(String(item.productId ?? ''));
            const baseId = product?.parent_id ? String(product.parent_id) : String(item.productId ?? '');
            return {
                key: key(),
                item_type: item.itemType === 'combo' ? 'product' : (item.itemType ?? 'product'),
                base_product_id: baseId,
                product_id: item.productId ?? '',
                product_name: item.productName ?? '',
                product_sku: item.sku ?? product?.sku ?? '',
                quantity: item.quantity == null || item.quantity === '' ? 1 : numberValue(item.quantity),
                unit_price: numberValue(item.unitPrice),
                discount_amount: numberValue(item.discountAmount),
            };
        }),
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
                subLabel: item.subLabel ? String(item.subLabel) : undefined,
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
    const t = useT();
    const auth = usePage().props.auth;
    const isAdmin = auth?.user?.role === 'admin';
    const sourceLocked = Boolean(order) && !isAdmin;
    const unitPriceLocked = !isAdmin;
    const canUnclose = Boolean(order?.canUnclose);
    const orderClosed = Boolean(order?.closedAt);
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
    const [pickerBaseId, setPickerBaseId] = useState('');
    const [pickerSkuId, setPickerSkuId] = useState('');
    const ordersBase = `${String(actionBaseUrl || '/sales').replace(/\/$/, '')}/orders`;
    const { token: lockToken, error: lockError, ready: lockReady } = useOrderInteractionLock({
        orderId: order?.id,
        actionApiBase: ordersBase,
        action: closeIntent ? 'close' : (canUnclose && orderClosed ? 'unclose' : 'edit_order'),
        enabled: Boolean(open && order?.id),
    });

    useEffect(() => {
        if (open) {
            const nextForm = mapOrder(order, initialOperationResult, productOptions);
            setForm(nextForm);
            setOperationResultSeed(nextForm.operation_result || '');
            setFormError('');
            setManualDiscount(numberValue(nextForm.discount) > 0);
            setManualShippingFee(numberValue(nextForm.shipping_fee_collected) > 0);
            setRecipientPaysCarrier(false);
            setPickerBaseId('');
            setPickerSkuId('');
        }
    }, [open, order, initialOperationResult, productOptions]);

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

    const catalog = useMemo(() => {
        const products = productOptions.filter((item) => item.type !== 'combo');
        const byParent = new Map();
        products.forEach((item) => {
            if (!item.parent_id) return;
            const parentKey = String(item.parent_id);
            if (!byParent.has(parentKey)) byParent.set(parentKey, []);
            byParent.get(parentKey).push(item);
        });
        const bases = products.filter((item) => !item.parent_id);
        return { products, byParent, bases };
    }, [productOptions]);

    const baseProductSelectOptions = useMemo(() => toSelectOptions(catalog.bases.map((item) => ({
        id: item.id,
        name: item.name,
        subLabel: item.sku || undefined,
    }))), [catalog.bases]);

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

    const resolveLineFromProduct = (product) => {
        if (!product) {
            return {
                base_product_id: '',
                product_id: '',
                product_name: '',
                product_sku: '',
                unit_price: 0,
            };
        }
        const variants = catalog.byParent.get(String(product.id)) ?? [];
        if (!product.parent_id && variants.length > 0) {
            return {
                base_product_id: String(product.id),
                product_id: '',
                product_name: product.name,
                product_sku: product.sku || '',
                unit_price: numberValue(product.unit_price),
            };
        }
        const baseId = product.parent_id ? String(product.parent_id) : String(product.id);
        return {
            base_product_id: baseId,
            product_id: String(product.id),
            product_name: product.name,
            product_sku: product.sku || '',
            unit_price: numberValue(product.unit_price),
        };
    };

    const pickerVariants = useMemo(
        () => (pickerBaseId ? (catalog.byParent.get(String(pickerBaseId)) ?? []) : []),
        [pickerBaseId, catalog.byParent],
    );
    const pickerSkuOptions = useMemo(() => toSelectOptions(pickerVariants.map((item) => ({
        id: item.id,
        name: item.name,
        subLabel: item.sku || undefined,
    }))), [pickerVariants]);

    const lineProductLabel = (line) => {
        const product = catalog.products.find((item) => String(item.id) === String(line.product_id));
        const name = product?.name || line.product_name || '—';
        const sku = product?.sku || line.product_sku;
        return sku ? `${name} (${sku})` : name;
    };

    const appendResolvedLine = (resolved) => {
        if (!resolved.product_id && !resolved.base_product_id) return;
        setForm((current) => ({
            ...current,
            items: [...current.items, { ...newLine('product'), ...resolved }],
        }));
    };

    const onPickerBaseChange = (baseId) => {
        setPickerBaseId(baseId);
        setPickerSkuId('');
        setFormError('');
        if (!baseId) return;

        const variants = catalog.byParent.get(String(baseId)) ?? [];
        if (variants.length > 0) return;

        const product = catalog.products.find((item) => String(item.id) === String(baseId));
        if (!product) return;
        appendResolvedLine(resolveLineFromProduct(product));
        setPickerBaseId('');
    };

    const onPickerSkuChange = (skuId) => {
        setPickerSkuId(skuId);
        setFormError('');
        if (!skuId || !pickerBaseId) return;

        const product = catalog.products.find((item) => String(item.id) === String(skuId));
        if (!product) return;
        appendResolvedLine({
            ...resolveLineFromProduct(product),
            base_product_id: String(pickerBaseId),
        });
        setPickerBaseId('');
        setPickerSkuId('');
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
            item_type: item.item_type === 'combo' ? 'product' : item.item_type,
            quantity: Math.max(0, numberValue(item.quantity)),
            unit_price: numberValue(item.unit_price),
            discount_amount: numberValue(item.discount_amount),
        })),
    });

    const validate = () => {
        if (!form.marketing_source_id && !order) return t('operations.sale_order.source_required');
        if (!form.name.trim()) return t('operations.sale_order.name_required');
        const phoneError = vietnamesePhoneError(form.phone, { required: true });
        if (phoneError) return phoneError;
        if (!form.receiver_is_customer) {
            const receiverError = vietnamesePhoneError(form.receiver_phone, { required: true });
            if (receiverError) return receiverError;
        }
        if (form.items.some((item) => item.base_product_id && !item.product_id && (catalog.byParent.get(String(item.base_product_id)) ?? []).length > 0)) {
            return t('operations.sale_order.sku_required');
        }
        if (!form.items.some((item) => item.product_id)) return t('operations.sale_order.product_required');
        return null;
    };

    const firstError = (errors) => {
        const value = Object.values(errors ?? {})[0];
        if (Array.isArray(value)) return value[0];
        return value || t('operations.sale_order.save_failed');
    };

    const selectedOperationResult = operationStatusOptions.find((item) => String(item.value) === String(form.operation_result));
    const needsNextOperationAt = form.operation_result === 'callback_scheduled';

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
                toast.success(t('operations.sale_order.result_updated', {
                    label: selectedOperationResult?.label ? `: ${selectedOperationResult.label}` : '',
                }));
                onDone?.();
            },
            onError: (errors) => setFormError(firstError(errors)),
            onFinish: () => setProcessing(false),
        });
    };

    const submit = (shouldClose) => {
        if (orderClosed) {
            setFormError(t('operations.sale_order.closed_locked'));
            return;
        }
        const error = validate();
        if (error) {
            setFormError(error);
            return;
        }
        if (!shouldClose && form.operation_result === 'closed_success') {
            setFormError(t('operations.sale_order.close_via_button'));
            return;
        }
        if (!shouldClose && needsNextOperationAt && !form.next_operation_at) {
            setFormError(t('operations.sale_order.next_operation_required'));
            return;
        }
        setFormError('');
        const data = payload();
        if (order) {
            if (!lockToken) {
                setFormError(lockError || t('operations.sale_order.lock_missing'));
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
                onSuccess: () => {
                    toast.success(shouldClose ? t('operations.sale_order.created_closed') : t('operations.sale_order.created'));
                    onOpenChange(false);
                },
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
                        toast.success(t('operations.sale_order.updated'));
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
                    onSuccess: () => { toast.success(t('operations.sale_order.closed')); onOpenChange(false); },
                    onError: (errors) => setFormError(firstError(errors)),
                    onFinish: () => setProcessing(false),
                });
            },
            onError: (errors) => { setFormError(firstError(errors)); setProcessing(false); },
        });
    };

    const uncloseOrder = () => {
        if (!order?.id || !canUnclose) return;
        if (!lockToken) {
            setFormError(lockError || t('operations.sale_order.lock_missing'));
            return;
        }
        setProcessing(true);
        setFormError('');
        router.post(`${actionBaseUrl}/orders/${order.id}/unclose`, {
            interaction_lock_token: lockToken,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('operations.sale_order.unclose_success'));
                onOpenChange(false);
            },
            onError: (errors) => setFormError(firstError(errors)),
            onFinish: () => setProcessing(false),
        });
    };

    const calcOrderDiscount = () => {
        if (manualDiscount) {
            toast.info(t('operations.sale_order.manual_discount_on'));
            return;
        }
        update('discount', lineDiscount);
        toast.success(t('operations.sale_order.discount_applied'));
    };

    const calcShippingFee = () => {
        if (!order?.id) {
            toast.error(t('operations.sale_order.save_before_shipping_fee'));
            return;
        }
        if (!form.warehouse_id || !form.province_code) {
            toast.error(t('operations.sale_order.shipping_fee_need_address'));
            return;
        }
        toast.info(t('operations.sale_order.shipping_fee_hint'));
    };

    const refreshProducts = () => {
        setForm((current) => ({ ...current, items: [] }));
        toast.success(t('operations.sale_order.products_refreshed'));
    };

    const selectedWarehouse = warehouseOptions.find((item) => String(item.id) === String(form.warehouse_id));
    const services = shippingServiceOptions?.[form.shipping_provider] ?? [];
    const formDisabled = orderClosed && !canUnclose;
    const editDisabled = orderClosed || processing || (Boolean(order?.id) && !lockReady);

    const dialogTitle = order
        ? `${closeIntent ? t('operations.sale_order.title_close') : t('operations.sale_order.title_update')}: ${order.orderCode || t('operations.sale_order.uncoded')}`
        : t('operations.sale_order.title_new');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="ps-sale-dialog ps-sale-modal ps-sale-order-modal ps-sale-order-dialog"
                aria-describedby={undefined}
                style={{ minHeight: 'min(920px, calc(100dvh - 16px))', maxHeight: 'calc(100dvh - 8px)' }}
            >
                <DialogHeader className="ps-sale-dialog-header"><DialogTitle>{dialogTitle}</DialogTitle></DialogHeader>
                <div className="ps-sale-order-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
                    {orderClosed && canUnclose ? (
                        <div className="ps-dialog-form-error" role="status" style={{ background: '#fff8e6', color: '#8a6d3b', borderColor: '#faebcc' }}>
                            {t('operations.sale_order.unclose_hint')}
                        </div>
                    ) : null}
                    <section className={`ps-order-left-panel${formDisabled ? ' is-disabled' : ''}`}>
                        <div className="ps-order-field ps-full">
                            <FieldLabel required>{t('operations.sale_order.source')}</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={sourceSelectOptions}
                                value={form.marketing_source_id}
                                onChange={(value) => update('marketing_source_id', value)}
                                placeholder={t('operations.sale_order.source_placeholder')}
                                searchPlaceholder={t('operations.sale_order.source_search')}
                                disabled={sourceLocked || formDisabled}
                            />
                        </div>
                        {order && operationStatusOptions.length ? <div className="ps-order-field ps-full ps-order-result-field"><FieldLabel>{t('operations.sale_order.result')}</FieldLabel><Select value={form.operation_result} onChange={(value) => update('operation_result', value)} disabled={editDisabled}><option value="">--{t('operations.sale_order.choose_result')}--</option>{operationStatusOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></div> : null}
                        {order && needsNextOperationAt ? <div className="ps-order-field ps-full ps-order-next-operation-field"><FieldLabel required>{t('operations.sale_order.next_operation')}</FieldLabel><input className="form-control" type="datetime-local" value={form.next_operation_at} onChange={(event) => update('next_operation_at', event.target.value)} disabled={editDisabled} /></div> : null}
                        <div className="ps-order-field"><FieldLabel required>{t('operations.sale_order.customer_name')}</FieldLabel><input className="form-control" value={form.name} onChange={(event) => update('name', event.target.value)} disabled={editDisabled} /></div>
                        <div className="ps-order-field"><FieldLabel required>{t('operations.sale_order.customer_phone')}</FieldLabel><input className="form-control" value={form.phone} onChange={(event) => update('phone', event.target.value)} disabled={editDisabled} /></div>
                        <div className="ps-order-field ps-full"><FieldLabel>{t('operations.sale_order.message')}</FieldLabel><textarea className="form-control" rows={2} maxLength={1000} value={form.message} onChange={(event) => update('message', event.target.value)} disabled={editDisabled} /></div>
                        <div className="ps-order-field"><FieldLabel>{t('operations.sale_order.address_detail')}</FieldLabel><input className="form-control" maxLength={200} placeholder={t('operations.sale_order.address_detail_placeholder')} value={form.address_detail} onChange={(event) => update('address_detail', event.target.value)} disabled={editDisabled} /></div>
                        <div className="ps-order-field">
                            <FieldLabel>{t('operations.sale_order.province')}</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={provinceSelectOptions}
                                value={form.province_code}
                                onChange={(value) => { update('province_code', value); update('district_code', ''); update('ward_code', ''); }}
                                placeholder={t('operations.sale_order.province_placeholder')}
                                searchPlaceholder={t('operations.sale_order.province_search')}
                                disabled={editDisabled}
                            />
                        </div>
                        <div className="ps-order-field">
                            <FieldLabel>{t('operations.sale_order.district')}</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={districtSelectOptions}
                                value={form.district_code}
                                onChange={(value) => { update('district_code', value); update('ward_code', ''); }}
                                placeholder={t('operations.sale_order.district_placeholder')}
                                searchPlaceholder={t('operations.sale_order.district_search')}
                                disabled={form.address_mode === 'new' || editDisabled}
                            />
                        </div>
                        <div className="ps-order-field">
                            <FieldLabel>{t('operations.sale_order.ward')}</FieldLabel>
                            <PushsaleSelect
                                searchable
                                className="ps-order-search-select"
                                options={wardSelectOptions}
                                value={form.ward_code}
                                onChange={(value) => update('ward_code', value)}
                                placeholder={t('operations.sale_order.ward_placeholder')}
                                searchPlaceholder={t('operations.sale_order.ward_search')}
                                disabled={editDisabled}
                            />
                        </div>
                        <div className="ps-order-field"><FieldLabel>{t('operations.sale_order.shipping_method')}</FieldLabel><Select value={form.shipping_provider} onChange={(value) => { update('shipping_provider', value); update('shipping_service', ''); update('shipping_method', value || 'Thủ công'); }} disabled={editDisabled}><option value="">{t('operations.sale_order.manual_shipping')}</option>{carrierOptions.map((item) => <option key={item.value ?? item.id} value={item.value ?? item.id}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field"><FieldLabel>{t('operations.sale_order.ship_via')}</FieldLabel><Select value={form.shipping_service} onChange={(value) => update('shipping_service', value)} disabled={editDisabled}><option value="">{t('operations.sale_order.manual_carrier')}</option>{services.map((item) => <option key={item.value ?? item.code} value={item.value ?? item.code}>{item.label ?? item.name}</option>)}</Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>{t('operations.sale_order.shipping_note_template')}</FieldLabel><Select value="" disabled={editDisabled}><option value="">--{t('operations.sale_order.note_template')}--</option></Select></div>
                        <div className="ps-order-field ps-full"><FieldLabel>{t('operations.sale_order.shipping_notes')}</FieldLabel><textarea className="form-control" rows={2} value={form.shipping_notes} onChange={(event) => update('shipping_notes', event.target.value)} disabled={editDisabled} /></div>
                        <label className="ps-order-checkbox ps-full"><input type="checkbox" checked={form.receiver_is_customer} onChange={(event) => update('receiver_is_customer', event.target.checked)} disabled={editDisabled} /> {t('operations.sale_order.receiver_is_customer')}</label>
                        {!form.receiver_is_customer && <><div className="ps-order-field"><FieldLabel>{t('operations.sale_order.receiver_name')}</FieldLabel><input className="form-control" value={form.receiver_name} onChange={(event) => update('receiver_name', event.target.value)} disabled={editDisabled} /></div><div className="ps-order-field"><FieldLabel>{t('operations.sale_order.receiver_phone')}</FieldLabel><input className="form-control" value={form.receiver_phone} onChange={(event) => update('receiver_phone', event.target.value)} disabled={editDisabled} /></div></>}
                        <label className="ps-order-checkbox"><input type="checkbox" checked={form.address_mode === 'new'} onChange={(event) => update('address_mode', event.target.checked ? 'new' : 'old')} disabled={editDisabled} /> {t('operations.sale_order.address_2_level')}</label>
                        <label className="ps-order-checkbox"><input type="checkbox" checked={form.address_mode === 'new'} readOnly disabled title={t('operations.sale_order.address_suggest_title')} /> {t('operations.sale_order.address_suggest')}</label>
                    </section>

                    <section className="ps-order-right-panel">
                        <div className="ps-order-top-grid ps-order-product-picker">
                            <div><FieldLabel>{t('operations.sale_order.warehouse')}</FieldLabel><Select value={form.warehouse_id} onChange={(value) => update('warehouse_id', value)} disabled={editDisabled}><option value="">--{t('operations.sale_order.choose_warehouse')}--</option>{warehouseOptions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></div>
                            <div><FieldLabel>{t('operations.sale_order.pickup_address')}</FieldLabel><div className="ps-static-line">{selectedWarehouse?.pickup_address ?? selectedWarehouse?.address ?? '—'}</div></div>
                            <div>
                                <FieldLabel>{t('operations.sale_order.pick_base_product')}</FieldLabel>
                                <PushsaleSelect
                                    searchable
                                    className="ps-order-search-select"
                                    options={baseProductSelectOptions}
                                    value={pickerBaseId}
                                    onChange={onPickerBaseChange}
                                    placeholder={t('operations.sale_order.base_product_placeholder')}
                                    searchPlaceholder={t('operations.sale_order.product_search')}
                                    disabled={editDisabled}
                                />
                            </div>
                            <div>
                                <FieldLabel>{t('operations.sale_order.pick_variant_product')}</FieldLabel>
                                <PushsaleSelect
                                    searchable
                                    className="ps-order-search-select"
                                    options={pickerSkuOptions}
                                    value={pickerSkuId}
                                    onChange={onPickerSkuChange}
                                    placeholder={t('operations.sale_order.variant_placeholder')}
                                    searchPlaceholder={t('operations.sale_order.sku_search')}
                                    disabled={editDisabled || !pickerBaseId || pickerVariants.length === 0}
                                />
                            </div>
                        </div>
                        <div className="ps-vat-note">{t('operations.sale_order.vat_included')}</div>
                        <div className="table-responsive">
                            <table className="table table-bordered ps-order-product-table">
                                <thead><tr><th>{t('operations.sale_order.col_product')}</th><th>{t('operations.sale_order.col_price')}</th><th>{t('operations.sale_order.col_qty')}</th><th>{t('operations.sale_order.col_amount')}</th><th>{t('operations.sale_order.col_discount')}</th><th>{t('operations.sale_order.col_discount_money')}</th><th>{t('operations.sale_order.col_weight')}</th><th /></tr></thead>
                                <tbody>
                                    {form.items.map((line) => {
                                        const lineTotal = numberValue(line.quantity) * numberValue(line.unit_price) - numberValue(line.discount_amount);
                                        return <tr key={line.key}>
                                            <td><div className="ps-static-line ps-order-line-name" title={lineProductLabel(line)}>{lineProductLabel(line)}</div></td>
                                            <td className="text-right">
                                                {unitPriceLocked ? (
                                                    <div className="ps-static-line ps-order-unit-price-locked" title={t('operations.sale_order.price_locked_hint')}>{money(line.unit_price)}</div>
                                                ) : (
                                                    <input
                                                        className="form-control text-right"
                                                        type="number"
                                                        min="0"
                                                        value={line.unit_price}
                                                        onChange={(event) => updateLine(line.key, { unit_price: event.target.value })}
                                                        disabled={editDisabled}
                                                    />
                                                )}
                                            </td>
                                            <td><input className="form-control text-center" type="number" min="0" value={line.quantity} onChange={(event) => updateLine(line.key, { quantity: event.target.value })} disabled={editDisabled} /></td>
                                            <td className="text-right"><b>{money(lineTotal)}</b></td>
                                            <td><input className="form-control text-right" type="number" min="0" value={line.discount_amount} onChange={(event) => updateLine(line.key, { discount_amount: event.target.value })} disabled={editDisabled} /></td>
                                            <td className="text-right">{money(line.discount_amount)}</td><td className="text-center">0</td>
                                            <td className="text-center"><button className="btn-icon text-danger" type="button" onClick={() => removeLine(line.key)} disabled={editDisabled}><i className="fa fa-trash" /></button></td>
                                        </tr>;
                                    })}
                                    {!form.items.length && <tr><td colSpan={8} className="text-center ps-empty-products">{t('operations.sale_order.no_products')}</td></tr>}
                                </tbody>
                                <tfoot>
                                    <tr><th colSpan={2} className="text-right">{t('operations.sale_order.total_label')}</th><th>{form.items.reduce((sum, item) => sum + numberValue(item.quantity), 0)}</th><th className="text-right">{money(subtotal + lineDiscount)}</th><th /><th className="text-right">{money(lineDiscount)}</th><th /><th /></tr>
                                    <tr><td colSpan={3} className="text-right">{t('operations.sale_order.product_discount')}:</td><td className="text-right">{money(lineDiscount)}</td><td colSpan={4} /></tr>
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={manualDiscount} onChange={(event) => setManualDiscount(event.target.checked)} disabled={editDisabled} /> {t('operations.sale_order.manual_discount')}
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">{t('operations.sale_order.order_discount')}:</td>
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
                                                disabled={editDisabled}
                                            />
                                        </td>
                                        <td colSpan={4} />
                                    </tr>
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={recipientPaysCarrier} onChange={(event) => setRecipientPaysCarrier(event.target.checked)} disabled={editDisabled} /> {t('operations.sale_order.recipient_pays_carrier')}
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">{t('operations.sale_order.shipping_fee_collected')}:</td>
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
                                                disabled={editDisabled}
                                            />
                                        </td>
                                        <td colSpan={2}>{t('operations.sale_order.shipping_fee_estimate')}:<br /><span className="text-muted">{recipientPaysCarrier ? money(form.shipping_fee_collected) : '0'}</span></td>
                                        <td colSpan={2} />
                                    </tr>
                                    <tr>
                                        <td>
                                            <label>
                                                <input type="checkbox" checked={manualShippingFee} onChange={(event) => setManualShippingFee(event.target.checked)} disabled={editDisabled} /> {t('operations.sale_order.manual_shipping_fee')}
                                            </label>
                                        </td>
                                        <td colSpan={2} className="text-right">{t('operations.sale_order.deposit')}:</td>
                                        <td>
                                            <input
                                                className="form-control text-right"
                                                type="number"
                                                min="0"
                                                value={form.deposit}
                                                onChange={(event) => update('deposit', event.target.value)}
                                                disabled={editDisabled}
                                            />
                                        </td>
                                        <td colSpan={4} />
                                    </tr>
                                    <tr><th colSpan={3} className="text-right">{t('operations.sale_order.order_total')}:</th><th className="text-right">{money(total)}</th><th colSpan={2}>{t('operations.sale_order.collect')}:<br /><span className="text-success">{money(collect)}</span></th><th colSpan={2} /></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div className="ps-order-actions">
                            <button type="button" className="btn btn-default" onClick={calcShippingFee} disabled={editDisabled}><i className="fa fa-calculator" /> {t('operations.sale_order.calc_shipping')}</button>
                            <button type="button" className="btn btn-default" onClick={calcOrderDiscount} disabled={editDisabled}><i className="fa fa-calendar-minus-o" /> {t('operations.sale_order.calc_discount')}</button>
                            <button type="button" className="btn btn-default" disabled={editDisabled} onClick={() => submit(false)}><i className="fa fa-floppy-o" /> {t('operations.sale_order.save')}</button>
                            <button type="button" className="btn btn-default" onClick={refreshProducts} disabled={editDisabled}><i className="fa fa-cube" /> {t('operations.sale_order.refresh_products')}</button>
                            {canUnclose ? (
                                <button type="button" className="btn btn-warning" disabled={processing || !lockReady} onClick={uncloseOrder}>
                                    <i className="fa fa-undo" /> {t('operations.sale_order.unclose')}
                                </button>
                            ) : null}
                            <button type="button" className="btn btn-primary" disabled={editDisabled || orderClosed} onClick={() => submit(true)}><i className="fa fa-calendar-check-o" /> {t('operations.sale_order.close')}</button>
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
