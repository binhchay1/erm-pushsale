import { useEffect, useMemo, useState } from 'react';
import { Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiRequest } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';

const deliveryStatuses = [
    ['waiting_waybill', 'Chờ vận đơn'], ['posted', 'Đã đăng vận đơn'], ['picking_up', 'Đang lấy hàng'],
    ['delivering', 'Đang giao'], ['redelivery', 'Giao lại'], ['partial_delivery', 'Giao một phần'],
    ['delivered', 'Đã giao'], ['delivery_complete', 'Hoàn tất giao'], ['paid', 'Đã đối soát COD'],
    ['cannot_deliver', 'Không giao được'], ['returning', 'Đang hoàn'], ['returned', 'Đã hoàn'],
    ['cancel_waybill', 'Hủy vận đơn'],
];

function Field({ label, children, required = false }) {
    return <label className="ps-wh-dialog-field"><span>{label}{required && <b> (*)</b>}</span>{children}</label>;
}

function MoneyInput({ value, onChange }) {
    return <input type="number" min="0" step="1000" value={value ?? 0} onChange={(event) => onChange(Number(event.target.value || 0))} />;
}

export function WarehouseActionDialogs({ action, onClose, actionApiBase, filterOptions = {} }) {
    const row = action?.row;
    const type = action?.type;
    const [loading, setLoading] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState({});

    useEffect(() => {
        if (! row || ! type) return;
        setFormError('');
        if (type === 'date') setForm({ desired_delivery_at: row.desiredDeliveryAt?.slice(0, 16) ?? '' });
        if (type === 'blacklist') setForm({ phone: row.customerPhone ?? '', reason: 'Chờ vận đơn' });
        if (type === 'care') setForm({ status: row.warehouseCareStatus ?? 'waiting', note: row.warehouseCareNote ?? '' });
        if (type === 'message') setForm({ message: '' });
        if (type === 'changeCode') setForm({ order_code: row.orderCode ?? '' });
        if (type === 'delivery') setForm({ delivery_status: row.deliveryStatusValue ?? 'waiting_waybill', note: row.shippingNotes ?? '', collected_amount: row.settledCodAmount ?? 0 });
        if (type === 'edit') setForm({
            customer_name: row.customerName ?? '', customer_phone: row.customerPhone ?? '',
            receiver_name: row.receiverName ?? '', receiver_phone: row.receiverPhone ?? '',
            shipping_address: row.shippingAddressRaw ?? '', shipping_address_2: row.shippingAddress2 ?? '',
            shipping_notes: row.shippingNotes ?? '', customer_note: row.customerNote ?? '',
            warehouse_id: row.warehouseId ?? '', shipping_provider: row.shippingProvider ?? '', shipping_method: row.shippingMethod ?? '',
            discount: row.discount ?? 0, vat: row.vat ?? 0, shipping_fee_collected: row.shippingFeeCollected ?? 0, deposit: row.deposit ?? 0,
            items: (row.products ?? []).map((item) => ({ product_id: item.productId, product_name: item.productName, item_type: item.itemType, quantity: item.quantity, unit_price: item.unitPrice, discount_amount: item.discountAmount ?? 0 })),
        });
        if (type === 'split') setForm({ items: (row.products ?? []).map((item) => ({ order_item_id: item.id, product_name: item.productName, max: item.quantity, quantity: 0 })) });
        if (type === 'return') setForm({
            reason: row.returnReason ?? '', note: '',
            items: (row.products ?? []).map((item) => ({ order_item_id: item.id, product_id: item.productId, product_name: item.productName, expected: item.quantity, received_quantity: item.quantity, restock_quantity: item.quantity, damaged_quantity: 0, missing_quantity: 0, condition: 'sellable', note: '' })),
        });
    }, [row, type]);

    const endpoint = useMemo(() => row ? `${actionApiBase}/${row.id}` : '', [actionApiBase, row]);
    const submit = async () => {
        if (type === 'blacklist') {
            const phoneError = vietnamesePhoneError(form.phone, { required: true });
            if (phoneError) {
                setFormError(phoneError);
                return;
            }
        }
        if (type === 'edit') {
            for (const [key, required] of [['customer_phone', true], ['receiver_phone', false]]) {
                const phoneError = vietnamesePhoneError(form[key], { required });
                if (phoneError) {
                    setFormError(phoneError);
                    return;
                }
            }
        }
        if (type === 'changeCode' && !String(form.order_code ?? '').trim()) {
            setFormError('Nhập mã đơn mới.');
            return;
        }
        if (type === 'message' && !String(form.message ?? '').trim()) {
            setFormError('Nhập nội dung tin nhắn nội bộ.');
            return;
        }

        setFormError('');
        setLoading(true);
        try {
            let path = endpoint;
            let method = 'PATCH';
            let payload = { ...form };
            if (type === 'blacklist') {
                payload = { ...payload, phone: normalizeVietnamesePhone(form.phone) ?? form.phone };
            }
            if (type === 'edit') {
                payload = {
                    ...payload,
                    customer_phone: normalizeVietnamesePhone(form.customer_phone) ?? form.customer_phone,
                    receiver_phone: String(form.receiver_phone ?? '').trim()
                        ? (normalizeVietnamesePhone(form.receiver_phone) ?? form.receiver_phone)
                        : form.receiver_phone,
                };
            }
            if (type === 'date') path += '/desired-delivery';
            if (type === 'changeCode') {
                path += '/order-code';
                payload = { order_code: String(form.order_code ?? '').trim() };
            }
            if (type === 'blacklist') { path += '/blacklist'; method = 'POST'; }
            if (type === 'care') path += '/care';
            if (type === 'message') {
                path += '/care';
                payload = {
                    status: row.warehouseCareStatus ?? 'waiting',
                    note: [row.warehouseCareNote, `[Nội bộ] ${String(form.message).trim()}`].filter(Boolean).join('\n'),
                };
            }
            if (type === 'delivery') path += '/delivery-status';
            if (type === 'edit') method = 'PUT';
            if (type === 'split') { path += '/split'; method = 'POST'; payload = { items: form.items.filter((item) => Number(item.quantity) > 0).map(({ order_item_id, quantity }) => ({ order_item_id, quantity: Number(quantity) })) }; }
            if (type === 'return') { path += '/return-receipt'; method = 'POST'; }
            const result = await apiRequest(path, { method, body: payload });
            toast.success(result.message ?? 'Đã cập nhật đơn hàng.');
            onClose();
            router.reload({ only: ['report', 'filters', 'filterOptions'] });
        } catch (error) {
            setFormError(error.message || 'Không thể cập nhật đơn hàng.');
        } finally {
            setLoading(false);
        }
    };

    if (! row) return null;
    const titles = {
        date: 'Cập nhật ngày giao hàng theo đơn',
        blacklist: 'Cập nhật số blacklist',
        care: 'Cập nhật trạng thái care đơn',
        message: 'Tin nhắn nội bộ',
        changeCode: `Đổi mã đơn: ${row.orderCode}`,
        delivery: 'Cập nhật trạng thái giao hàng',
        edit: `Cập nhật đơn: ${row.orderCode}`,
        split: `Tách đơn: ${row.orderCode}`,
        return: `Nhập hàng hoàn: ${row.orderCode}`,
        timeline: 'Lịch sử tác nghiệp',
    };

    return (
        <Dialog open={Boolean(action)} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className={`ps-wh-dialog ${type === 'edit' || type === 'split' || type === 'return' ? 'wide' : ''}`}>
                <DialogHeader><DialogTitle>{titles[type]}</DialogTitle></DialogHeader>
                <div className="ps-wh-dialog-body">
                    {formError ? <div className="ps-dialog-form-error" role="alert">{formError}</div> : null}
                    <div className="ps-wh-dialog-order-summary">
                        <span><b>Mã đơn:</b> {row.orderCode}</span><span><b>Khách:</b> {row.customerName}</span><span><b>SĐT:</b> {row.customerPhone}</span><span><b>Trạng thái:</b> {row.deliveryStatus}</span>
                    </div>

                    {type === 'date' && <Field label="Ngày giao hàng mong muốn" required><input type="datetime-local" value={form.desired_delivery_at ?? ''} onChange={(e) => setForm({ ...form, desired_delivery_at: e.target.value })} /></Field>}
                    {type === 'changeCode' && <Field label="Mã đơn mới" required><input value={form.order_code ?? ''} onChange={(e) => setForm({ ...form, order_code: e.target.value })} maxLength={80} /></Field>}
                    {type === 'blacklist' && <><Field label="Số blacklist" required><input value={form.phone ?? ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></Field><Field label="Lý do" required><input value={form.reason ?? ''} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></Field></>}
                    {type === 'care' && <><Field label="Chuyển sang trạng thái"><select value={form.status ?? ''} onChange={(e) => setForm({ ...form, status: e.target.value })}>{(filterOptions.warehouseCareStatuses ?? []).map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field><Field label="Ghi chú"><textarea value={form.note ?? ''} onChange={(e) => setForm({ ...form, note: e.target.value })} /></Field></>}
                    {type === 'message' && <Field label="Nội dung tin nhắn nội bộ" required><textarea value={form.message ?? ''} onChange={(e) => setForm({ ...form, message: e.target.value })} rows={4} /></Field>}
                    {type === 'delivery' && <><Field label="Chuyển sang trạng thái"><select value={form.delivery_status ?? ''} onChange={(e) => setForm({ ...form, delivery_status: e.target.value })}>{deliveryStatuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field><Field label="Ghi chú"><textarea value={form.note ?? ''} onChange={(e) => setForm({ ...form, note: e.target.value })} /></Field><Field label="Tiền đã thu (giao một phần/COD)"><MoneyInput value={form.collected_amount} onChange={(value) => setForm({ ...form, collected_amount: value })} /></Field></>}

                    {type === 'edit' && (
                        <div className="ps-wh-edit-grid">
                            <Field label="Họ tên khách hàng"><input value={form.customer_name ?? ''} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} /></Field>
                            <Field label="Số điện thoại"><input value={form.customer_phone ?? ''} onChange={(e) => setForm({ ...form, customer_phone: e.target.value })} /></Field>
                            <Field label="Người nhận"><input value={form.receiver_name ?? ''} onChange={(e) => setForm({ ...form, receiver_name: e.target.value })} /></Field>
                            <Field label="SĐT người nhận"><input value={form.receiver_phone ?? ''} onChange={(e) => setForm({ ...form, receiver_phone: e.target.value })} /></Field>
                            <Field label="Địa chỉ giao" ><textarea value={form.shipping_address_2 || form.shipping_address || ''} onChange={(e) => setForm({ ...form, shipping_address_2: e.target.value })} /></Field>
                            <Field label="Đơn vị giao hàng"><select value={form.shipping_provider ?? ''} onChange={(e) => setForm({ ...form, shipping_provider: e.target.value })}><option value="">-- Chọn --</option>{(filterOptions.shippingProviders ?? []).map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field>
                            <Field label="Kho"><select value={form.warehouse_id ?? ''} onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}><option value="">-- Chọn --</option>{(filterOptions.warehouses ?? []).map((item) => { const value = item.value ?? item.id; return <option key={value} value={value}>{item.label ?? item.name}</option>; })}</select></Field>
                            <Field label="Phí ship thu khách"><MoneyInput value={form.shipping_fee_collected} onChange={(value) => setForm({ ...form, shipping_fee_collected: value })} /></Field>
                            <Field label="Tiền cọc"><MoneyInput value={form.deposit} onChange={(value) => setForm({ ...form, deposit: value })} /></Field>
                            <Field label="Chiết khấu"><MoneyInput value={form.discount} onChange={(value) => setForm({ ...form, discount: value })} /></Field>
                            <Field label="VAT"><MoneyInput value={form.vat} onChange={(value) => setForm({ ...form, vat: value })} /></Field>
                            <Field label="Ghi chú giao hàng"><textarea value={form.shipping_notes ?? ''} onChange={(e) => setForm({ ...form, shipping_notes: e.target.value })} /></Field>
                            <div className="ps-wh-items-editor">
                                <h4>Sản phẩm trong đơn</h4>
                                <table><thead><tr><th>Tên sản phẩm</th><th>Loại</th><th>SL</th><th>Đơn giá (VND)</th><th>Chiết khấu</th><th>Thành tiền</th></tr></thead><tbody>
                                    {(form.items ?? []).map((item, index) => <tr key={index}><td><input value={item.product_name} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, product_name: e.target.value }; setForm({ ...form, items }); }} /></td><td><select value={item.item_type ?? 'product'} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, item_type: e.target.value }; setForm({ ...form, items }); }}><option value="product">Sản phẩm</option><option value="combo">Combo</option><option value="upsell">Upsale</option><option value="gift">Quà tặng</option></select></td><td><input type="number" min="1" value={item.quantity} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, quantity: Number(e.target.value) }; setForm({ ...form, items }); }} /></td><td><input type="number" min="0" value={item.unit_price} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, unit_price: Number(e.target.value) }; setForm({ ...form, items }); }} /></td><td><input type="number" min="0" value={item.discount_amount ?? 0} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, discount_amount: Number(e.target.value) }; setForm({ ...form, items }); }} /></td><td>{formatCurrency(Math.max(0, item.quantity * item.unit_price - (item.discount_amount ?? 0)))}</td></tr>)}
                                </tbody></table>
                            </div>
                        </div>
                    )}

                    {type === 'split' && <div className="ps-wh-items-editor"><div className="ps-wh-warning">Chỉ tách được đơn chưa xuất kho và chưa có mã vận đơn. Mỗi đơn phải còn ít nhất một sản phẩm.</div><table><thead><tr><th>Sản phẩm</th><th>Số lượng trong đơn</th><th>Số lượng tách</th></tr></thead><tbody>{(form.items ?? []).map((item, index) => <tr key={item.order_item_id}><td>{item.product_name}</td><td>{item.max}</td><td><input type="number" min="0" max={item.max} value={item.quantity} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, quantity: Number(e.target.value) }; setForm({ ...form, items }); }} /></td></tr>)}</tbody></table></div>}

                    {type === 'return' && <div className="ps-wh-return-editor"><Field label="Lý do hoàn"><input value={form.reason ?? ''} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></Field><Field label="Ghi chú biên bản"><textarea value={form.note ?? ''} onChange={(e) => setForm({ ...form, note: e.target.value })} /></Field><table><thead><tr><th>Sản phẩm</th><th>SL dự kiến</th><th>SL nhận</th><th>Nhập lại kho</th><th>Hỏng</th><th>Thiếu</th><th>Tình trạng</th></tr></thead><tbody>{(form.items ?? []).map((item, index) => <tr key={item.order_item_id}><td>{item.product_name}</td><td>{item.expected}</td>{['received_quantity','restock_quantity','damaged_quantity','missing_quantity'].map((key) => <td key={key}><input type="number" min="0" max={item.expected} value={item[key]} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, [key]: Number(e.target.value) }; setForm({ ...form, items }); }} /></td>)}<td><select value={item.condition} onChange={(e) => { const items = [...form.items]; items[index] = { ...item, condition: e.target.value }; setForm({ ...form, items }); }}><option value="sellable">Còn bán được</option><option value="damaged">Hư hỏng</option><option value="missing">Thiếu/mất</option><option value="inspection">Chờ kiểm tra</option></select></td></tr>)}</tbody></table></div>}

                    {type === 'timeline' && <div className="ps-wh-timeline">{(row.statusEvents ?? []).length ? row.statusEvents.map((event) => <div key={event.id}><time>{formatDateTime(event.occurredAt, { withSeconds: false })}</time><strong>{event.rawStatus || event.mappedStatus}</strong><span>{event.location || ''} {event.note || ''}</span>{event.financials && <small>COD: {formatCurrency(event.financials.cod_remitted || event.financials.cod_collected || event.financials.cod_amount)} · Phí: {formatCurrency((event.financials.shipping_fee || 0)+(event.financials.return_fee || 0)+(event.financials.cod_fee || 0)+(event.financials.other_fee || 0))}</small>}</div>) : <p>Chưa có sự kiện từ đơn vị vận chuyển.</p>}</div>}
                </div>
                {type !== 'timeline' && <DialogFooter className="ps-wh-dialog-footer"><Button variant="outline" onClick={onClose} disabled={loading}>Đóng</Button><Button onClick={submit} disabled={loading}>{loading ? <Loader2 className="animate-spin" size={15} /> : <Save size={15} />} Cập nhật</Button></DialogFooter>}
            </DialogContent>
        </Dialog>
    );
}
