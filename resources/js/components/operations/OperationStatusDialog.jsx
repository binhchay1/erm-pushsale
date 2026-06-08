import { router } from '@inertiajs/react';
import { Loader2, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { formatNumber } from '@/lib/format';

const QUICK_NO_ANSWER = {
    value: 'no_answer_auto',
    label: 'Gọi không nghe máy (tự tăng lần gọi)',
    group: 'Gọi không nghe máy',
};

function StockWarningBlock({ warnings = [] }) {
    const insufficient = warnings.filter((w) => !w.sufficient);

    if (!insufficient.length) {
        return null;
    }

    return (
        <div className="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
            <p className="font-semibold">Hàng trong kho không đủ</p>
            <ul className="mt-2 list-inside list-disc text-xs">
                {insufficient.map((w) => (
                    <li key={w.productId}>
                        {w.productName}: cần {formatNumber(w.required)}, tồn {formatNumber(w.available)}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function OperationStatusDialog({ order, options = [] }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState('');
    const [nextAt, setNextAt] = useState('');
    const [note, setNote] = useState('');
    const [confirmInsufficient, setConfirmInsufficient] = useState(false);

    const groupedOptions = useMemo(() => {
        const all = [QUICK_NO_ANSWER, ...options];
        const groups = {};

        all.forEach((item) => {
            groups[item.group] = groups[item.group] ?? [];
            groups[item.group].push(item);
        });

        return groups;
    }, [options]);

    if (!order?.canChangeStatus) {
        return null;
    }

    const needsSchedule = result === 'callback_scheduled';
    const isClosing = result === 'closed_success';
    const showStockWarning = isClosing && order.hasInsufficientStock;

    const submit = () => {
        if (!result) {
            toast.error('Chọn kết quả tác nghiệp.');
            return;
        }

        if (needsSchedule && !nextAt) {
            toast.error('Chọn thời gian hẹn gọi lại.');
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
                    setOpen(false);
                    setResult('');
                    setNextAt('');
                    setNote('');
                    setConfirmInsufficient(false);
                    toast.success('Đã cập nhật trạng thái.');
                },
                onError: (errors) => {
                    if (errors.insufficient_stock || errors.stock) {
                        setConfirmInsufficient(true);
                        toast.error('Hàng trong kho không đủ — xác nhận để tiếp tục.');
                        return;
                    }

                    const message =
                        errors.operation_result ??
                        errors.next_operation_at ??
                        errors.order ??
                        'Không cập nhật được trạng thái.';
                    toast.error(message);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Button type="button" size="sm" variant="secondary" className="gap-1" onClick={() => setOpen(true)}>
                <RefreshCw className="size-3.5" />
                Chuyển trạng thái
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    setOpen(value);
                    if (!value) {
                        setConfirmInsufficient(false);
                    }
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Chuyển trạng thái — {order.orderCode}</DialogTitle>
                        <DialogDescription>
                            {order.customerName} · {order.customerPhone}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor={`status-${order.id}`}>Kết quả tác nghiệp</Label>
                            <select
                                id={`status-${order.id}`}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={result}
                                onChange={(e) => {
                                    setResult(e.target.value);
                                    setConfirmInsufficient(false);
                                }}
                            >
                                <option value="">— Chọn kết quả —</option>
                                {Object.entries(groupedOptions).map(([group, items]) => (
                                    <optgroup key={group} label={group}>
                                        {items.map((item) => (
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
                                <Label htmlFor={`next-${order.id}`}>Hẹn gọi lại</Label>
                                <Input
                                    id={`next-${order.id}`}
                                    type="datetime-local"
                                    value={nextAt}
                                    onChange={(e) => setNextAt(e.target.value)}
                                />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor={`note-${order.id}`}>Ghi chú (tùy chọn)</Label>
                            <Input
                                id={`note-${order.id}`}
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder="VD: Khách bận, gọi lại 15:00"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Huỷ
                        </Button>
                        <Button
                            type="button"
                            variant={showStockWarning && confirmInsufficient ? 'destructive' : 'default'}
                            onClick={submit}
                            disabled={processing}
                        >
                            {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                            {showStockWarning && !confirmInsufficient
                                ? 'Tiếp tục'
                                : showStockWarning && confirmInsufficient
                                  ? 'Vẫn chốt (thiếu hàng)'
                                  : 'Lưu trạng thái'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
