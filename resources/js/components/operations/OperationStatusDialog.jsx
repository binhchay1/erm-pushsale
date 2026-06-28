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

export function OperationStatusDialog({ order, options = [] }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState('');
    const [nextAt, setNextAt] = useState('');
    const [note, setNote] = useState('');
    const [confirmInsufficient, setConfirmInsufficient] = useState(false);

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

    if (!order?.canChangeStatus) {
        return null;
    }

    const needsSchedule = result === 'callback_scheduled';
    const isClosing = result === 'closed_success';
    const showStockWarning = isClosing && order.hasInsufficientStock;

    const submit = () => {
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
                    setOpen(false);
                    setResult('');
                    setNextAt('');
                    setNote('');
                    setConfirmInsufficient(false);
                    toast.success(t('operations.status_dialog.update_success'));
                },
                onError: (errors) => {
                    if (errors.insufficient_stock || errors.stock) {
                        setConfirmInsufficient(true);
                        toast.error(t('operations.close_insufficient_error'));
                        return;
                    }

                    const message =
                        errors.operation_result ??
                        errors.next_operation_at ??
                        errors.order ??
                        t('operations.status_dialog.update_failed');
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
                {t('operations.status_dialog.title')}
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
                        <DialogTitle>
                            {t('operations.status_dialog.title_with_code', { code: order.orderCode })}
                        </DialogTitle>
                        <DialogDescription>
                            {order.customerName} · {order.customerPhone}
                        </DialogDescription>
                    </DialogHeader>

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
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            {t('operations.status_dialog.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant={showStockWarning && confirmInsufficient ? 'destructive' : 'default'}
                            onClick={submit}
                            disabled={processing || !result || (needsSchedule && !nextAt)}
                            title={
                                !result
                                    ? t('operations.status_dialog.select_result')
                                    : needsSchedule && !nextAt
                                      ? t('operations.status_dialog.select_schedule')
                                      : undefined
                            }
                        >
                            {processing && <Loader2 className="mr-1 size-4 animate-spin" />}
                            {showStockWarning && !confirmInsufficient
                                ? t('operations.status_dialog.continue')
                                : showStockWarning && confirmInsufficient
                                  ? t('operations.status_dialog.confirm_insufficient')
                                  : t('operations.status_dialog.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
