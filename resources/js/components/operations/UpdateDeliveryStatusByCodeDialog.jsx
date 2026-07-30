import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiRequest } from '@/lib/api';

function Field({ label, required = false, children }) {
    return (
        <label className="ps-ttgh-field">
            <span>
                {label}
                {required ? <b className="text-danger"> (*)</b> : null}
            </span>
            {children}
        </label>
    );
}

/**
 * FAB truck xanh — Cập nhật trạng thái giao hàng theo mã đơn.
 * Status options lấy từ filterOptions (không hard-code).
 */
export function UpdateDeliveryStatusByCodeDialog({
    open,
    onOpenChange,
    actionApiBase,
    initialCodes = '',
    deliveryStatuses = [],
    onDone,
}) {
    const apiBase = `${actionApiBase}/delivery-status-bulk`;
    const [codeType, setCodeType] = useState('MHT');
    const [isGhtk, setIsGhtk] = useState(false);
    const [codes, setCodes] = useState(initialCodes);
    const [deliveryStatus, setDeliveryStatus] = useState('');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [inspect, setInspect] = useState(null);

    const statusOptions = useMemo(
        () => (deliveryStatuses ?? []).map((item) => ({
            value: String(item.value ?? ''),
            label: item.label ?? String(item.value ?? ''),
        })).filter((item) => item.value),
        [deliveryStatuses],
    );

    useEffect(() => {
        if (!open) return;
        setCodes(initialCodes || '');
        setDeliveryStatus('');
        setNote('');
        setIsGhtk(false);
        setCodeType('MHT');
        setInspect(null);
    }, [open, initialCodes]);

    const runInspect = async () => {
        if (!String(codes).trim()) {
            toast.error('Nhập danh sách mã đơn.');
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${apiBase}/inspect`, {
                method: 'POST',
                body: { codes, code_type: codeType, is_ghtk: isGhtk },
            });
            setInspect(data);
            toast.success(`Tìm thấy ${data.found} đơn` + (data.missing?.length ? `, thiếu ${data.missing.length} mã` : ''));
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const submit = async () => {
        if (!String(codes).trim()) {
            toast.error('Nhập danh sách mã đơn.');
            return;
        }
        if (!deliveryStatus) {
            toast.error('Chọn trạng thái giao hàng.');
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${apiBase}/update`, {
                method: 'POST',
                body: {
                    codes,
                    code_type: codeType,
                    is_ghtk: isGhtk,
                    delivery_status: deliveryStatus,
                    note: note || null,
                },
            });
            if (data.failed_count > 0) toast.warning(data.message);
            else toast.success(data.message);
            onDone?.();
            onOpenChange(false);
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-wh-dialog ps-ttgh-by-code-dialog" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Cập nhật trạng thái giao hàng theo mã đơn</DialogTitle>
                </DialogHeader>

                <div className="ps-ttgh-form">
                    <Field label="Loại mã đơn" required>
                        <select className="form-control" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                            <option value="MHT">Mã đơn PUSHSALE</option>
                            <option value="MGV">Mã vận đơn</option>
                        </select>
                    </Field>

                    <Field label="Danh sách mã đơn" required>
                        <textarea
                            className="form-control"
                            rows={6}
                            value={codes}
                            onChange={(e) => setCodes(e.target.value)}
                            placeholder="PS001... cách nhau bằng ; hoặc xuống dòng"
                        />
                    </Field>

                    {codeType === 'MGV' ? (
                        <label className="ps-ttgh-check">
                            <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                            {' '}
                            Đơn vị GH là: Giao hàng tiết kiệm
                        </label>
                    ) : null}

                    <Field label="Trạng thái giao hàng" required>
                        <select className="form-control" value={deliveryStatus} onChange={(e) => setDeliveryStatus(e.target.value)}>
                            <option value="">--Chọn trạng thái giao hàng--</option>
                            {statusOptions.map((item) => (
                                <option key={item.value} value={item.value}>{item.label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Ghi chú">
                        <input className="form-control" value={note} onChange={(e) => setNote(e.target.value)} />
                    </Field>

                    {inspect ? (
                        <div className="ps-ttgh-inspect">
                            <div>
                                <b>Kiểm tra:</b>
                                {' '}
                                tìm thấy
                                {' '}
                                {inspect.found}
                                {inspect.missing?.length ? ` · thiếu ${inspect.missing.length} mã` : ''}
                            </div>
                            <ul>
                                {(inspect.by_delivery_status ?? []).map((item) => (
                                    <li key={`d-${item.value}`}>{item.label}: {item.count}</li>
                                ))}
                                {(inspect.by_reconciliation ?? []).map((item) => (
                                    <li key={`r-${item.value}`}>{item.label}: {item.count}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </div>

                <DialogFooter className="ps-wh-dialog-footer">
                    <button type="button" className="btn btn-primary" disabled={busy} onClick={submit}>
                        <i className="fa fa-save" />
                        {' '}
                        Cập nhật
                    </button>
                    <button type="button" className="btn btn-default" disabled={busy} onClick={runInspect}>
                        <i className="fa fa-calendar-check-o" />
                        {' '}
                        Kiểm tra
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
