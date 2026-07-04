import { router } from '@inertiajs/react';
import { Loader2, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function StockWarningBlock({ warnings = [] }) {
    const t = useT();
    const insufficient = warnings.filter((w) => !w.sufficient);

    if (!insufficient.length) {
        return null;
    }

    return (
        <div className="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
            <p className="font-semibold">{t('operations.insufficient_stock')}</p>
            <ul className="mt-2 list-inside list-disc text-xs">
                {insufficient.map((w) => (
                    <li key={w.productId}>
                        {t('operations.stock_line', {
                            name: w.productName,
                            required: formatNumber(w.required),
                            available: formatNumber(w.available),
                        })}
                    </li>
                ))}
            </ul>
        </div>
    );
}

async function fetchJson(url) {
    try {
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        return r.ok ? await r.json() : [];
    } catch {
        return [];
    }
}

// Cache danh sách tỉnh theo từng chế độ (old/new) để không gọi lại nhiều lần.
const provincesCache = {};

function fetchProvinces(mode) {
    const key = mode === 'new' ? 'new' : 'old';
    if (!provincesCache[key]) {
        provincesCache[key] = fetchJson(key === 'new' ? '/geo/provinces?mode=new' : '/geo/provinces');
    }
    return provincesCache[key];
}

function mapInitialItems(order) {
    return (order.products ?? []).map((p) => ({
        productId: p.productId ?? null,
        productName: p.productName ?? '',
        itemType: p.itemType ?? 'product',
        quantity: Number(p.quantity ?? 1) || 1,
        unitPrice: Number(p.unitPrice ?? 0) || 0,
        discountAmount: Number(p.discountAmount ?? 0) || 0,
    }));
}

export function OperationStatusDialog({
    order,
    options = [],
    carrierOptions = [],
    shippingServiceOptions = {},
    warehouseOptions = [],
    productOptions = [],
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState('status');
    const [processing, setProcessing] = useState(false);

    // Status tab state
    const [result, setResult] = useState('');
    const [nextAt, setNextAt] = useState('');
    const [note, setNote] = useState('');
    const [confirmInsufficient, setConfirmInsufficient] = useState(false);

    // Order tab state
    const [items, setItems] = useState(() => mapInitialItems(order));
    const [orderDiscount, setOrderDiscount] = useState(Number(order.discount ?? 0) || 0);
    const [carrier, setCarrier] = useState(order.shippingProvider ?? '');
    const [customerName, setCustomerName] = useState(order.customerName ?? '');
    const [customerPhone, setCustomerPhone] = useState(order.customerPhone ?? '');
    const [shippingAddress, setShippingAddress] = useState(order.shippingAddress ?? '');
    const [customerNote, setCustomerNote] = useState(order.customerNote ?? '');
    const [warehouseId, setWarehouseId] = useState(order.warehouseId ?? '');
    const [shippingFee, setShippingFee] = useState(Number(order.shippingFeeCollected ?? 0) || 0);
    const [deposit, setDeposit] = useState(Number(order.deposit ?? 0) || 0);

    // Địa chỉ giao đã xác nhận (Tỉnh/Huyện/Xã + số nhà) + dịch vụ vận chuyển.
    const initialGeo = order.shippingGeo ?? {};
    const [addressModeNew, setAddressModeNew] = useState((order.addressMode ?? 'old') === 'new');
    const [receiverIsCustomer, setReceiverIsCustomer] = useState(!order.receiverName && !order.receiverPhone);
    const [receiverName, setReceiverName] = useState(order.receiverName ?? '');
    const [receiverPhone, setReceiverPhone] = useState(order.receiverPhone ?? '');
    const [shippingService, setShippingService] = useState(order.shippingService ?? '');
    const [addressDetail, setAddressDetail] = useState(initialGeo.address_detail ?? '');
    const [provinceCode, setProvinceCode] = useState(initialGeo.province_code ? String(initialGeo.province_code) : '');
    const [districtCode, setDistrictCode] = useState(initialGeo.district_code ? String(initialGeo.district_code) : '');
    const [wardCode, setWardCode] = useState(initialGeo.ward_code ? String(initialGeo.ward_code) : '');
    const [provinces, setProvinces] = useState([]);
    const [districts, setDistricts] = useState([]);
    const [wards, setWards] = useState([]);

    const serviceList = shippingServiceOptions?.[carrier] ?? [];
    const provinceOpts = useMemo(() => provinces.map((p) => ({ value: String(p.code), label: p.name })), [provinces]);
    const districtOpts = useMemo(() => districts.map((d) => ({ value: String(d.code), label: d.name })), [districts]);
    const wardOpts = useMemo(() => wards.map((w) => ({ value: String(w.code), label: w.name })), [wards]);

    // Tỉnh/TP theo chế độ (đơn vị cũ hoặc đơn vị 2 cấp 2025).
    useEffect(() => {
        if (open && tab === 'order') {
            fetchProvinces(addressModeNew ? 'new' : 'old').then(setProvinces);
        }
    }, [open, tab, addressModeNew]);

    // Quận/Huyện chỉ tồn tại ở đơn vị cũ.
    useEffect(() => {
        if (addressModeNew || !provinceCode) {
            setDistricts([]);
            return;
        }
        fetchJson(`/geo/provinces/${provinceCode}/districts`).then(setDistricts);
    }, [provinceCode, addressModeNew]);

    // Phường/Xã: đơn vị mới trực thuộc Tỉnh; đơn vị cũ trực thuộc Quận/Huyện.
    useEffect(() => {
        if (addressModeNew) {
            if (!provinceCode) {
                setWards([]);
                return;
            }
            fetchJson(`/geo/provinces/${provinceCode}/wards`).then(setWards);
            return;
        }
        if (!districtCode) {
            setWards([]);
            return;
        }
        fetchJson(`/geo/districts/${districtCode}/wards`).then(setWards);
    }, [provinceCode, districtCode, addressModeNew]);

    const catalog = useMemo(() => {
        const products = [];
        const combos = [];
        (productOptions ?? []).forEach((p) => {
            (p.type === 'combo' ? combos : products).push(p);
        });
        return { products, combos };
    }, [productOptions]);

    const optionsForType = (itemType) => (itemType === 'combo' ? catalog.combos : catalog.products);

    const quickNoAnswer = useMemo(
        () => ({
            value: 'no_answer_auto',
            label: t('operations.status_dialog.no_answer'),
            group: t('operations.status_dialog.no_answer_group'),
        }),
        [t],
    );

    const groupedOptions = useMemo(() => {
        const all = [quickNoAnswer, ...options];
        const groups = {};
        all.forEach((item) => {
            groups[item.group] = groups[item.group] ?? [];
            groups[item.group].push(item);
        });
        return groups;
    }, [options, quickNoAnswer]);

    const subtotal = useMemo(
        () => items.reduce((sum, it) => sum + (Number(it.quantity) || 0) * (Number(it.unitPrice) || 0), 0),
        [items],
    );
    const itemsDiscount = useMemo(
        () => items.reduce((sum, it) => sum + (Number(it.discountAmount) || 0), 0),
        [items],
    );
    const finalTotal = Math.max(0, subtotal - itemsDiscount - (Number(orderDiscount) || 0));
    const amountToCollect = Math.max(0, finalTotal + (Number(shippingFee) || 0) - (Number(deposit) || 0));

    if (!order?.canChangeStatus) {
        return null;
    }

    const needsSchedule = result === 'callback_scheduled';
    const isClosing = result === 'closed_success';
    const showStockWarning = isClosing && order.hasInsufficientStock;

    const resetAndClose = () => {
        setOpen(false);
        setTab('status');
        setResult('');
        setNextAt('');
        setNote('');
        setConfirmInsufficient(false);
        setItems(mapInitialItems(order));
        setOrderDiscount(Number(order.discount ?? 0) || 0);
        setCarrier(order.shippingProvider ?? '');
        setCustomerName(order.customerName ?? '');
        setCustomerPhone(order.customerPhone ?? '');
        setShippingAddress(order.shippingAddress ?? '');
        setCustomerNote(order.customerNote ?? '');
        setWarehouseId(order.warehouseId ?? '');
        setShippingFee(Number(order.shippingFeeCollected ?? 0) || 0);
        setDeposit(Number(order.deposit ?? 0) || 0);
        setShippingService(order.shippingService ?? '');
        setAddressModeNew((order.addressMode ?? 'old') === 'new');
        setReceiverIsCustomer(!order.receiverName && !order.receiverPhone);
        setReceiverName(order.receiverName ?? '');
        setReceiverPhone(order.receiverPhone ?? '');
        setAddressDetail(initialGeo.address_detail ?? '');
        setProvinceCode(initialGeo.province_code ? String(initialGeo.province_code) : '');
        setDistrictCode(initialGeo.district_code ? String(initialGeo.district_code) : '');
        setWardCode(initialGeo.ward_code ? String(initialGeo.ward_code) : '');
    };

    const submitStatus = () => {
        if (!result) {
            toast.error(t('operations.status_dialog.select_result'));
            return;
        }
        if (needsSchedule && !nextAt) {
            toast.error(t('operations.status_dialog.select_schedule'));
            return;
        }
        if (isClosing && order.hasInsufficientStock && !confirmInsufficient) {
            setConfirmInsufficient(true);
            return;
        }

        setProcessing(true);
        router.post(
            `/sales/orders/${order.id}/operation-status`,
            {
                operation_result: result,
                next_operation_at: needsSchedule ? nextAt : null,
                note: note || null,
                confirm_insufficient_stock: confirmInsufficient,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetAndClose();
                    toast.success(t('operations.status_dialog.update_success'));
                },
                onError: (errors) => {
                    if (errors.insufficient_stock || errors.stock) {
                        setConfirmInsufficient(true);
                        toast.error(t('operations.close_insufficient_error'));
                        return;
                    }
                    toast.error(
                        errors.operation_result ??
                            errors.next_operation_at ??
                            errors.order ??
                            t('operations.status_dialog.update_failed'),
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const submitOrder = () => {
        if (items.some((it) => !it.productId)) {
            toast.error(t('operations.order_edit.select_product_required'));
            return;
        }

        setProcessing(true);
        router.post(
            `/sales/orders/${order.id}/details`,
            {
                items: items.map((it) => ({
                    product_id: it.productId ? Number(it.productId) : null,
                    product_name: it.productName.trim(),
                    item_type: it.itemType,
                    quantity: Number(it.quantity) || 1,
                    unit_price: Number(it.unitPrice) || 0,
                    discount_amount: Number(it.discountAmount) || 0,
                })),
                discount: Number(orderDiscount) || 0,
                shipping_provider: carrier || null,
                shipping_service: shippingService || null,
                customer_name: customerName.trim() || null,
                customer_phone: customerPhone.trim() || null,
                shipping_address: shippingAddress.trim() || null,
                address_mode: addressModeNew ? 'new' : 'old',
                address_detail: addressDetail.trim() || null,
                province_code: provinceCode || null,
                district_code: addressModeNew ? null : districtCode || null,
                ward_code: wardCode || null,
                receiver_is_customer: receiverIsCustomer,
                receiver_name: receiverIsCustomer ? null : receiverName.trim() || null,
                receiver_phone: receiverIsCustomer ? null : receiverPhone.trim() || null,
                customer_note: customerNote.trim() || null,
                warehouse_id: warehouseId ? Number(warehouseId) : null,
                shipping_fee_collected: Number(shippingFee) || 0,
                deposit: Number(deposit) || 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetAndClose();
                    toast.success(t('operations.order_edit.save_success'));
                },
                onError: (errors) => {
                    const first = Object.values(errors ?? {})[0];
                    toast.error(first ?? t('operations.order_edit.save_failed'));
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const updateItem = (index, patch) => {
        setItems((prev) => prev.map((it, i) => (i === index ? { ...it, ...patch } : it)));
    };

    const addItem = (itemType) => {
        setItems((prev) => [
            ...prev,
            { productId: null, productName: '', itemType, quantity: 1, unitPrice: 0, discountAmount: 0 },
        ]);
    };

    const selectCatalogItem = (index, id) => {
        const current = items[index];
        const found = optionsForType(current?.itemType).find((p) => String(p.id) === String(id));
        if (!found) {
            updateItem(index, { productId: null, productName: '', unitPrice: 0 });
            return;
        }
        updateItem(index, {
            productId: found.id,
            productName: found.sku ? `${found.name} (${found.sku})` : found.name,
            unitPrice: Number(found.unit_price) || 0,
        });
    };

    const removeItem = (index) => {
        setItems((prev) => prev.filter((_, i) => i !== index));
    };

    const tabButton = (key, label) => (
        <button
            type="button"
            onClick={() => setTab(key)}
            className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${
                tab === key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'
            }`}
        >
            {label}
        </button>
    );

    return (
        <>
            <Button type="button" size="sm" variant="secondary" className="gap-1" onClick={() => setOpen(true)}>
                <RefreshCw className="size-3.5" />
                {t('operations.status_dialog.title')}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (value) {
                        setOpen(true);
                    } else {
                        resetAndClose();
                    }
                }}
            >
                <DialogContent className={tab === 'order' ? 'max-w-5xl max-h-[92vh] overflow-y-auto' : 'max-w-2xl'}>
                    <DialogHeader>
                        <DialogTitle>
                            {t('operations.status_dialog.title_with_code', { code: order.orderCode })}
                        </DialogTitle>
                        <DialogDescription>
                            {order.customerName} · {order.customerPhone}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex gap-1 rounded-lg bg-muted/50 p-1">
                        {tabButton('status', t('operations.order_edit.tab_status'))}
                        {tabButton('order', t('operations.order_edit.tab_order'))}
                    </div>

                    {tab === 'status' && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor={`status-${order.id}`}>
                                    {t('operations.status_dialog.operation_result')}
                                </Label>
                                <select
                                    id={`status-${order.id}`}
                                    className="input-soft flex h-9 w-full px-3"
                                    value={result}
                                    onChange={(e) => {
                                        setResult(e.target.value);
                                        setConfirmInsufficient(false);
                                    }}
                                >
                                    <option value="">{t('operations.status_dialog.select_result_placeholder')}</option>
                                    {Object.entries(groupedOptions).map(([group, groupItems]) => (
                                        <optgroup key={group} label={group}>
                                            {groupItems.map((item) => (
                                                <option key={item.value} value={item.value}>
                                                    {item.label}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </select>
                            </div>

                            {(showStockWarning || (confirmInsufficient && isClosing)) && (
                                <StockWarningBlock warnings={order.stockWarnings} />
                            )}

                            {needsSchedule && (
                                <div className="space-y-2">
                                    <Label htmlFor={`next-${order.id}`}>{t('operations.status_dialog.next_at')}</Label>
                                    <Input
                                        id={`next-${order.id}`}
                                        type="datetime-local"
                                        value={nextAt}
                                        onChange={(e) => setNextAt(e.target.value)}
                                    />
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor={`note-${order.id}`}>{t('operations.status_dialog.note_optional')}</Label>
                                <Input
                                    id={`note-${order.id}`}
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    placeholder={t('operations.status_dialog.note_example')}
                                />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={resetAndClose}>
                                    {t('operations.status_dialog.cancel')}
                                </Button>
                                <Button
                                    type="button"
                                    variant={showStockWarning && confirmInsufficient ? 'destructive' : 'default'}
                                    onClick={submitStatus}
                                    disabled={processing || !result || (needsSchedule && !nextAt)}
                                >
                                    {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                                    {showStockWarning && !confirmInsufficient
                                        ? t('operations.status_dialog.continue')
                                        : showStockWarning && confirmInsufficient
                                          ? t('operations.status_dialog.confirm_insufficient')
                                          : t('operations.status_dialog.save')}
                                </Button>
                            </DialogFooter>
                        </div>
                    )}

                    {tab === 'order' && (
                        <div className="space-y-4">
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
                                {/* Cột trái: thông tin khách hàng & giao hàng */}
                                <div className="space-y-3 rounded-lg border border-border/70 p-3">
                                    <p className="text-sm font-semibold">{t('operations.order_edit.customer_block')}</p>
                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.customer_name')}</Label>
                                        <Input value={customerName} onChange={(e) => setCustomerName(e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.customer_phone')}</Label>
                                        <Input value={customerPhone} onChange={(e) => setCustomerPhone(e.target.value)} />
                                    </div>

                                    <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                                        <input
                                            type="checkbox"
                                            className="size-4 accent-primary"
                                            checked={receiverIsCustomer}
                                            onChange={(e) => setReceiverIsCustomer(e.target.checked)}
                                        />
                                        {t('operations.order_edit.receiver_is_customer')}
                                    </label>
                                    {!receiverIsCustomer && (
                                        <div className="grid grid-cols-2 gap-2 rounded-lg border border-dashed border-border/70 p-2.5">
                                            <div className="space-y-1">
                                                <Label className="text-xs">{t('operations.order_edit.receiver_name')}</Label>
                                                <Input
                                                    value={receiverName}
                                                    onChange={(e) => setReceiverName(e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-xs">{t('operations.order_edit.receiver_phone')}</Label>
                                                <Input
                                                    value={receiverPhone}
                                                    onChange={(e) => setReceiverPhone(e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.shipping_address')}</Label>
                                        <textarea
                                            className="input-soft min-h-[56px] w-full px-3 py-2 text-sm"
                                            value={shippingAddress}
                                            onChange={(e) => setShippingAddress(e.target.value)}
                                        />
                                        <p className="text-[10px] text-muted-foreground">
                                            {t('operations.order_edit.shipping_address_hint')}
                                        </p>
                                    </div>

                                    {/* Địa chỉ giao đã xác nhận (ưu tiên dùng cho vận chuyển) */}
                                    <div className="space-y-2 rounded-lg border border-dashed border-border/70 p-2.5">
                                        <Label className="text-xs font-semibold">
                                            {t('operations.order_edit.confirmed_address')}
                                        </Label>

                                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-amber-700 dark:text-amber-400">
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-amber-500"
                                                checked={addressModeNew}
                                                onChange={(e) => {
                                                    setAddressModeNew(e.target.checked);
                                                    setProvinceCode('');
                                                    setDistrictCode('');
                                                    setWardCode('');
                                                }}
                                            />
                                            {t('operations.order_edit.address_mode_new')}
                                        </label>

                                        <Input
                                            value={addressDetail}
                                            placeholder={t('operations.order_edit.address_detail_placeholder')}
                                            maxLength={200}
                                            onChange={(e) => setAddressDetail(e.target.value)}
                                        />

                                        <SearchableSelect
                                            value={provinceCode}
                                            onChange={(v) => {
                                                setProvinceCode(v);
                                                setDistrictCode('');
                                                setWardCode('');
                                            }}
                                            options={provinceOpts}
                                            placeholder={t('operations.order_edit.province_placeholder')}
                                            searchPlaceholder={t('operations.order_edit.search_placeholder')}
                                            emptyText={t('operations.order_edit.search_empty')}
                                        />

                                        <div className="grid grid-cols-2 gap-2">
                                            {!addressModeNew && (
                                                <SearchableSelect
                                                    value={districtCode}
                                                    onChange={(v) => {
                                                        setDistrictCode(v);
                                                        setWardCode('');
                                                    }}
                                                    options={districtOpts}
                                                    disabled={!provinceCode}
                                                    placeholder={t('operations.order_edit.district_placeholder')}
                                                    searchPlaceholder={t('operations.order_edit.search_placeholder')}
                                                    emptyText={t('operations.order_edit.search_empty')}
                                                />
                                            )}
                                            <SearchableSelect
                                                className={addressModeNew ? 'col-span-2' : ''}
                                                value={wardCode}
                                                onChange={setWardCode}
                                                options={wardOpts}
                                                disabled={addressModeNew ? !provinceCode : !districtCode}
                                                placeholder={t('operations.order_edit.ward_placeholder')}
                                                searchPlaceholder={t('operations.order_edit.search_placeholder')}
                                                emptyText={t('operations.order_edit.search_empty')}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.customer_note')}</Label>
                                        <textarea
                                            className="input-soft min-h-[48px] w-full px-3 py-2 text-sm"
                                            value={customerNote}
                                            onChange={(e) => setCustomerNote(e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.carrier_title')}</Label>
                                        <select
                                            className="input-soft flex h-9 w-full px-3"
                                            value={carrier}
                                            onChange={(e) => {
                                                setCarrier(e.target.value);
                                                setShippingService('');
                                            }}
                                        >
                                            <option value="">{t('operations.order_edit.carrier_placeholder')}</option>
                                            {carrierOptions.map((c) => (
                                                <option key={c.value} value={c.value}>
                                                    {c.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs">{t('operations.order_edit.service_title')}</Label>
                                        <select
                                            className="input-soft flex h-9 w-full px-3"
                                            value={shippingService}
                                            disabled={!carrier || serviceList.length === 0}
                                            onChange={(e) => setShippingService(e.target.value)}
                                        >
                                            <option value="">{t('operations.order_edit.service_placeholder')}</option>
                                            {serviceList.map((s) => (
                                                <option key={s.value} value={s.value}>
                                                    {s.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                {/* Cột phải: kho, chọn sản phẩm, bảng SP, tổng tiền */}
                                <div className="space-y-3">
                                    <div className="space-y-1">
                                        <Label className="text-xs">{t('operations.order_edit.warehouse')}</Label>
                                        <select
                                            className="input-soft flex h-9 w-full px-3"
                                            value={warehouseId}
                                            onChange={(e) => setWarehouseId(e.target.value)}
                                        >
                                            <option value="">{t('operations.order_edit.warehouse_placeholder')}</option>
                                            {warehouseOptions.map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <Label className="text-sm font-semibold">{t('operations.order_edit.section_title')}</Label>
                                        <div className="flex gap-1.5">
                                            <Button type="button" size="sm" variant="outline" className="gap-1" onClick={() => addItem('product')}>
                                                <Plus className="size-3.5" />
                                                {t('operations.order_edit.add_item')}
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" className="gap-1" onClick={() => addItem('combo')}>
                                                <Plus className="size-3.5" />
                                                {t('operations.order_edit.add_combo')}
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="overflow-x-auto rounded-lg border border-border/70">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/60 text-xs text-muted-foreground">
                                                <tr>
                                                    <th className="px-2 py-2 text-left font-medium">{t('operations.order_edit.col_product')}</th>
                                                    <th className="px-2 py-2 text-right font-medium">{t('operations.order_edit.col_unit_price')}</th>
                                                    <th className="px-2 py-2 text-center font-medium">{t('operations.order_edit.col_qty')}</th>
                                                    <th className="px-2 py-2 text-right font-medium">{t('operations.order_edit.col_line_discount')}</th>
                                                    <th className="px-2 py-2 text-right font-medium">{t('operations.order_edit.col_line_total')}</th>
                                                    <th className="w-8 px-1 py-2" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {items.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={6} className="px-2 py-4 text-center text-xs text-muted-foreground">
                                                            {t('operations.order_edit.empty_items')}
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    items.map((it, index) => (
                                                        <tr key={index} className="border-t border-border/60 align-top">
                                                            <td className="px-2 py-1.5">
                                                                <select
                                                                    className="input-soft h-8 w-full px-2 text-sm"
                                                                    value={it.productId ?? ''}
                                                                    onChange={(e) => selectCatalogItem(index, e.target.value)}
                                                                >
                                                                    <option value="">
                                                                        {it.itemType === 'combo'
                                                                            ? t('operations.order_edit.pick_combo_placeholder')
                                                                            : t('operations.order_edit.pick_item_placeholder')}
                                                                    </option>
                                                                    {optionsForType(it.itemType).map((p) => (
                                                                        <option key={p.id} value={p.id}>
                                                                            {p.name}
                                                                            {p.sku ? ` (${p.sku})` : ''}
                                                                        </option>
                                                                    ))}
                                                                    {it.productId &&
                                                                        !optionsForType(it.itemType).some(
                                                                            (p) => String(p.id) === String(it.productId),
                                                                        ) && (
                                                                            <option value={it.productId}>{it.productName}</option>
                                                                        )}
                                                                </select>
                                                                <span
                                                                    className={`mt-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold ${
                                                                        it.itemType === 'combo'
                                                                            ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300'
                                                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                                                    }`}
                                                                >
                                                                    {t(`operations.order_edit.type_${it.itemType}`)}
                                                                </span>
                                                            </td>
                                                            <td className="px-2 py-1.5">
                                                                <Input
                                                                    className="h-8 text-right"
                                                                    type="number"
                                                                    min={0}
                                                                    value={it.unitPrice}
                                                                    onChange={(e) => updateItem(index, { unitPrice: e.target.value })}
                                                                />
                                                            </td>
                                                            <td className="px-2 py-1.5">
                                                                <Input
                                                                    className="h-8 w-16 text-center"
                                                                    type="number"
                                                                    min={1}
                                                                    value={it.quantity}
                                                                    onChange={(e) => updateItem(index, { quantity: e.target.value })}
                                                                />
                                                            </td>
                                                            <td className="px-2 py-1.5">
                                                                <Input
                                                                    className="h-8 text-right"
                                                                    type="number"
                                                                    min={0}
                                                                    value={it.discountAmount}
                                                                    onChange={(e) => updateItem(index, { discountAmount: e.target.value })}
                                                                />
                                                            </td>
                                                            <td className="whitespace-nowrap px-2 py-1.5 text-right font-medium">
                                                                {formatCurrency(
                                                                    Math.max(
                                                                        0,
                                                                        (Number(it.quantity) || 0) * (Number(it.unitPrice) || 0) -
                                                                            (Number(it.discountAmount) || 0),
                                                                    ),
                                                                )}
                                                            </td>
                                                            <td className="px-1 py-1.5 text-center">
                                                                <button
                                                                    type="button"
                                                                    className="text-muted-foreground hover:text-destructive"
                                                                    title={t('operations.order_edit.remove')}
                                                                    onClick={() => removeItem(index)}
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Chiết khấu / phí / cọc / phải thu */}
                                    <div className="space-y-2 rounded-lg bg-muted/40 p-3 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-muted-foreground">{t('operations.order_edit.product_discount')}</span>
                                            <span className="font-medium">{formatCurrency(itemsDiscount)}</span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <Label className="text-muted-foreground">{t('operations.order_edit.order_discount')}</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-36 text-right"
                                                value={orderDiscount}
                                                onChange={(e) => setOrderDiscount(e.target.value)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <Label className="text-muted-foreground">{t('operations.order_edit.shipping_fee')}</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-36 text-right"
                                                value={shippingFee}
                                                onChange={(e) => setShippingFee(e.target.value)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <Label className="text-muted-foreground">{t('operations.order_edit.deposit')}</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                className="h-8 w-36 text-right"
                                                value={deposit}
                                                onChange={(e) => setDeposit(e.target.value)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between border-t border-border/60 pt-2 text-muted-foreground">
                                            <span>{t('operations.order_edit.subtotal')}</span>
                                            <span>{formatCurrency(subtotal)}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span>{t('operations.order_edit.final_total')}</span>
                                            <span className="font-medium">{formatCurrency(finalTotal)}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-base font-semibold text-primary">
                                            <span>{t('operations.order_edit.amount_to_collect')}</span>
                                            <span>{formatCurrency(amountToCollect)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setItems(mapInitialItems(order))}>
                                    <RefreshCw className="mr-1 size-4" />
                                    {t('operations.order_edit.refresh_items')}
                                </Button>
                                <Button type="button" variant="outline" onClick={resetAndClose}>
                                    {t('operations.status_dialog.cancel')}
                                </Button>
                                <Button type="button" onClick={submitOrder} disabled={processing}>
                                    {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                                    {t('operations.order_edit.save_order')}
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
