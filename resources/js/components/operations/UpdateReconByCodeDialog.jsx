import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/providers/I18nProvider';
import { apiRequest } from '@/lib/api';

function BoxedRow({ label, required = false, blankLabel = false, children }) {
    return (
        <div className="ps-ttgh-boxed-row">
            <div className={`ps-ttgh-boxed-label${blankLabel ? ' is-blank' : ''}`}>
                {blankLabel ? null : (
                    <>
                        <span>{label}</span>
                        {required ? <b className="text-danger">(*)</b> : null}
                    </>
                )}
            </div>
            <div className="ps-ttgh-boxed-control">{children}</div>
        </div>
    );
}

/**
 * FAB sliders xanh — Cập nhật đối soát theo mã đơn (accounting).
 * Pushsale boxed form: label left / control right.
 */
export function UpdateReconByCodeDialog({
    open,
    onOpenChange,
    actionApiBase,
    initialCodes = '',
    reconciliationStatuses = [],
    onDone,
}) {
    const t = useT();
    const apiBase = `${actionApiBase}/reconciliation-bulk`;
    const [codeType, setCodeType] = useState('MHT');
    const [isGhtk, setIsGhtk] = useState(false);
    const [codes, setCodes] = useState(initialCodes);
    const [reconciliationStatus, setReconciliationStatus] = useState('');
    const [busy, setBusy] = useState(false);

    const statusOptions = useMemo(
        () => (reconciliationStatuses ?? []).map((item) => ({
            value: String(item.value ?? ''),
            label: item.label ?? String(item.value ?? ''),
        })).filter((item) => item.value),
        [reconciliationStatuses],
    );

    useEffect(() => {
        if (!open) return;
        setCodes(initialCodes || '');
        setReconciliationStatus('');
        setIsGhtk(false);
        setCodeType('MHT');
    }, [open, initialCodes]);

    const submit = async () => {
        if (!String(codes).trim()) {
            toast.error(t('operations.recon_bulk.codes_required'));
            return;
        }
        if (!reconciliationStatus) {
            toast.error(t('operations.recon_bulk.status_required'));
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
                    reconciliation_status: reconciliationStatus,
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
            <DialogContent className="ps-wh-dialog ps-ttgh-by-code-dialog ps-ttgh-boxed" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>{t('operations.recon_bulk.by_code_title')}</DialogTitle>
                </DialogHeader>

                <div className="ps-ttgh-boxed-form">
                    <BoxedRow label={t('operations.recon_bulk.code_type')} required>
                        <select className="form-control" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                            <option value="MHT">{t('operations.recon_bulk.code_type_mht')}</option>
                            <option value="MGV">{t('operations.recon_bulk.code_type_mgv')}</option>
                        </select>
                    </BoxedRow>

                    <BoxedRow label={t('operations.recon_bulk.codes')} required>
                        <textarea
                            className="form-control"
                            rows={6}
                            value={codes}
                            onChange={(e) => setCodes(e.target.value)}
                            placeholder={t('operations.recon_bulk.codes_placeholder')}
                        />
                    </BoxedRow>

                    <BoxedRow label={t('operations.recon_bulk.status')} required>
                        <select className="form-control" value={reconciliationStatus} onChange={(e) => setReconciliationStatus(e.target.value)}>
                            <option value="">{t('operations.recon_bulk.status_placeholder')}</option>
                            {statusOptions.map((item) => (
                                <option key={item.value} value={item.value}>{item.label}</option>
                            ))}
                        </select>
                    </BoxedRow>

                    <BoxedRow blankLabel>
                        <label className="ps-ttgh-check">
                            <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                            {' '}
                            {t('operations.recon_bulk.is_ghtk')}
                        </label>
                    </BoxedRow>

                    <BoxedRow blankLabel>
                        <button type="button" className="btn btn-primary" disabled={busy} onClick={submit}>
                            <i className="fa fa-save" />
                            {' '}
                            {t('operations.recon_bulk.update')}
                        </button>
                    </BoxedRow>
                </div>
            </DialogContent>
        </Dialog>
    );
}
