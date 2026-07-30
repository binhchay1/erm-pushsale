import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiPost } from '@/lib/api';

/**
 * Dialog Đăng đơn khi FAB không có tick — cho phép đăng các đơn đủ điều kiện trên trang
 * hoặc dán thêm mã Pushsale.
 */
export function RegisterShipmentDialog({
    open,
    onOpenChange,
    eligibleRows = [],
    apiBase,
    onDone,
}) {
    const [codes, setCodes] = useState('');
    const [busy, setBusy] = useState(false);
    const [selected, setSelected] = useState(() => new Set());

    const rows = useMemo(() => eligibleRows, [eligibleRows]);

    useEffect(() => {
        if (!open) return;
        setSelected(new Set(rows.map((row) => row.id)));
        setCodes('');
    }, [open, rows]);

    const toggle = (id) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set(rows.map((row) => row.id)) : new Set());
    };

    const submit = async () => {
        const fromTable = rows.filter((row) => selected.has(row.id));
        const pasted = String(codes)
            .split(/[\s,;]+/)
            .map((code) => code.trim())
            .filter(Boolean);
        const byCode = pasted.length
            ? rows.filter((row) => pasted.includes(row.orderCode) && !selected.has(row.id))
            : [];
        const targets = [...fromTable, ...byCode];

        if (!targets.length) {
            toast.error('Chọn ít nhất 1 đơn đủ điều kiện đăng, hoặc dán mã đơn đang hiện trên trang.');
            return;
        }

        setBusy(true);
        let ok = 0;
        const errors = [];
        try {
            for (const row of targets) {
                try {
                    await apiPost(`${apiBase}/${row.id}/create-shipment`);
                    ok += 1;
                } catch (error) {
                    errors.push(`${row.orderCode}: ${error.message}`);
                }
            }
            if (ok) toast.success(`Đã đăng vận đơn cho ${ok} đơn.`);
            if (errors.length) toast.error(errors.slice(0, 3).join(' | '));
            onDone?.();
            onOpenChange(false);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-wh-dialog wide" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Đăng đơn</DialogTitle>
                </DialogHeader>
                <div className="ps-wh-register-dialog">
                    <p className="text-muted">
                        Không tick đơn trên bảng thì mở hộp thoại này (giống Pushsale). Chọn đơn đủ điều kiện bên dưới hoặc dán mã đang hiện trên trang.
                    </p>
                    <div className="form-group">
                        <span className="h-label">Danh sách mã bổ sung (tuỳ chọn)</span>
                        <textarea
                            className="form-control"
                            rows={3}
                            value={codes}
                            onChange={(e) => setCodes(e.target.value)}
                            placeholder="PS001... ; PS002..."
                        />
                    </div>
                    <div className="ps-wh-register-table-wrap">
                        <table className="table table-bordered table-condensed">
                            <thead>
                                <tr>
                                    <th style={{ width: 36 }}>
                                        <input
                                            type="checkbox"
                                            checked={rows.length > 0 && selected.size === rows.length}
                                            onChange={(e) => toggleAll(e.target.checked)}
                                        />
                                    </th>
                                    <th>Mã đơn</th>
                                    <th>Kho</th>
                                    <th>TTGH</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={4}>Không có đơn đủ điều kiện đăng trên trang hiện tại.</td>
                                    </tr>
                                ) : rows.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            <input
                                                type="checkbox"
                                                checked={selected.has(row.id)}
                                                onChange={() => toggle(row.id)}
                                            />
                                        </td>
                                        <td>{row.orderCode}</td>
                                        <td>{row.warehouseName || '—'}</td>
                                        <td>{row.deliveryStatus || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <DialogFooter className="ps-wh-dialog-footer">
                    <button type="button" className="btn btn-default" onClick={() => onOpenChange(false)} disabled={busy}>
                        Đóng
                    </button>
                    <button type="button" className="btn btn-primary" onClick={submit} disabled={busy}>
                        {busy ? 'Đang đăng...' : 'Đăng đơn'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
