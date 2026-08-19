import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { getCsrfToken } from '@/lib/api';
import { useT } from '@/providers/I18nProvider';

function CheckOption({ checked, onChange, children }) {
    return (
        <label className="ps-reallocate-check">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>{children}</span>
        </label>
    );
}

export function CustomerReallocateDialog({
    open,
    onOpenChange,
    selectedIds = [],
    sales = [],
    operationStages = [],
    actionBaseUrl,
    onDone,
}) {
    const t = useT();
    const [saleId, setSaleId] = useState('');
    const [operationStage, setOperationStage] = useState('');
    const [hideLocked, setHideLocked] = useState(true);
    const [hideNotReceiving, setHideNotReceiving] = useState(true);
    const [deleteHistory, setDeleteHistory] = useState(false);
    const [deleteMessages, setDeleteMessages] = useState(false);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }
        setSaleId('');
        setOperationStage('');
        setHideLocked(true);
        setHideNotReceiving(true);
        setDeleteHistory(false);
        setDeleteMessages(false);
    }, [open]);

    const visibleSales = useMemo(() => (sales ?? []).filter((sale) => {
        if (hideLocked && sale.isLocked) return false;
        if (hideNotReceiving && sale.receiveData === false) return false;
        return true;
    }), [sales, hideLocked, hideNotReceiving]);

    useEffect(() => {
        if (saleId && !visibleSales.some((sale) => String(sale.value) === String(saleId))) {
            setSaleId('');
        }
    }, [saleId, visibleSales]);

    const submit = async () => {
        if (!selectedIds.length) {
            toast.warning(t('pages.customer_profile.reallocate_need_selection'));
            return;
        }
        if (!saleId) {
            toast.warning(t('pages.customer_profile.reallocate_need_sale'));
            return;
        }

        setProcessing(true);
        try {
            const response = await fetch(`${actionBaseUrl}/bulk/reallocate-now`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ids: selectedIds.map((id) => Number(id)).filter((id) => id > 0),
                    sale_user_id: Number(saleId),
                    hide_locked_sales: hideLocked,
                    hide_sales_not_receiving: hideNotReceiving,
                    delete_operation_history: deleteHistory,
                    delete_internal_messages: deleteMessages,
                    operation_stage: operationStage || null,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || t('pages.customer_profile.reallocate_failed'));
            }
            toast.success(data.message || t('pages.customer_profile.reallocate_submit'));
            onOpenChange(false);
            onDone?.();
        } catch (error) {
            toast.error(error.message || t('pages.customer_profile.reallocate_failed'));
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !processing && onOpenChange(next)}>
            <DialogContent className="ps-sale-dialog ps-reallocate-dialog" aria-describedby={undefined}>
                <DialogHeader className="ps-sale-dialog-header">
                    <DialogTitle>{t('pages.customer_profile.reallocate_title')}</DialogTitle>
                </DialogHeader>
                <div className="ps-reallocate-body">
                    <select
                        className="form-control"
                        value={saleId}
                        onChange={(event) => setSaleId(event.target.value)}
                    >
                        <option value="">{t('pages.customer_profile.reallocate_pick_sale')}</option>
                        {visibleSales.map((sale) => (
                            <option key={sale.value} value={sale.value}>
                                {sale.label}
                                {sale.email ? ` (${sale.email})` : ''}
                            </option>
                        ))}
                    </select>
                    {!visibleSales.length ? (
                        <div className="small-tip">{t('pages.customer_profile.reallocate_empty_sales')}</div>
                    ) : null}

                    <select
                        className="form-control"
                        value={operationStage}
                        onChange={(event) => setOperationStage(event.target.value)}
                    >
                        <option value="">{t('pages.customer_profile.reallocate_keep_stage')}</option>
                        {(operationStages ?? []).map((stage) => (
                            <option key={stage.value} value={stage.value}>{stage.label}</option>
                        ))}
                    </select>

                    <div className="ps-reallocate-options">
                        <CheckOption checked={hideLocked} onChange={setHideLocked}>
                            {t('pages.customer_profile.reallocate_hide_locked')}
                        </CheckOption>
                        <CheckOption checked={hideNotReceiving} onChange={setHideNotReceiving}>
                            {t('pages.customer_profile.reallocate_hide_not_receiving')}
                        </CheckOption>
                        <CheckOption checked={deleteHistory} onChange={setDeleteHistory}>
                            {t('pages.customer_profile.reallocate_delete_history')}
                        </CheckOption>
                        <CheckOption checked={deleteMessages} onChange={setDeleteMessages}>
                            {t('pages.customer_profile.reallocate_delete_messages')}
                        </CheckOption>
                    </div>

                    <div className="ps-sale-dialog-footer">
                        <button type="button" className="btn btn-default" disabled={processing} onClick={() => onOpenChange(false)}>
                            {t('confirm_dialog.cancel_label')}
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            disabled={processing || !saleId || !selectedIds.length}
                            onClick={submit}
                        >
                            <i className="fa fa-retweet" /> {processing ? t('pages.customer_profile.reallocate_processing') : t('pages.customer_profile.reallocate_submit')}
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
