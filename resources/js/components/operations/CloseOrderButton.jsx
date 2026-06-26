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

export function CloseOrderButton({ order, disabled }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [confirmInsufficient, setConfirmInsufficient] = useState(false);

    // Đồng nhất backend SaleOperationPolicy::canClose; fallback theo closedAt nếu prop thiếu.
    const canClose = (order.canClose ?? !order.closedAt) && !disabled;
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
                    toast.success(t('operations.close_success'));
                },
                onError: (errors) => {
                    if (errors.insufficient_stock || errors.stock) {
                        setConfirmInsufficient(true);
                        toast.error(t('operations.close_insufficient_error'));
                        return;
                    }
                    toast.error(errors.order ?? t('operations.close_failed'));
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    if (!canClose) {
        return order.closedAt ? (
            <span className="inline-flex items-center gap-1 text-xs text-emerald-600">
                <CheckCircle2 className="size-3.5" />
                {t('operations.closed')}
            </span>
        ) : null;
    }

    return (
        <>
            <Button type="button" size="sm" variant="default" onClick={() => setOpen(true)}>
                {t('operations.close_order')}
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
                        <DialogTitle>{t('operations.confirm_close_title', { code: order.orderCode })}</DialogTitle>
                        <DialogDescription>{t('operations.confirm_close_desc')}</DialogDescription>
                    </DialogHeader>

                    {(hasInsufficientStock || confirmInsufficient) && (
                        <StockWarningBlock warnings={warnings} />
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            {t('confirm_dialog.cancel_label')}
                        </Button>
                        <Button
                            type="button"
                            variant={confirmInsufficient ? 'destructive' : 'default'}
                            onClick={submit}
                            disabled={processing}
                        >
                            {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                            {confirmInsufficient ? t('operations.confirm_insufficient') : t('operations.confirm_close')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
