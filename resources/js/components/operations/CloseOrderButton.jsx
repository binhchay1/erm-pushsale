import { useState } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, Loader2 } from 'lucide-react';
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
import { formatNumber } from '@/lib/format';

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

export function CloseOrderButton({ order, disabled }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [confirmInsufficient, setConfirmInsufficient] = useState(false);

    const canClose = !order.closedAt && !disabled;
    const hasInsufficientStock = order.hasInsufficientStock;
    const warnings = order.stockWarnings ?? [];

    const submit = () => {
        if (hasInsufficientStock && !confirmInsufficient) {
            setConfirmInsufficient(true);
            return;
        }

        setProcessing(true);
        router.post(
            `/sales/orders/${order.id}/close`,
            { confirm_insufficient_stock: confirmInsufficient },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setConfirmInsufficient(false);
                    toast.success('Đã chốt đơn — chuyển sang kho tạo vận đơn.');
                },
                onError: (errors) => {
                    if (errors.insufficient_stock || errors.stock) {
                        setConfirmInsufficient(true);
                        toast.error('Hàng trong kho không đủ — xác nhận để tiếp tục.');
                        return;
                    }
                    toast.error(errors.order ?? 'Không chốt được đơn.');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    if (!canClose) {
        return order.closedAt ? (
            <span className="inline-flex items-center gap-1 text-xs text-emerald-600">
                <CheckCircle2 className="size-3.5" />
                Đã chốt
            </span>
        ) : null;
    }

    return (
        <>
            <Button type="button" size="sm" variant="default" onClick={() => setOpen(true)}>
                Chốt đơn
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
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Chốt đơn {order.orderCode}?</DialogTitle>
                        <DialogDescription>
                            Đơn sẽ chuyển sang kho và hệ thống tự tạo vận đơn (nếu đã bật cấu hình).
                        </DialogDescription>
                    </DialogHeader>

                    {(hasInsufficientStock || confirmInsufficient) && (
                        <StockWarningBlock warnings={warnings} />
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Huỷ
                        </Button>
                        <Button
                            type="button"
                            variant={confirmInsufficient ? 'destructive' : 'default'}
                            onClick={submit}
                            disabled={processing}
                        >
                            {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                            {confirmInsufficient ? 'Vẫn chốt (thiếu hàng)' : 'Xác nhận chốt'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
