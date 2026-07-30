import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiRequest, getCsrfToken } from '@/lib/api';

/**
 * FAB truck cam — Cập nhật TTGH + mã giao vận bằng Excel.
 * Header/template linh hoạt theo backend; history lọc theo batch hiện tại.
 */
export function UpdateDeliveryStatusExcelDialog({
    open,
    onOpenChange,
    actionApiBase,
    onDone,
}) {
    const apiBase = `${actionApiBase}/delivery-status-bulk`;
    const [isGhtk, setIsGhtk] = useState(false);
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [batch, setBatch] = useState(null);
    const [counts, setCounts] = useState({ total: 0, processed: 0, pending: 0, success: 0, error: 0 });
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [filters, setFilters] = useState({ search: '', process_status: '', result_status: '', page: 1 });

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
    };

    useEffect(() => {
        if (!open) return;
        setIsGhtk(false);
        setFile(null);
        setBusy(false);
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

    const upload = async () => {
        if (!file) {
            toast.error('Chọn file Excel trước.');
            return;
        }
        setBusy(true);
        try {
            const body = new FormData();
            body.append('file', file);
            body.append('is_ghtk', isGhtk ? '1' : '0');
            const response = await fetch(`${apiBase}/upload`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Upload thất bại.');
            setBatch(data.batch);
            setCounts(data.counts || {});
            toast.success(`Đã upload ${data.counts?.total ?? 0} dòng.`);
            const params = new URLSearchParams({ page: '1', batch_id: String(data.batch.id) });
            const hist = await apiRequest(`${apiBase}/history?${params.toString()}`);
            setRows(hist.rows?.data || []);
            setMeta(hist.rows?.meta || null);
            setFilters((old) => ({ ...old, page: 1 }));
            setFile(null);
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
            toast.success('Đã xóa dữ liệu upload.');
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    const apply = async () => {
        if (!batch?.id) {
            toast.error('Upload file trước khi cập nhật.');
            return;
        }
        setBusy(true);
        try {
            const data = await apiRequest(`${apiBase}/batches/${batch.id}/apply`, { method: 'POST', body: {} });
            setBatch(data.batch);
            setCounts(data.counts || {});
            if ((data.counts?.error || 0) > 0) toast.warning(`Xong: thành công ${data.counts.success}, lỗi ${data.counts.error}`);
            else toast.success(`Đã cập nhật ${data.counts?.success ?? 0} đơn.`);
            await loadHistory({ page: 1 });
            onDone?.();
        } catch (error) {
            toast.error(error.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-wh-dialog ps-ttgh-excel-dialog wide" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Cập nhật trạng thái giao hàng Excel</DialogTitle>
                </DialogHeader>

                <div className="ps-ttgh-excel-notice">
                    <b>Ghi chú:</b>
                    {' '}
                    Bắt buộc có mã Pushsale. Mã giao vận / trạng thái có thể để trống tùy dòng; trạng thái lấy theo cột “Trạng thái cập nhật” (value hoặc tên).
                    {' '}
                    Lịch sử tối đa
                    {' '}
                    {2000}
                    {' '}
                    dòng / lần upload.
                </div>

                <div className="ps-ttgh-excel-layout">
                    <aside className="ps-ttgh-excel-side">
                        <div className="form-group">
                            <span className="h-label">Tải mẫu</span>
                            <button type="button" className="btn btn-link" onClick={downloadTemplate}>
                                <i className="fa fa-cloud-download" />
                                {' '}
                                Tải mẫu Excel
                            </button>
                        </div>
                        <div className="form-group">
                            <span className="h-label">Chọn file</span>
                            <input
                                type="file"
                                className="form-control"
                                accept=".csv,.xls,.xlsx,.txt"
                                onChange={(e) => setFile(e.target.files?.[0] || null)}
                            />
                        </div>
                        <label className="ps-ttgh-check">
                            <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                            {' '}
                            Đơn vị GH là: Giao hàng tiết kiệm
                        </label>
                        <div className="ps-ttgh-excel-actions">
                            <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={upload}>
                                <i className="fa fa-cloud-upload" />
                                {' '}
                                1. Upload
                            </button>
                            <button type="button" className="btn btn-danger btn-sm" disabled={busy || !batch} onClick={clearUpload}>
                                <i className="fa fa-trash" />
                                {' '}
                                Xóa
                            </button>
                        </div>

                        <table className="table table-bordered table-condensed ps-ttgh-excel-stats">
                            <tbody>
                                <tr><td>Tổng</td><td>{counts.total}</td></tr>
                                <tr><td>Đã xử lý</td><td className="text-primary">{counts.processed}</td></tr>
                                <tr><td>Chưa xử lý</td><td>{counts.pending}</td></tr>
                                <tr><td>Thành công</td><td className="text-success">{counts.success}</td></tr>
                                <tr><td>Lỗi</td><td className="text-danger">{counts.error}</td></tr>
                            </tbody>
                        </table>

                        <button type="button" className="btn btn-primary" disabled={busy || !batch} onClick={apply}>
                            <i className="fa fa-save" />
                            {' '}
                            2. Cập nhật
                        </button>
                    </aside>

                    <section className="ps-ttgh-excel-main">
                        <div className="ps-ttgh-excel-filters">
                            <input
                                className="form-control"
                                placeholder="Mã đơn"
                                value={filters.search}
                                onChange={(e) => setFilters((old) => ({ ...old, search: e.target.value }))}
                            />
                            <select
                                className="form-control"
                                value={filters.process_status}
                                onChange={(e) => setFilters((old) => ({ ...old, process_status: e.target.value }))}
                            >
                                <option value="">--Trạng thái XL--</option>
                                <option value="pending">Chưa xử lý</option>
                                <option value="processed">Đã xử lý</option>
                            </select>
                            <select
                                className="form-control"
                                value={filters.result_status}
                                onChange={(e) => setFilters((old) => ({ ...old, result_status: e.target.value }))}
                            >
                                <option value="">--Kết quả XL--</option>
                                <option value="success">Thành công</option>
                                <option value="error">Lỗi</option>
                                <option value="pending">Chờ</option>
                            </select>
                            <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => loadHistory({ page: 1 })}>
                                <i className="fa fa-search" />
                                {' '}
                                Tìm kiếm
                            </button>
                        </div>

                        <table className="table table-bordered table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th style={{ width: 36 }} />
                                    <th>Mã đơn</th>
                                    <th>Trạng thái</th>
                                    <th>Xử lý</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr><td colSpan={4}>Chưa có dữ liệu upload.</td></tr>
                                ) : rows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="text-center">
                                            {row.result_status === 'success' ? <i className="fa fa-check-circle text-success" /> : null}
                                            {row.result_status === 'error' ? <i className="fa fa-times-circle text-danger" /> : null}
                                            {row.result_status === 'pending' ? <i className="fa fa-clock-o text-muted" /> : null}
                                        </td>
                                        <td>{row.order_code || '—'}</td>
                                        <td>{row.delivery_status_label || '—'}</td>
                                        <td>
                                            <div>{row.process_status === 'processed' ? 'Đã xử lý' : 'Chưa xử lý'}</div>
                                            <div className="text-primary small">{row.processed_at || row.message || ''}</div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="ps-ttgh-excel-footer">
                            <span className="text-danger">* Danh sách lịch sử tối đa 2.000</span>
                            {meta ? (
                                <span>
                                    {meta.from || 0}
                                    {' - '}
                                    {meta.to || 0}
                                    {' / '}
                                    {meta.total || 0}
                                    {' '}
                                    <button type="button" className="btn btn-link btn-xs" disabled={busy || (meta.current_page || 1) <= 1} onClick={() => loadHistory({ page: (meta.current_page || 1) - 1 })}>
                                        <i className="fa fa-chevron-left" />
                                    </button>
                                    <button type="button" className="btn btn-link btn-xs" disabled={busy || (meta.current_page || 1) >= (meta.last_page || 1)} onClick={() => loadHistory({ page: (meta.current_page || 1) + 1 })}>
                                        <i className="fa fa-chevron-right" />
                                    </button>
                                </span>
                            ) : null}
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
