import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/providers/I18nProvider';
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
 * FAB sliders xanh — Cập nhật đối soát theo mã đơn (accounting).
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
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [inspect, setInspect] = useState(null);

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
        setNote('');
        setIsGhtk(false);
        setCodeType('MHT');
        setInspect(null);
    }, [open, initialCodes]);

    const runInspect = async () => {
        if (!String(codes).trim()) {
            toast.error(t('operations.recon_bulk.codes_required'));
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${apiBase}/inspect`, {
                method: 'POST',
                body: { codes, code_type: codeType, is_ghtk: isGhtk },
            });
            setInspect(data);
            toast.success(
                t('operations.recon_bulk.inspect_ok', {
                    found: data.found,
                    missing: data.missing?.length
                        ? t('operations.recon_bulk.inspect_missing', { count: data.missing.length })
                        : '',
                }),
            );
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

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
                    <DialogTitle>{t('operations.recon_bulk.by_code_title')}</DialogTitle>
                </DialogHeader>

                <div className="ps-ttgh-form">
                    <Field label={t('operations.recon_bulk.code_type')} required>
                        <select className="form-control" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                            <option value="MHT">{t('operations.recon_bulk.code_type_mht')}</option>
                            <option value="MGV">{t('operations.recon_bulk.code_type_mgv')}</option>
                        </select>
                    </Field>

                    <Field label={t('operations.recon_bulk.codes')} required>
                        <textarea
                            className="form-control"
                            rows={6}
                            value={codes}
                            onChange={(e) => setCodes(e.target.value)}
                            placeholder={t('operations.recon_bulk.codes_placeholder')}
                        />
                    </Field>

                    {codeType === 'MGV' ? (
                        <label className="ps-ttgh-check">
                            <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                            {' '}
                            {t('operations.recon_bulk.is_ghtk')}
                        </label>
                    ) : null}

                    <Field label={t('operations.recon_bulk.status')} required>
                        <select className="form-control" value={reconciliationStatus} onChange={(e) => setReconciliationStatus(e.target.value)}>
                            <option value="">{t('operations.recon_bulk.status_placeholder')}</option>
                            {statusOptions.map((item) => (
                                <option key={item.value} value={item.value}>{item.label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label={t('operations.recon_bulk.note')}>
                        <input className="form-control" value={note} onChange={(e) => setNote(e.target.value)} />
                    </Field>

                    {inspect ? (
                        <div className="ps-ttgh-inspect">
                            <div>
                                <b>{t('operations.recon_bulk.inspect_label')}</b>
                                {' '}
                                {t('operations.recon_bulk.inspect_found', { count: inspect.found })}
                                {inspect.missing?.length
                                    ? ` · ${t('operations.recon_bulk.inspect_missing_short', { count: inspect.missing.length })}`
                                    : ''}
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
                        {t('operations.recon_bulk.update')}
                    </button>
                    <button type="button" className="btn btn-default" disabled={busy} onClick={runInspect}>
                        <i className="fa fa-calendar-check-o" />
                        {' '}
                        {t('operations.recon_bulk.inspect')}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
