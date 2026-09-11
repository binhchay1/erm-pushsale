import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useAppName } from '@/hooks/use-app-name';
import { useT } from '@/providers/I18nProvider';
import { apiRequest } from '@/lib/api';

/**
 * Cập nhật đối soát theo mã đơn — layout khớp htmk4 (CapNhatDoiSoatNoiBov2):
 * m-header + box-body → col-xs-4 table.tb-sp form.
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
    const appName = useAppName();
    const apiBase = `${actionApiBase}/reconciliation-bulk`;
    const [codeType, setCodeType] = useState('MHT');
    const [isGhtk, setIsGhtk] = useState(false);
    const [codes, setCodes] = useState(initialCodes);
    const [reconciliationStatus, setReconciliationStatus] = useState('');
    const [busy, setBusy] = useState(false);
    const [showGuide, setShowGuide] = useState(false);

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
        setShowGuide(false);
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
            <DialogContent className="ps-recon-bycode-dialog" showClose={false} aria-describedby={undefined}>
                <div className="m-header-wrap ps-recon-dialog-header">
                    <div className="m-header">
                        <div className="col-xs-6 form-group">
                            <span className="text">{t('operations.recon_bulk.by_code_title')}</span>
                            <button type="button" className="ps-recon-guide-toggle" onClick={() => setShowGuide((v) => !v)}>
                                {t('operations.recon_bulk.view_guide')}
                            </button>
                        </div>
                        <div className="col-xs-6 text-right">
                            <button type="button" className="btn-default ps-recon-close" onClick={() => onOpenChange(false)} aria-label={t('operations.bulk_update_by_code.close')}>
                                <i className="fa fa-close" />
                            </button>
                        </div>
                    </div>
                </div>

                <div className="box-body ps-recon-bycode-body">
                    <div className="row">
                        {showGuide ? (
                            <div className="col-xs-12 huong-dan">
                                <div className="notice">
                                    <b>{t('operations.recon_bulk.guide_title')}</b>
                                    <br />
                                    <span>- {t('operations.recon_bulk.guide_by_code_1')}</span>
                                </div>
                            </div>
                        ) : null}

                        <div className="col-xs-4">
                            <table className="table table-bordered tb-sp ps-recon-tb-sp">
                                <tbody>
                                    <tr>
                                        <td className="no-wrap">
                                            {t('operations.recon_bulk.code_type')}
                                            {' '}
                                            <span className="text-red">(*)</span>
                                        </td>
                                        <td>
                                            <select className="form-control txt-dotted" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                                                <option value="MHT">{t('operations.recon_bulk.code_type_mht', { app: appName })}</option>
                                                <option value="MGV">{t('operations.recon_bulk.code_type_mgv')}</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap" style={{ width: 50 }}>
                                            {t('operations.recon_bulk.codes')}
                                            {' '}
                                            <span className="text-red">(*)</span>
                                        </td>
                                        <td>
                                            <textarea
                                                className="form-control txt-dotted"
                                                rows={5}
                                                value={codes}
                                                onChange={(e) => setCodes(e.target.value)}
                                                placeholder={t('operations.recon_bulk.codes_placeholder')}
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap">
                                            {t('operations.recon_bulk.status')}
                                            {' '}
                                            <span className="text-red">(*)</span>
                                        </td>
                                        <td>
                                            <select
                                                className="form-control txt-dotted"
                                                value={reconciliationStatus}
                                                onChange={(e) => setReconciliationStatus(e.target.value)}
                                            >
                                                <option value="">{t('operations.recon_bulk.status_placeholder')}</option>
                                                {statusOptions.map((item) => (
                                                    <option key={item.value} value={item.value}>{item.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap" />
                                        <td>
                                            <label className="ps-recon-check">
                                                <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                                                {' '}
                                                {t('operations.recon_bulk.is_ghtk')}
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td>
                                            <button type="button" className="btn btn-sm btn-primary" disabled={busy} onClick={submit} style={{ float: 'left' }}>
                                                <i className="fa fa-save" />
                                                {' '}
                                                {t('operations.recon_bulk.update')}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="col-xs-8" />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
