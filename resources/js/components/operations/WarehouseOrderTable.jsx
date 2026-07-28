import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { ShippingOrderDetailDialog } from '@/components/shipping/ShippingOrderDetailDialog';
import { WarehouseActionDialogs } from '@/components/operations/WarehouseActionDialogs';
import { OrderMoneyBreakdown, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { apiPost, apiRequest, getCsrfToken } from '@/lib/api';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';
import { useConfirm } from '@/hooks/use-confirm';

const statusTone = {
    waiting_waybill: 'ttgh1',
    posted: 'ttgh20',
    picking_up: 'ttgh23',
    picked_up: 'ttgh21',
    cannot_pickup: 'ttgh22',
    delivering: 'ttgh30',
    cannot_deliver: 'ttgh33',
    redelivery: 'ttgh34',
    delivered: 'ttgh31',
    delivery_complete: 'ttgh31',
    partial_delivery: 'ttgh35',
    paid: 'ttgh32',
    returning: 'ttgh40',
    refund: 'ttgh40',
    returned: 'ttgh41',
    cancel_waybill: 'ttgh4',
    cancel_closing: 'ttgh5',
    compensation: 'ttgh50',
};


function filenameFromDisposition(header, fallback) {
    const match = String(header || '').match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
    return decodeURIComponent(match?.[1] || match?.[2] || fallback);
}

async function postJson(url, body = {}) {
    return apiRequest(url, { method: 'POST', body });
}

async function postDownload(url, body = {}, fallbackName = 'warehouse-export.csv') {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/csv, application/vnd.ms-excel, application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    const contentType = response.headers.get('content-type') ?? '';
    if (!response.ok) {
        const data = contentType.includes('application/json')
            ? await response.json().catch(() => ({}))
            : {};
        throw new Error(data.message || `Không thể xuất dữ liệu (${response.status}).`);
    }
    if (contentType.includes('text/html')) {
        throw new Error('Máy chủ trả về trang lỗi thay vì file xuất.');
    }
    const blob = await response.blob();
    const filename = filenameFromDisposition(response.headers.get('Content-Disposition'), fallbackName);
    const href = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = href;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);
}

function InlineIconButton({ title, icon, onClick, disabled = false, className = '' }) {
    return (
        <button type="button" className={`btn-icon aoh ps-wh-inline-icon ${className}`} title={title} onClick={onClick} disabled={disabled}>
            <i className={`fa fa-${icon}`} />
        </button>
    );
}

function ActionMenuButton({ title, icon, tone = 'success', onClick, disabled = false }) {
    return (
        <button type="button" className={`n-button ps-wh-action-button fam-${tone}`} fam-tooltip={title} title={title} onClick={onClick} disabled={disabled}>
            <i className={`fa fa-${icon}`} />
        </button>
    );
}

function FloatingWarehouseActions({ selectedRows, apiBase, actionApiBase, onOpenSingle, onClear, onPrint, onReload }) {
    const { ask } = useConfirm();
    const [open, setOpen] = useState(false);
    const selectedCount = selectedRows.length;
    const selectedIds = selectedRows.map((row) => row.id);
    const selectedValidForShipment = selectedRows.filter((row) => row.canCreateShipment && !row.hasInsufficientStock);
    const requireSelected = (callback, message = 'Chọn ít nhất 1 đơn ở cột đầu tiên trước khi thao tác.') => {
        if (!selectedCount) {
            toast.error(message);
            return;
        }
        callback();
    };

    const bulkJson = async (endpoint, payload = {}) => postJson(`${actionApiBase}/bulk/${endpoint}`, { ids: selectedIds, ...payload });

    const createShipments = async () => {
        if (!selectedValidForShipment.length) {
            toast.error('Không có đơn đủ điều kiện tạo vận đơn.');
            return;
        }
        try {
            for (const row of selectedValidForShipment) await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(`Đã đăng vận đơn cho ${selectedValidForShipment.length} đơn.`);
            onClear();
            onReload();
        } catch (error) { toast.error(error.message); }
    };

    const cancelShipments = async () => {
        requireSelected(async () => {
            const ok = await ask({ description: 'Bạn chắc chắn muốn hủy vận đơn các đơn đã chọn?', confirmLabel: 'Xóa', variant: 'destructive' });
            if (!ok) return;
            try {
                for (const row of selectedRows) await apiRequest(`${apiBase}/${row.id}/cancel-shipment`, { method: 'POST', body: {} });
                toast.success(`Đã gửi yêu cầu hủy vận đơn cho ${selectedCount} đơn.`);
                onClear();
                onReload();
            } catch (error) { toast.error(error.message); }
        });
    };

    const syncStatuses = async () => {
        requireSelected(async () => {
            try {
                for (const row of selectedRows) await apiRequest(`${apiBase}/${row.id}/sync-status`, { method: 'POST', body: {} });
                toast.success(`Đã cập nhật trạng thái giao hàng cho ${selectedCount} đơn.`);
                onReload();
            } catch (error) { toast.error(error.message); }
        });
    };

    const markPrinted = async (printer = 'In đơn') => {
        requireSelected(async () => {
            try {
                for (const row of selectedRows) await apiRequest(`${actionApiBase}/${row.id}/printed`, { method: 'POST', body: {} });
                toast.success(`${printer}: đã đánh dấu in ${selectedCount} đơn.`);
                onReload();
            } catch (error) { toast.error(error.message); }
        });
    };

    const exportSelected = (kind, type = 'standard') => requireSelected(async () => {
        try {
            await postDownload(`${actionApiBase}/bulk/export`, { ids: selectedIds, type }, `warehouse-${type}.csv`);
            toast.success(`${kind}: đã xuất ${selectedCount} đơn.`);
        } catch (error) { toast.error(error.message); }
    });

    const issueInvoices = () => requireSelected(async () => {
        try {
            const data = await bulkJson('invoices', { source: 'warehouse-actions' });
            toast.success(data.message || `Đã tạo yêu cầu xuất HĐĐT cho ${selectedCount} đơn.`);
            onReload();
        } catch (error) { toast.error(error.message); }
    });

    const updateByOrderCode = () => requireSelected(async () => {
        try {
            const data = await bulkJson('update-by-code', { note: 'Cập nhật nhiều đơn theo mã Pushsale từ màn thủ kho.' });
            toast.success(data.message || `Đã ghi nhận ${selectedCount} đơn cần cập nhật.`);
            onReload();
        } catch (error) { toast.error(error.message); }
    });

    return (
        <nav className={`action-container ps-wh-floating-actions ${open ? 'open' : ''}`} onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)}>
            <div className="hidden-actions" aria-hidden={!open}>
                <div className="icon-row">
                    <ActionMenuButton title="Đăng đơn" icon="calendar-check-o" tone="primary" onClick={createShipments} disabled={!selectedValidForShipment.length} />
                    <ActionMenuButton title="Hủy đăng đơn" icon="calendar-times-o" tone="warning" onClick={cancelShipments} disabled={!selectedCount} />
                </div>
                <div className="icon-row">
                    <ActionMenuButton title="Cập nhật trạng thái giao hàng" icon="truck" tone="success" onClick={syncStatuses} disabled={!selectedCount} />
                    <ActionMenuButton title="Cập nhật trạng thái giao hàng Excel" icon="truck" tone="warning" onClick={() => exportSelected('Cập nhật giao hàng Excel', 'delivery-status')} disabled={!selectedCount} />
                </div>
                <div className="icon-row">
                    <ActionMenuButton title="In đơn" icon="print" tone="success" onClick={() => markPrinted('In đơn')} disabled={!selectedCount} />
                    <ActionMenuButton title="In đơn mẫu Shopee" icon="print" tone="warning" onClick={() => markPrinted('In Shopee')} disabled={!selectedCount} />
                    <ActionMenuButton title="In đơn mẫu TikTok" icon="print" tone="warning" onClick={() => markPrinted('In TikTok')} disabled={!selectedCount} />
                    <ActionMenuButton title="In đơn mẫu GHTK" icon="print" tone="success" onClick={() => markPrinted('In GHTK')} disabled={!selectedCount} />
                    <ActionMenuButton title="In đơn mẫu J&T" icon="print" tone="success" onClick={() => markPrinted('In J&T')} disabled={!selectedCount} />
                    <ActionMenuButton title="In đơn mẫu SPX" icon="print" tone="success" onClick={() => markPrinted('In SPX')} disabled={!selectedCount} />
                </div>
                <div className="icon-row">
                    <ActionMenuButton title="Xuất Excel kiểu 1" icon="file-excel-o" tone="primary" onClick={() => exportSelected('Xuất Excel kiểu 1', 'standard')} disabled={!selectedCount} />
                    <ActionMenuButton title="Xuất Excel kiểu 2" icon="file-excel-o" tone="success" onClick={() => exportSelected('Xuất Excel kiểu 2', 'shipping')} disabled={!selectedCount} />
                    <ActionMenuButton title="Xuất Excel kiểu 3" icon="file-excel-o" tone="warning" onClick={() => exportSelected('Xuất Excel kiểu 3', 'accounting')} disabled={!selectedCount} />
                </div>
                <div className="icon-row">
                    <ActionMenuButton title="Thêm đơn vào biên bản" icon="file-text-o" tone="success" onClick={() => onOpenSingle('return')} disabled={selectedCount !== 1} />
                    <ActionMenuButton title="Xuất hóa đơn điện tử theo mã đơn" icon="barcode" tone="success" onClick={issueInvoices} disabled={!selectedCount} />
                </div>
                <div className="icon-row">
                    <ActionMenuButton title="Cập nhật nhiều đơn theo mã Pushsale" icon="gears" tone="success" onClick={updateByOrderCode} disabled={!selectedCount} />
                </div>
            </div>
            <button type="button" className="main-action ps-wh-main-action" id="warehouseMenuToggle" title={open ? 'Đóng chức năng' : 'Mở chức năng'} onClick={() => setOpen((value) => !value)}>
                <i className="fa fa-bars" />
            </button>
        </nav>
    );
}

function LegacyStatus({ row }) {
    const className = statusTone[row.deliveryStatusValue] ?? 'ttgh1';
    return <span className={`no-wrap ${className}`}>{row.deliveryStatus || 'Chưa cập nhật'}</span>;
}

function CareNoteCell({ row, actionApiBase, onCare, onMessage }) {
    const noteRef = useRef(null);

    const saveNote = async () => {
        const note = noteRef.current?.value ?? '';
        try {
            await apiRequest(`${actionApiBase}/${row.id}/care`, {
                method: 'PATCH',
                body: { status: row.warehouseCareStatus ?? 'waiting', note },
            });
            toast.success('Đã lưu ghi chú care.');
            router.reload({ only: ['report', 'filters', 'filterOptions'] });
        } catch (error) {
            toast.error(error.message);
        }
    };

    return (
        <td className="text-left c-care-body">
            <div className="small-tip text-center">{formatDateTime(row.warehouseCareUpdatedAt, { withSeconds: false })}</div>
            <span className="span-col icon-col"><InlineIconButton title="Tác nghiệp care đơn" icon="refresh" onClick={onCare} /></span>
            <span className="span-col care-text"><span className="ps-wh-magenta">{row.warehouseCareStatusLabel || row.warehouseCareStatus || ''}</span><div><span title="Người đang xử lý" className="small-tip">({row.warehouseCareName || ''})</span></div></span>
            <span className="span-col save-col">
                <InlineIconButton title="Lưu ghi chú" icon="save" onClick={saveNote} />
                <InlineIconButton title="Tin nhắn nội bộ" icon="commenting-o" onClick={onMessage} />
            </span>
            <div className="mof-container text-left">
                <textarea
                    ref={noteRef}
                    className="txt-mof form-control txt-dotted"
                    defaultValue={row.warehouseCareNote || row.accountingNotes || ''}
                    maxLength="200"
                    onFocus={(event) => event.currentTarget.select()}
                />
            </div>
            <div style={{ clear: 'both' }} />
            <span className="item-noidung-other">{row.lastInternalMessage || ''}</span>
        </td>
    );
}

export function WarehouseOrderTable({ rows = [], apiBase, actionApiBase, filterOptions = {}, canDeleteOrder = false }) {
    const [action, setAction] = useState(null);
    const [detailOrderId, setDetailOrderId] = useState(null);
    const [selected, setSelected] = useState([]);
    const checkAllRef = useRef(null);
    const { ask } = useConfirm();
    const rowIds = useMemo(() => rows.map((row) => String(row.id)), [rows]);
    const selectedRows = useMemo(() => rows.filter((row) => selected.includes(String(row.id))), [rows, selected]);
    const allSelected = rowIds.length > 0 && rowIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    useEffect(() => {
        setSelected((current) => current.filter((id) => rowIds.includes(id)));
    }, [rowIds.join('|')]);

    useEffect(() => {
        if (checkAllRef.current) checkAllRef.current.indeterminate = someSelected;
    }, [someSelected]);

    const toggleAll = () => setSelected(allSelected ? [] : rowIds);
    const toggle = (id) => setSelected((current) => current.includes(String(id)) ? current.filter((item) => item !== String(id)) : [...current, String(id)]);

    const reload = () => router.reload({ only: ['report'] });
    const openSingleSelected = (type) => {
        if (selectedRows.length !== 1) {
            toast.error('Chọn đúng 1 đơn để thao tác.');
            return;
        }
        setAction({ type, row: selectedRows[0] });
    };

    const printLabel = async (row, reloadAfter = true) => {
        try {
            await apiRequest(`${actionApiBase}/${row.id}/printed`, { method: 'POST', body: {} });
            if (row.canPrintLabel) openShippingLabel(`${apiBase}/${row.id}/label`);
            else window.print();
            if (reloadAfter) reload();
        } catch (error) { toast.error(error.message); }
    };

    const printSelected = async () => {
        for (const row of selectedRows) await printLabel(row, false);
        reload();
    };

    const createShipment = async (row) => {
        try {
            await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(`Đã tạo vận đơn cho ${row.orderCode}.`);
            reload();
        } catch (error) { toast.error(error.message); }
    };

    const deleteOrder = async (row) => {
        const ok = await ask({
            description: `Bạn chắc chắn muốn xóa data của ${row.customerName || row.customerPhone || row.orderCode || `#${row.id}`}?`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        try {
            await apiRequest(`${actionApiBase}/${row.id}`, { method: 'DELETE' });
            toast.success('Đã xóa data.');
            reload();
        } catch (error) {
            toast.error(error.message);
        }
    };

    return (
        <>
            <div className="ps-wh-table-shell dragscroll1 tableFixHead">
                <table className="table table-bordered table-multi-select table-sale ps-wh-table ps-wh-legacy-table">
                    <thead>
                        <tr className="drags-area hidden"><th className="text-center" colSpan="11">THÔNG TIN ĐƠN HÀNG</th><th className="text-center" colSpan="4">THÔNG TIN GIAO HÀNG</th></tr>
                        <tr className="drags-area">
                            <th className="text-center c-check"><span className="chk-all"><input ref={checkAllRef} type="checkbox" checked={allSelected} onChange={toggleAll} /><label>&nbsp;</label></span></th>
                            <th className="text-center c-sale">Sale</th>
                            <th className="text-center no-wrap c-order">Ngày data về<br />Mã đơn<br />Ngày chốt đơn</th>
                            <th className="text-center no-wrap c-shipper">Kho<br /><span title="Phương thức giao hàng">PTGH</span><br />Mã giao vận</th>
                            <th className="text-center no-wrap c-care">Ngày cập nhật care đơn<br /><span>Care đơn<br />Ghi chú kế toán</span></th>
                            <th className="text-center no-wrap c-status">Ngày cập nhật<br />Trạng thái giao hàng<br />Ngày đăng đơn</th>
                            <th className="text-center no-wrap c-customer"><span>Họ tên</span><br />Số điện thoại<br />Ngày muốn nhận hàng</th>
                            <th className="text-center c-address"><span>Địa chỉ<br />Ghi chú giao hàng</span><br />Hóa đơn điện tử</th>
                            <th className="text-left no-wrap c-products"><span>Sản phẩm - Số lượng - Đơn giá</span></th>
                            <th className="text-center no-wrap area3 c-money"><span>Thành tiền<br />CK / VAT SP<br />Phí VC / Tổng tiền</span></th>
                            <th className="text-center no-wrap c-deposit">Đặt cọc</th>
                            <th className="text-center no-wrap c-cod">Tiền thu<br />của khách</th>
                            <th className="text-center c-fee">Giá dịch vụ<br />VC</th>
                            <th className="text-center c-fee">Phí VC<br />hỗ trợ khách</th>
                            <th className="text-center c-recon" title="Đối soát nội bộ">ĐSNB</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => (
                            <tr className={`contact-row item${row.id} ${row.isReturnFlow ? 'return-row' : ''}`} key={row.id}>
                                <td className="text-center no-wrap"><span className="chk-item"><input type="checkbox" checked={selected.includes(String(row.id))} onChange={() => toggle(row.id)} /><label>{index + 1}</label></span></td>
                                <td className="text-center ps-wh-sale-cell">
                                    <div className="text-right ps-wh-delete-wrap">
                                        {(canDeleteOrder && row.canDeleteOrder) ? (
                                            <button type="button" className="btn-icon aoh ps-wh-delete" onClick={() => deleteOrder(row)} title="Xóa data" aria-label="Xóa data">
                                                <i className="fa fa-trash" />
                                            </button>
                                        ) : null}
                                    </div>
                                    <span className="ps-wh-sale-name">{row.saleName || '—'}</span>
                                    <br />
                                    <span className="small-tip">{row.saleUsername ? `(${row.saleUsername})` : ''}</span>
                                </td>
                                <td className="text-center no-wrap ps-wh-code-cell">
                                    <div className="ps-wh-code-stack">
                                        <button type="button" className="btn-icon aoh orange ps-wh-code-action" title="Thay đổi mã đơn" onClick={() => setAction({ type: 'changeCode', row })}>
                                            <i className="fa fa-repeat" />
                                        </button>
                                        <div className="small-tip sline">{formatDateTime(row.dataArrivedAt, { withSeconds: false })}</div>
                                        <button type="button" className="item-md ps-wh-order-code" onClick={() => setDetailOrderId(row.id)}>{row.orderCode}</button>
                                        <div className="small-tip sline">{formatDateTime(row.closedAt, { withSeconds: false })}</div>
                                    </div>
                                </td>
                                <td className="text-center no-wrap">
                                    <div className="text-right">
                                        {row.canCreateShipment && <InlineIconButton title="Đăng vận đơn" icon="calendar-check-o" onClick={() => createShipment(row)} className="orange" />}
                                        {row.canPrintLabel && <InlineIconButton title="In đơn" icon="print" onClick={() => printLabel(row)} />}
                                    </div>
                                    {row.warehouseName || 'Chưa chọn kho'}<br />
                                    <span className="ps-wh-green">{row.shippingProviderLabel || row.shippingMethod || 'Thủ công'}</span>
                                    <button type="button" className="item-mdgv" onClick={() => setDetailOrderId(row.id)}>{row.trackingNumber || ''}</button>
                                </td>
                                <CareNoteCell
                                    row={row}
                                    actionApiBase={actionApiBase}
                                    onCare={() => setAction({ type: 'care', row })}
                                    onMessage={() => setAction({ type: 'message', row })}
                                />
                                <td className="text-center area4 ps-wh-delivery-cell">
                                    <div className="ps-wh-delivery-stack">
                                        <div className="small-tip">{formatDateTime(row.lastDeliveryEventAt, { withSeconds: false })}</div>
                                        <div className="ps-wh-delivery-actions">
                                            <InlineIconButton title="Đưa vào danh sách cảnh báo bom hàng" icon="bomb" onClick={() => setAction({ type: 'blacklist', row })} />
                                            <InlineIconButton title="Cập nhật trạng thái giao hàng" icon="refresh" onClick={() => setAction({ type: 'delivery', row })} />
                                            <InlineIconButton title="Lịch sử tác nghiệp" icon="history" onClick={() => setAction({ type: 'timeline', row })} />
                                        </div>
                                        <LegacyStatus row={row} />
                                        <div className="small-tip sline">{row.shipment?.statusText || ''}</div>
                                        {row.shipmentError && <div className="ps-wh-error text-left">{row.shipmentError}</div>}
                                    </div>
                                </td>
                                <td className="text-center c-customer-body ps-contact-name-phone" title={`${row.id} | ${row.sourceType || ''}`}>
                                    <div className="text-right">
                                        <InlineIconButton title="Cập nhật ngày muốn nhận hàng" icon="calendar" onClick={() => setAction({ type: 'date', row })} />
                                        <InlineIconButton title="Tách đơn" icon="clipboard" onClick={() => setAction({ type: 'split', row })} disabled={!row.canSplit} />
                                        <InlineIconButton title="Cập nhật đơn vị giao vận" icon="truck" onClick={() => setAction({ type: 'edit', row })} />
                                    </div>
                                    <div className="sline text-left ps-wh-customer-name">
                                        <span>{row.effectiveReceiverName || row.customerName}</span>
                                    </div>
                                    {row.carrierLabel ? <span className="nha-mang text-left">{row.carrierLabel}</span> : null}
                                    <div className="no-wrap ps-contact-phone-row">
                                        <div className="ps-phone-main">
                                            <a className="text-left" href={`tel:${row.effectiveReceiverPhone || row.customerPhone}`}>{row.effectiveReceiverPhone || row.customerPhone}</a>
                                            {(row.effectiveReceiverPhone || row.customerPhone) ? (
                                                <a className="ps-contact-phone-icon" href={`tel:${row.effectiveReceiverPhone || row.customerPhone}`} title="Gọi khách hàng" aria-label={`Gọi ${row.effectiveReceiverPhone || row.customerPhone}`}>
                                                    <i className="fa fa-phone" aria-hidden="true" />
                                                </a>
                                            ) : null}
                                        </div>
                                        <OrderStatusFlags row={row} className="ps-contact-flags" />
                                    </div>
                                    <div className="text-left khkn sline">{row.customerNote || ''}</div>
                                    <div className="ps-wh-green">{formatDateTime(row.desiredDeliveryAt, { withSeconds: false })}</div>
                                </td>
                                <td className="c-address-body"><span>{row.shippingAddress || 'Chưa có địa chỉ giao'}</span>{row.shippingNotes && <><br /><span className="small-tip ps-wh-magenta">{row.shippingNotes}</span></>}</td>
                                <td className="text-left c-products-body"><OrderProductsBreakdown items={row.products || [...(row.mainProducts || []), ...(row.upsellProducts || [])]} order={row} /></td>
                                <td className="no-wrap area3 text-right c-money-body"><OrderMoneyBreakdown row={row} /></td>
                                <td className="text-right">{formatCurrency(row.deposit)}</td>
                                <td className="text-right">{formatCurrency(row.codAmount || row.total)}</td>
                                <td className="text-right">{formatCurrency(row.carrierServiceFee)}</td>
                                <td className="text-right">{formatCurrency(row.carrierReturnFee || row.carrierOtherFee || row.codFee)}</td>
                                <td className="text-center"><span>{row.reconciliationStatus || ''}</span><br /><span className="small-tip">{row.reconciliationUpdatedAt || ''}</span></td>
                            </tr>
                        )) : <tr><td colSpan="15" className="ps-wh-empty">Không có đơn phù hợp bộ lọc.</td></tr>}
                    </tbody>
                </table>
            </div>

            <FloatingWarehouseActions
                selectedRows={selectedRows}
                apiBase={apiBase}
                actionApiBase={actionApiBase}
                onOpenSingle={openSingleSelected}
                onClear={() => setSelected([])}
                onPrint={printSelected}
                onReload={reload}
            />
            {selectedRows.length > 0 && <div className="ps-wh-selected-hint">Đã chọn {selectedRows.length} đơn. Mở nút chức năng màu xanh bên trái để xử lý hàng loạt.</div>}
            <WarehouseActionDialogs action={action} onClose={() => setAction(null)} actionApiBase={actionApiBase} filterOptions={filterOptions} />
            <ShippingOrderDetailDialog orderId={detailOrderId} open={Boolean(detailOrderId)} onOpenChange={(open) => !open && setDetailOrderId(null)} apiBase={apiBase} />
        </>
    );
}
