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

export function CloseOrderButton({ order, disabled }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const canClose = !order.closedAt && !disabled;

    const submit = () => {
        setProcessing(true);
        router.post(
            `/sales/orders/${order.id}/close`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    toast.success('Đã chốt đơn — chuyển sang kho tạo vận đơn.');
                },
                onError: (errors) => {
                    toast.error(errors.order ?? 'Không chốt được đơn.');
                },
                onFinish: () => setProcessing(false),
            }
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
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Chốt đơn {order.orderCode}?</DialogTitle>
                        <DialogDescription>
                            Đơn sẽ chuyển sang kho và hệ thống tự tạo vận đơn GHTK (nếu đã bật cấu hình).
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Huỷ
                        </Button>
                        <Button type="button" onClick={submit} disabled={processing}>
                            {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                            Xác nhận chốt
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
