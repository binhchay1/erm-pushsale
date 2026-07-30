import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

function toInputDate(value = new Date()) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return new Date().toISOString().slice(0, 10);
    return date.toISOString().slice(0, 10);
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.id ?? '';
}

/**
 * FAB: Thêm đơn vào biên bản bàn giao vận đơn (not return-receipt).
 */
export function AddToHandoverDialog({
    open,
    onOpenChange,
    targetRows = [],
    providers = [],
    incidentsUrl = '/admin/warehouse/incidents',
    onDone,
}) {
    const orderCount = targetRows.length;
    const productCount = useMemo(
        () => targetRows.reduce((sum, row) => {
            const products = row.products ?? row.items ?? [];
            if (Array.isArray(products) && products.length) {
                return sum + products.reduce((inner, item) => inner + Number(item.quantity || 0), 0);
            }
            return sum + Number(row.productCount || row.product_count || 0);
        }, 0),
        [targetRows],
    );

    const [form, setForm] = useState(() => ({
        name: 'BIÊN BẢN BÀN GIAO VẬN ĐƠN',
        carrier: '',
        document_date: toInputDate(),
        sender_name: '',
        receiver_name: '',
        note: '',
        status: 'updating',
    }));
    const [processing, setProcessing] = useState(false);

    const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    const submit = (event) => {
        event.preventDefault();
        if (!orderCount) {
            toast.error('Không có đơn để thêm vào biên bản.');
            return;
        }
        if (!form.carrier || !form.sender_name || !form.receiver_name || !form.note) {
            toast.error('Điền đủ các trường bắt buộc.');
            return;
        }

        setProcessing(true);
        router.post(`${incidentsUrl}/records`, {
            payload: {
                ...form,
                order_count: orderCount,
                product_count: productCount,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã tạo biên bản với ${orderCount} đơn.`);
                onOpenChange(false);
                onDone?.();
            },
            onError: (errors) => {
                const message = errors?.payload
                    || errors?.name
                    || Object.values(errors || {})[0]
                    || 'Không tạo được biên bản.';
                toast.error(String(message));
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-wh-dialog wide ps-handover-fab-dialog">
                <DialogHeader>
                    <DialogTitle>Biên bản bàn giao vận đơn</DialogTitle>
                </DialogHeader>
                <form className="ps-handover-form" onSubmit={submit}>
                    <p className="text-muted" style={{ marginBottom: 12 }}>
                        Thêm
                        {' '}
                        <strong>{orderCount}</strong>
                        {' '}
                        đơn (
                        {productCount}
                        {' '}
                        SP) vào biên bản mới.
                    </p>
                    <label>
                        <span>Tên biên bản<span className="text-red">(*)</span></span>
                        <input value={form.name} onChange={(e) => setField('name', e.target.value)} required />
                    </label>
                    <label>
                        <span>Đơn vị giao hàng<span className="text-red">(*)</span></span>
                        <select value={form.carrier} onChange={(e) => setField('carrier', e.target.value)} required>
                            <option value="">--Chọn đơn vị giao hàng--</option>
                            {providers.map((provider) => {
                                const value = provider.id ?? provider.value;
                                return (
                                    <option key={value} value={value}>
                                        {optionLabel(provider)}
                                    </option>
                                );
                            })}
                        </select>
                    </label>
                    <label>
                        <span>Ngày biên bản<span className="text-red">(*)</span></span>
                        <input type="date" value={form.document_date} onChange={(e) => setField('document_date', e.target.value)} required />
                    </label>
                    <label>
                        <span>Bên giao<span className="text-red">(*)</span></span>
                        <input value={form.sender_name} onChange={(e) => setField('sender_name', e.target.value)} required />
                    </label>
                    <label>
                        <span>Bên nhận<span className="text-red">(*)</span></span>
                        <input value={form.receiver_name} onChange={(e) => setField('receiver_name', e.target.value)} required />
                    </label>
                    <label>
                        <span>Ghi chú<span className="text-red">(*)</span></span>
                        <input value={form.note} onChange={(e) => setField('note', e.target.value)} required />
                    </label>
                    <DialogFooter className="ps-handover-modal-actions" style={{ marginLeft: 0 }}>
                        <button type="button" className="btn btn-default btn-sm" onClick={() => onOpenChange(false)} disabled={processing}>
                            Đóng
                        </button>
                        <button type="submit" className="btn btn-primary btn-sm" disabled={processing}>
                            <i className="fa fa-plus" /> Thêm mới
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
