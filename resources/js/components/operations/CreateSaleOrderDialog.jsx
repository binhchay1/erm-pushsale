import { router, usePage } from '@inertiajs/react';
import { Calculator, CalendarDays, CheckSquare, PackageOpen, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/format';

const EMPTY_FORM = {
    marketing_source_id: '',
    name: '',
    phone: '',
    message: '',
    address: '',
    province: '',
    district: '',
    ward: '',
    shipping_method: 'Thủ công',
    warehouse_id: '',
    delivery_note: '',
    discount: 0,
    shipping_fee: 0,
    deposit: 0,
    items: [],
};

function newLine(type = 'product') {
    return {
        key: `${Date.now()}-${Math.random()}`,
        item_type: type,
        product_id: '',
        product_name: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
    };
}

function Label({ children, required = false }) {
    return (
        <span className="sale-order-label">
            {children}
            {required && <em> (*)</em>}
        </span>
    );
}

export function CreateSaleOrderDialog({
    manualUrl,
    sources = [],
    productOptions = [],
    warehouseOptions = [],
}) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [processing, setProcessing] = useState(false);

    const catalog = useMemo(() => ({
        products: productOptions.filter((item) => item.type !== 'combo'),
        combos: productOptions.filter((item) => item.type === 'combo'),
    }), [productOptions]);

    const subtotal = useMemo(() => form.items.reduce((sum, item) => (
        sum + Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.unit_price) || 0)
        - Math.max(0, Number(item.discount) || 0)
    ), 0), [form.items]);
    const total = Math.max(0, subtotal - Number(form.discount || 0) + Number(form.shipping_fee || 0));
    const collect = Math.max(0, total - Number(form.deposit || 0));

    const update = (key, value) => setForm((current) => ({ ...current, [key]: value }));
    const updateLine = (key, patch) => setForm((current) => ({
        ...current,
        items: current.items.map((item) => item.key === key ? { ...item, ...patch } : item),
    }));

    const addLine = (type) => update('items', [...form.items, newLine(type)]);
    const removeLine = (key) => update('items', form.items.filter((item) => item.key !== key));

    const selectProduct = (line, productId) => {
        const options = line.item_type === 'combo' ? catalog.combos : catalog.products;
        const product = options.find((item) => String(item.id) === String(productId));
        updateLine(line.key, {
            product_id: product?.id ?? '',
            product_name: product?.name ?? '',
            unit_price: Number(product?.unit_price ?? 0),
        });
    };

    const reset = () => setForm(EMPTY_FORM);

    const submit = () => {
        if (!manualUrl) {
            toast.error('Chưa cấu hình đường dẫn tạo đơn.');
            return;
        }
        if (!form.marketing_source_id) {
            toast.error('Vui lòng chọn nguồn dữ liệu.');
            return;
        }
        if (!form.name.trim()) {
            toast.error('Vui lòng nhập họ tên khách hàng.');
            return;
        }
        if (!form.phone.trim()) {
            toast.error('Vui lòng nhập số điện thoại.');
            return;
        }
        const validItems = form.items.filter((item) => item.product_id);
        if (validItems.length === 0) {
            toast.error('Vui lòng chọn ít nhất một sản phẩm hoặc combo.');
            return;
        }

        const addressParts = [form.address, form.ward, form.district, form.province]
            .map((value) => String(value || '').trim())
            .filter(Boolean);
        const metadata = [
            form.shipping_method ? `Phương thức giao hàng: ${form.shipping_method}` : null,
            form.warehouse_id ? `Kho: ${warehouseOptions.find((item) => String(item.id) === String(form.warehouse_id))?.name ?? form.warehouse_id}` : null,
            `Tạm tính: ${subtotal}; Chiết khấu: ${Number(form.discount || 0)}; Phí VC: ${Number(form.shipping_fee || 0)}; Đặt cọc: ${Number(form.deposit || 0)}; Phải thu: ${collect}`,
        ].filter(Boolean).join('\n');

        const payload = {
            marketing_source_id: Number(form.marketing_source_id),
            name: form.name.trim(),
            phone: form.phone.trim(),
            address: addressParts.join(', '),
            shipping_address: addressParts.join(', '),
            message: form.message.trim(),
            shipping_notes: form.delivery_note.trim(),
            discount: Math.max(0, Number(form.discount) || 0),
            shipping_fee_collected: Math.max(0, Number(form.shipping_fee) || 0),
            deposit: Math.max(0, Number(form.deposit) || 0),
            note: metadata,
            items: validItems.map((item) => ({
                product_id: Number(item.product_id),
                item_type: item.item_type,
                quantity: Math.max(1, Number(item.quantity) || 1),
                unit_price: Math.max(0, Number(item.unit_price) || 0),
                discount_amount: Math.max(0, Number(item.discount) || 0),
            })),
        };

        if (auth?.user?.role === 'sales') {
            payload.allocation_mode = 'manual';
            payload.sale_user_ids = [Number(auth.user.id)];
        }

        setProcessing(true);
        router.post(manualUrl, payload, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã tiếp nhận đơn mới.');
                setOpen(false);
                reset();
            },
            onError: (errors) => {
                toast.error(errors.phone ?? errors.items ?? 'Không thể tạo đơn. Vui lòng kiểm tra lại dữ liệu.');
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <button
                type="button"
                className="pushsale-create-order-fab"
                onClick={() => setOpen(true)}
                title="Tạo đơn"
            >
                <span><i className="fa fa-pencil-square-o" aria-hidden="true" /></span>
                <strong>Tạo đơn</strong>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="pushsale-sale-order-dialog" aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>Nhập đơn mới: <small>- -</small></DialogTitle>
                    </DialogHeader>

                    <div data-slot="dialog-body" className="sale-order-dialog-body">
                        <div className="sale-order-grid">
                            <section className="sale-order-customer-panel">
                                <div className="sale-order-field sale-order-field-full">
                                    <Label required>Nguồn dữ liệu</Label>
                                    <select value={form.marketing_source_id} onChange={(event) => update('marketing_source_id', event.target.value)}>
                                        <option value="">--Chọn nguồn dữ liệu--</option>
                                        {sources.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}
                                    </select>
                                </div>

                                <div className="sale-order-field">
                                    <Label required>Họ tên khách hàng</Label>
                                    <input value={form.name} onChange={(event) => update('name', event.target.value)} />
                                </div>
                                <div className="sale-order-field">
                                    <Label required>Số điện thoại</Label>
                                    <input value={form.phone} onChange={(event) => update('phone', event.target.value)} />
                                </div>

                                <div className="sale-order-field sale-order-field-full">
                                    <Label>Tin nhắn</Label>
                                    <textarea rows={2} value={form.message} onChange={(event) => update('message', event.target.value)} />
                                </div>

                                <div className="sale-order-field">
                                    <Label>Số nhà/đường/ngõ/ngách</Label>
                                    <input placeholder="Tìm kiếm (Tối đa 200 ký tự)" value={form.address} onChange={(event) => update('address', event.target.value)} />
                                </div>
                                <div className="sale-order-field">
                                    <Label>Tỉnh/TP</Label>
                                    <input placeholder="--Chọn Tỉnh/TP--" value={form.province} onChange={(event) => update('province', event.target.value)} />
                                </div>
                                <div className="sale-order-field">
                                    <Label>Quận/Huyện</Label>
                                    <input placeholder="--Chọn Quận/Huyện--" value={form.district} onChange={(event) => update('district', event.target.value)} />
                                </div>
                                <div className="sale-order-field">
                                    <Label>Phường/Xã</Label>
                                    <input placeholder="--Chọn Phường/Xã--" value={form.ward} onChange={(event) => update('ward', event.target.value)} />
                                </div>
                                <div className="sale-order-field">
                                    <Label>Phương thức giao hàng</Label>
                                    <select value={form.shipping_method} onChange={(event) => update('shipping_method', event.target.value)}>
                                        <option>Thủ công</option>
                                        <option>Giao hàng tiêu chuẩn</option>
                                        <option>Giao hàng nhanh</option>
                                    </select>
                                </div>
                                <div className="sale-order-field">
                                    <Label>Giao hàng bằng</Label>
                                    <select value={form.shipping_method} onChange={(event) => update('shipping_method', event.target.value)}>
                                        <option>Giao hàng thủ công</option>
                                        <option>Đơn vị vận chuyển</option>
                                    </select>
                                </div>
                                <div className="sale-order-field sale-order-field-full">
                                    <Label>Giao hàng ghi chú</Label>
                                    <textarea rows={2} placeholder="Cho xem hàng, không được thử, không bóc seal..." value={form.delivery_note} onChange={(event) => update('delivery_note', event.target.value)} />
                                </div>
                            </section>

                            <section className="sale-order-product-panel">
                                <div className="sale-order-top-fields">
                                    <div className="sale-order-field">
                                        <Label>Kho</Label>
                                        <select value={form.warehouse_id} onChange={(event) => update('warehouse_id', event.target.value)}>
                                            <option value="">--Chọn kho--</option>
                                            {warehouseOptions.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="sale-order-field">
                                        <Label>Địa chỉ lấy hàng</Label>
                                        <input value={warehouseOptions.find((item) => String(item.id) === String(form.warehouse_id))?.name ?? ''} readOnly />
                                    </div>
                                    <div className="sale-order-field">
                                        <Label>Sản phẩm</Label>
                                        <select value="" onChange={(event) => {
                                            if (!event.target.value) return;
                                            const line = newLine('product');
                                            const product = catalog.products.find((item) => String(item.id) === event.target.value);
                                            update('items', [...form.items, { ...line, product_id: product.id, product_name: product.name, unit_price: Number(product.unit_price || 0) }]);
                                        }}>
                                            <option value="">--Chọn sản phẩm--</option>
                                            {catalog.products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="sale-order-field">
                                        <Label>Combo</Label>
                                        <select value="" onChange={(event) => {
                                            if (!event.target.value) return;
                                            const line = newLine('combo');
                                            const product = catalog.combos.find((item) => String(item.id) === event.target.value);
                                            update('items', [...form.items, { ...line, product_id: product.id, product_name: product.name, unit_price: Number(product.unit_price || 0) }]);
                                        }}>
                                            <option value="">--Chọn combo--</option>
                                            {catalog.combos.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}
                                        </select>
                                    </div>
                                </div>

                                <p className="sale-order-vat-note">(v) Đã bao gồm VAT</p>

                                <div className="sale-order-products-table-wrap">
                                    <table className="sale-order-products-table">
                                        <thead>
                                            <tr>
                                                <th>Tên/ Mã SP</th>
                                                <th>Đơn giá</th>
                                                <th>SL Tổng</th>
                                                <th>Thành tiền</th>
                                                <th>CK 1</th>
                                                <th>CN(g)</th>
                                                <th />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {form.items.length === 0 ? (
                                                <tr><td colSpan={7} className="sale-order-empty">Chưa chọn sản phẩm</td></tr>
                                            ) : form.items.map((line) => (
                                                <tr key={line.key}>
                                                    <td>
                                                        <select value={line.product_id} onChange={(event) => selectProduct(line, event.target.value)}>
                                                            <option value="">--Chọn--</option>
                                                            {(line.item_type === 'combo' ? catalog.combos : catalog.products).map((product) => (
                                                                <option key={product.id} value={product.id}>{product.name}{product.sku ? ` (${product.sku})` : ''}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    <td><input type="number" min="0" value={line.unit_price} onChange={(event) => updateLine(line.key, { unit_price: event.target.value })} /></td>
                                                    <td><input type="number" min="1" value={line.quantity} onChange={(event) => updateLine(line.key, { quantity: event.target.value })} /></td>
                                                    <td className="text-right">{formatCurrency(Number(line.unit_price || 0) * Number(line.quantity || 0) - Number(line.discount || 0))}</td>
                                                    <td><input type="number" min="0" value={line.discount} onChange={(event) => updateLine(line.key, { discount: event.target.value })} /></td>
                                                    <td className="text-center">—</td>
                                                    <td><button type="button" className="sale-order-remove" onClick={() => removeLine(line.key)}><Trash2 /></button></td>
                                                </tr>
                                            ))}
                                            <tr className="sale-order-summary-row">
                                                <td colSpan={3}><strong>Tổng cộng:</strong></td>
                                                <td className="text-right"><strong>{formatCurrency(subtotal)}</strong></td>
                                                <td colSpan={3} />
                                            </tr>
                                            <tr>
                                                <td colSpan={3} className="text-right">Chiết khấu sản phẩm (v):</td>
                                                <td><input type="number" min="0" value={form.discount} onChange={(event) => update('discount', event.target.value)} /></td>
                                                <td colSpan={3} />
                                            </tr>
                                            <tr>
                                                <td colSpan={3} className="text-right">Phí VC thu của khách (v):</td>
                                                <td><input type="number" min="0" value={form.shipping_fee} onChange={(event) => update('shipping_fee', event.target.value)} /></td>
                                                <td colSpan={3} />
                                            </tr>
                                            <tr>
                                                <td colSpan={3} className="text-right">Khách đã đặt cọc:</td>
                                                <td><input type="number" min="0" value={form.deposit} onChange={(event) => update('deposit', event.target.value)} /></td>
                                                <td colSpan={3} />
                                            </tr>
                                            <tr className="sale-order-total-row">
                                                <td colSpan={3} className="text-right"><strong>Tổng tiền đơn:</strong></td>
                                                <td className="text-right"><strong>{formatCurrency(total)}</strong></td>
                                                <td colSpan={2} className="text-right"><strong>Phải thu của khách:</strong></td>
                                                <td className="text-right"><strong>{formatCurrency(collect)}</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div className="sale-order-inline-actions">
                                    <Button type="button" variant="outline"><Calculator />Tính phí VC</Button>
                                    <Button type="button" variant="outline"><CalendarDays />Tính CK</Button>
                                    <Button type="button" variant="outline" onClick={() => addLine('product')}><Plus />Thêm SP</Button>
                                    <Button type="button" variant="outline" onClick={reset}><PackageOpen />Làm mới SP</Button>
                                </div>
                            </section>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>Đóng</Button>
                        <Button type="button" variant="outline" onClick={submit} disabled={processing}><Save />Lưu đơn</Button>
                        <Button type="button" onClick={submit} disabled={processing}><CheckSquare />Chốt đơn</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
