import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useT } from '@/providers/I18nProvider';
import { apiRequest, getCsrfToken } from '@/lib/api';

function formatAmount(value) {
    if (value === null || value === undefined || value === '') return '';
    const num = Number(value);
    if (!Number.isFinite(num)) return String(value);
    return num.toLocaleString('vi-VN');
}

/**
 * Cập nhật đối soát Excel — layout khớp htmk3 (CapNhatDoiSoatExcel):
 * m-header + box-body → col-xs-4 table.tb-sp | col-xs-8 filters + history.
 */
export function UpdateReconExcelDialog({
    open,
    onOpenChange,
    actionApiBase,
    onDone,
}) {
    const t = useT();
    const apiBase = `${actionApiBase}/reconciliation-bulk`;
    const fileRef = useRef(null);
    const [isGhtk, setIsGhtk] = useState(false);
    const [matchTotal, setMatchTotal] = useState(false);
    const [matchCod, setMatchCod] = useState(false);
    const [updateDsnbIfMatch, setUpdateDsnbIfMatch] = useState(false);
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [showGuide, setShowGuide] = useState(false);
    const [batch, setBatch] = useState(null);
    const [counts, setCounts] = useState({ total: 0, processed: 0, pending: 0, success: 0, error: 0 });
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [filters, setFilters] = useState({ search: '', process_status: '', result_status: '', page: 1 });

    const applyOptions = () => ({
        match_total: matchTotal,
        match_cod: matchCod,
        update_dsnb_if_match: updateDsnbIfMatch,
    });

    const loadHistory = async (next = {}) => {
        const query = { ...filters, ...next, batch_id: batch?.id };
        const params = new URLSearchParams();
        Object.entries(query).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) params.set(key, String(value));
        });
        const data = await apiRequest(`${apiBase}/history?${params.toString()}`);
        if (data.batch) setBatch(data.batch);
        setCounts(data.counts || counts);
        setRows(data.rows?.data || []);
        setMeta(data.rows?.meta || null);
        setFilters((old) => ({ ...old, ...next }));
        const opts = data.batch?.options || data.batch?.meta?.options;
        if (opts) {
            setMatchTotal(Boolean(opts.match_total));
            setMatchCod(Boolean(opts.match_cod));
            setUpdateDsnbIfMatch(Boolean(opts.update_dsnb_if_match));
        }
        if (typeof data.batch?.is_ghtk === 'boolean') setIsGhtk(data.batch.is_ghtk);
    };

    useEffect(() => {
        if (!open) return;
        setIsGhtk(false);
        setMatchTotal(false);
        setMatchCod(false);
        setUpdateDsnbIfMatch(false);
        setFile(null);
        setBusy(false);
        setShowGuide(false);
        if (fileRef.current) fileRef.current.value = '';
        (async () => {
            try {
                await loadHistory({ page: 1 });
            } catch {
                setBatch(null);
                setRows([]);
                setCounts({ total: 0, processed: 0, pending: 0, success: 0, error: 0 });
            }
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const downloadTemplate = () => {
        window.location.href = `${apiBase}/template`;
    };

    const downloadHistory = () => {
        const header = ['order_code', 'amount', 'message', 'process_status', 'result_status', 'processed_at'];
        const lines = [header.join(',')];
        rows.forEach((row) => {
            lines.push([
                row.order_code || '',
                row.amount ?? '',
                `"${String(row.message || '').replace(/"/g, '""')}"`,
                row.process_status || '',
                row.result_status || '',
                row.processed_at || '',
            ].join(','));
        });
        const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
        const href = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = href;
        a.download = `doi-soat-excel-${batch?.id || 'history'}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(href);
    };

    const upload = async () => {
        if (!file) {
            toast.error(t('operations.recon_bulk.file_required'));
            return;
        }
        setBusy(true);
        try {
            const form = new FormData();
            form.append('file', file);
            form.append('is_ghtk', isGhtk ? '1' : '0');
            form.append('match_total', matchTotal ? '1' : '0');
            form.append('match_cod', matchCod ? '1' : '0');
            form.append('update_dsnb_if_match', updateDsnbIfMatch ? '1' : '0');
            const response = await fetch(`${apiBase}/upload`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: form,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Upload failed');
            }
            setBatch(data.batch);
            setCounts(data.counts || {});
            toast.success(t('operations.recon_bulk.upload_ok'));
            await loadHistory({ page: 1 });
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const clearUpload = async () => {
        if (!batch?.id) return;
        setBusy(true);
        try {
            await apiRequest(`${apiBase}/batches/${batch.id}/clear`, { method: 'POST', body: {} });
            setBatch(null);
            setRows([]);
            setCounts({ total: 0, processed: 0, pending: 0, success: 0, error: 0 });
            toast.success(t('operations.recon_bulk.clear_ok'));
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const apply = async () => {
        if (!batch?.id) {
            toast.error(t('operations.recon_bulk.apply_need_upload'));
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${apiBase}/batches/${batch.id}/apply`, {
                method: 'POST',
                body: applyOptions(),
            });
            setBatch(data.batch);
            setCounts(data.counts || {});
            if ((data.counts?.error || 0) > 0) {
                toast.warning(t('operations.recon_bulk.apply_partial', {
                    success: data.counts.success,
                    error: data.counts.error,
                }));
            } else {
                toast.success(t('operations.recon_bulk.apply_ok', { count: data.counts?.success ?? 0 }));
            }
            await loadHistory({ page: 1 });
            onDone?.();
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const fileLabel = file?.name || t('operations.recon_bulk.choose_file_placeholder');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-recon-excel-dialog" showClose={false} aria-describedby={undefined}>
                <div className="m-header-wrap ps-recon-dialog-header">
                    <div className="m-header">
                        <div className="col-xs-6 form-group">
                            <span className="text">{t('operations.recon_bulk.excel_title')}</span>
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

                <div className="box-body ps-recon-excel-body">
                    <div className="row">
                        {showGuide ? (
                            <div className="col-xs-12 huong-dan">
                                <div className="notice">
                                    <b>{t('operations.recon_bulk.guide_title')}</b>
                                    <br />
                                    <span>- {t('operations.recon_bulk.guide_excel_1')}</span>
                                    <br />
                                    <span>- {t('operations.recon_bulk.guide_excel_2')}</span>
                                    <br />
                                    <span className="ps-recon-guide-emph">- {t('operations.recon_bulk.guide_excel_3')}</span>
                                </div>
                            </div>
                        ) : null}

                        <div className="col-xs-4 ps-recon-excel-side">
                            <table className="table table-bordered tb-sp ps-recon-tb-sp">
                                <tbody>
                                    <tr>
                                        <td className="no-wrap">{t('operations.recon_bulk.download_template')}</td>
                                        <td>
                                            <button type="button" className="btn btn-link ps-recon-link-btn" onClick={downloadTemplate}>
                                                <i className="fa fa-cloud-download" />
                                                {t('operations.recon_bulk.download_template_btn')}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap">{t('operations.recon_bulk.choose_file')}</td>
                                        <td>
                                            <div className="ps-recon-file-upload">
                                                <label className="form-control ps-recon-file-label" htmlFor="ps-recon-excel-file">
                                                    {fileLabel}
                                                </label>
                                                <input
                                                    id="ps-recon-excel-file"
                                                    ref={fileRef}
                                                    type="file"
                                                    className="hidden"
                                                    accept=".csv,.xls,.xlsx,.txt"
                                                    onChange={(e) => setFile(e.target.files?.[0] || null)}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn btn-default btn-square btn-icon ps-recon-file-icon"
                                                    title={t('operations.recon_bulk.choose_file')}
                                                    onClick={() => fileRef.current?.click()}
                                                >
                                                    <i className="fa fa-cloud-upload" />
                                                </button>
                                            </div>
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
                                        <td className="no-wrap" />
                                        <td>
                                            <label className="ps-recon-check">
                                                <input type="checkbox" checked={matchTotal} onChange={(e) => setMatchTotal(e.target.checked)} />
                                                {' '}
                                                {t('operations.recon_bulk.match_total')}
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap" />
                                        <td>
                                            <label className="ps-recon-check">
                                                <input type="checkbox" checked={matchCod} onChange={(e) => setMatchCod(e.target.checked)} />
                                                {' '}
                                                {t('operations.recon_bulk.match_cod')}
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="no-wrap" />
                                        <td>
                                            <label className="ps-recon-check">
                                                <input type="checkbox" checked={updateDsnbIfMatch} onChange={(e) => setUpdateDsnbIfMatch(e.target.checked)} />
                                                {' '}
                                                {t('operations.recon_bulk.update_dsnb_if_match')}
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td>
                                            <button type="button" className="btn btn-sm btn-primary" disabled={busy} onClick={upload}>
                                                <i className="fa fa-cloud-upload" />
                                                {' '}
                                                {t('operations.recon_bulk.step_upload')}
                                            </button>
                                            <button type="button" className="btn btn-sm ps-recon-clear-btn" disabled={busy || !batch} onClick={clearUpload} title={t('operations.recon_bulk.clear')}>
                                                <i className="fa fa-trash" />
                                                {' '}
                                                {t('operations.recon_bulk.clear')}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr className="smd0">
                                        <td className="no-wrap">{t('operations.recon_bulk.stat_total')}</td>
                                        <td className="text-primary">{counts.total}</td>
                                    </tr>
                                    <tr className="smd1">
                                        <td className="no-wrap">{t('operations.recon_bulk.stat_processed')}</td>
                                        <td className="text-primary">{counts.processed}</td>
                                    </tr>
                                    <tr className="smd2">
                                        <td className="no-wrap">{t('operations.recon_bulk.stat_pending')}</td>
                                        <td>{counts.pending}</td>
                                    </tr>
                                    <tr className="smd3">
                                        <td className="no-wrap">{t('operations.recon_bulk.stat_success')}</td>
                                        <td className="text-success">{counts.success}</td>
                                    </tr>
                                    <tr className="smd4">
                                        <td className="no-wrap">{t('operations.recon_bulk.stat_error')}</td>
                                        <td className="text-danger">{counts.error}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div className="text-right ps-recon-excel-apply-wrap">
                                <button type="button" className="btn btn-sm btn-primary" disabled={busy || !batch} onClick={apply}>
                                    <i className="fa fa-save" />
                                    {' '}
                                    {t('operations.recon_bulk.step_apply')}
                                </button>
                            </div>
                        </div>

                        <div className="col-xs-8 ps-recon-excel-main">
                            <div className="row ps-recon-excel-filters">
                                <div className="col-xs-3">
                                    <input
                                        className="form-control"
                                        placeholder={t('operations.recon_bulk.search_order')}
                                        value={filters.search}
                                        onChange={(e) => setFilters((old) => ({ ...old, search: e.target.value }))}
                                    />
                                </div>
                                <div className="col-xs-3">
                                    <select
                                        className="form-control"
                                        value={filters.process_status}
                                        onChange={(e) => setFilters((old) => ({ ...old, process_status: e.target.value }))}
                                    >
                                        <option value="">{t('operations.recon_bulk.filter_process')}</option>
                                        <option value="pending">{t('operations.recon_bulk.process_pending')}</option>
                                        <option value="processed">{t('operations.recon_bulk.process_done')}</option>
                                    </select>
                                </div>
                                <div className="col-xs-3">
                                    <select
                                        className="form-control"
                                        value={filters.result_status}
                                        onChange={(e) => setFilters((old) => ({ ...old, result_status: e.target.value }))}
                                    >
                                        <option value="">{t('operations.recon_bulk.filter_result')}</option>
                                        <option value="success">{t('operations.recon_bulk.result_success')}</option>
                                        <option value="error">{t('operations.recon_bulk.result_error')}</option>
                                        <option value="pending">{t('operations.recon_bulk.result_pending')}</option>
                                    </select>
                                </div>
                                <div className="col-xs-3 form-group">
                                    <button type="button" className="btn btn-sm btn-primary mr15" disabled={busy} onClick={() => loadHistory({ page: 1 })}>
                                        <i className="fa fa-search" />
                                        {' '}
                                        {t('operations.recon_bulk.search')}
                                    </button>
                                    <button type="button" className="btn btn-sm btn-default" disabled={busy || rows.length === 0} onClick={downloadHistory}>
                                        <i className="fa fa-cloud-download" />
                                        {' '}
                                        {t('operations.recon_bulk.download')}
                                    </button>
                                </div>
                            </div>

                            <div className="ps-recon-excel-history">
                                {rows.length === 0 ? (
                                    <div className="ps-recon-excel-history-empty">{t('operations.recon_bulk.empty_history')}</div>
                                ) : rows.map((row) => (
                                    <div className="ps-recon-excel-history-row" key={row.id}>
                                        <span className="ps-recon-excel-icon">
                                            {row.result_status === 'success' ? <i className="fa fa-check-circle text-success" /> : null}
                                            {row.result_status === 'error' ? <i className="fa fa-times-circle text-danger" /> : null}
                                            {row.result_status === 'pending' ? <i className="fa fa-clock-o text-muted" /> : null}
                                        </span>
                                        <span className="ps-recon-order-link">{row.order_code || '—'}</span>
                                        <span className="ps-recon-amount">{formatAmount(row.amount)}</span>
                                        <span className="ps-recon-result">
                                            {row.message
                                                || row.reconciliation_status_label
                                                || (row.process_status === 'processed'
                                                    ? t('operations.recon_bulk.process_done')
                                                    : t('operations.recon_bulk.process_pending'))}
                                        </span>
                                        <span className="ps-recon-processed">
                                            {row.processed_at
                                                ? `${t('operations.recon_bulk.process_done')} ${row.processed_at}`
                                                : ''}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <div className="ps-recon-excel-footer">
                                <span className="text-danger">* {t('operations.recon_bulk.history_limit')}</span>
                                {meta ? (
                                    <span className="ps-recon-pager">
                                        {meta.from || 0}
                                        {' - '}
                                        {meta.to || 0}
                                        {' / '}
                                        {(meta.total || 0).toLocaleString('vi-VN')}
                                        {' '}
                                        <button type="button" className="btn btn-default btn-sm" disabled={busy || (meta.current_page || 1) <= 1} onClick={() => loadHistory({ page: (meta.current_page || 1) - 1 })}>
                                            <i className="fa fa-chevron-left" />
                                        </button>
                                        <button type="button" className="btn btn-default btn-sm" disabled={busy || (meta.current_page || 1) >= (meta.last_page || 1)} onClick={() => loadHistory({ page: (meta.current_page || 1) + 1 })}>
                                            <i className="fa fa-chevron-right" />
                                        </button>
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
