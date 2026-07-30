import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { ShippingOrderDetailDialog } from '@/components/shipping/ShippingOrderDetailDialog';
import { WarehouseActionDialogs } from '@/components/operations/WarehouseActionDialogs';
import { RegisterShipmentDialog } from '@/components/operations/RegisterShipmentDialog';
import { AddToHandoverDialog } from '@/components/operations/AddToHandoverDialog';
import { UpdateDeliveryStatusByCodeDialog } from '@/components/operations/UpdateDeliveryStatusByCodeDialog';
import { UpdateDeliveryStatusExcelDialog } from '@/components/operations/UpdateDeliveryStatusExcelDialog';
import { OrderMoneyCell, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
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
        const validationMessage = data.message
            || data.errors?.export?.[0]
            || data.errors?.ids?.[0]
            || data.errors?.type?.[0]
            || (data.errors ? Object.values(data.errors).flat()[0] : null);
        throw new Error(validationMessage || `Không thể xuất dữ liệu (${response.status}).`);
    }
    // HTML Excel (.xls) is valid export output from ReportExcelExporter.
    if (contentType.includes('text/html') && !contentType.includes('excel') && !contentType.includes('spreadsheet')) {
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

function FloatingWarehouseActions({
    selectedRows,
    eligibleShipmentRows,
    pageRows = [],
    filters = {},
    apiBase,
    actionApiBase,
    deliveryStatuses = [],
    printButtons = [],
    exportButtons = [],
    shippingProviders = [],
    onClear,
    onReload,
}) {
    const { ask } = useConfirm();
    const [open, setOpen] = useState(false);
    const [registerOpen, setRegisterOpen] = useState(false);
    const [handoverOpen, setHandoverOpen] = useState(false);
    const [ttghCodeOpen, setTtghCodeOpen] = useState(false);
    const [ttghExcelOpen, setTtghExcelOpen] = useState(false);
    const [exportBusy, setExportBusy] = useState(false);
    const selectedIds = selectedRows.map((row) => row.id);
    const selectedValidForShipment = selectedRows.filter((row) => row.canCreateShipment && !row.hasInsufficientStock);

    const resolveActionRows = () => (selectedRows.length ? selectedRows : pageRows);
    const resolveActionIds = () => {
        const rows = resolveActionRows();
        return rows.map((row) => row.id).filter(Boolean);
    };
    const resolveActionCodesText = () => resolveActionRows().map((row) => row.orderCode).filter(Boolean).join('\n');

    const printFabButtons = printButtons.length
        ? printButtons
        : [
            { key: 'internal', title: 'In đơn', tone: 'success', icon: 'print' },
            { key: 'shopee', title: 'In đơn mẫu Shopee', tone: 'warning', icon: 'print' },
            { key: 'tiktok', title: 'In đơn mẫu TikTok', tone: 'warning', icon: 'print' },
            { key: 'ghtk', title: 'In đơn mẫu GHTK', tone: 'success', icon: 'print' },
            { key: 'jnt', title: 'In đơn mẫu J&T', tone: 'success', icon: 'print' },
            { key: 'spx', title: 'In đơn mẫu SPX', tone: 'success', icon: 'print' },
        ];
    const excelFabButtons = exportButtons.length
        ? exportButtons
        : [
            { key: 'standard', title: 'Xuất Excel kiểu 1', tone: 'primary', icon: 'file-excel-o' },
            { key: 'shipping', title: 'Xuất Excel kiểu 2', tone: 'success', icon: 'file-excel-o' },
            { key: 'accounting', title: 'Xuất Excel kiểu 3', tone: 'warning', icon: 'file-excel-o' },
        ];

    const bulkUpdatePageUrl = `${actionApiBase}/update-by-code`;

    const openPrintProfile = (profileKey) => {
        const ids = resolveActionIds();
        if (!ids.length) {
            toast.error('Không có đơn để in. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu theo bộ lọc.');
            return;
        }
        router.visit(`${actionApiBase}/print/${profileKey}?ids=${encodeURIComponent(ids.join(','))}`);
    };

    const createShipments = async () => {
        const targetRows = selectedRows.length
            ? selectedValidForShipment
            : pageRows.filter((row) => row.canCreateShipment && !row.hasInsufficientStock);

        if (!selectedRows.length && !targetRows.length) {
            setRegisterOpen(true);
            return;
        }
        if (!targetRows.length) {
            toast.error('Không có đơn đủ điều kiện tạo vận đơn trên trang / lựa chọn hiện tại.');
            return;
        }
        const ok = await ask({
            title: 'Đăng đơn',
            description: `Bạn chắc chắn muốn đăng đơn cho ${targetRows.length} đơn?`,
            confirmLabel: 'Đăng đơn',
        });
        if (!ok) return;
        try {
            for (const row of targetRows) await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(`Đã đăng vận đơn cho ${targetRows.length} đơn.`);
            onClear();
            onReload();
        } catch (error) { toast.error(error.message); }
    };

    const cancelShipments = async () => {
        const rows = resolveActionRows();
        if (!rows.length) {
            toast.error('Không có đơn để hủy. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu.');
            return;
        }
        const ok = await ask({
            title: 'Hủy đăng đơn',
            description: `Bạn chắc chắn muốn hủy đăng đơn cho ${rows.length} đơn đang hiển thị / đã chọn?`,
            confirmLabel: 'Hủy đăng đơn',
            variant: 'destructive',
        });
        if (!ok) return;
        try {
            for (const row of rows) {
                await apiRequest(`${apiBase}/${row.id}/cancel-shipment`, { method: 'POST', body: {} });
            }
            toast.success(`Đã gửi yêu cầu hủy vận đơn cho ${rows.length} đơn.`);
            onClear();
            onReload();
        } catch (error) { toast.error(error.message); }
    };

    const exportExcel = async (kind, type = 'standard') => {
        if (exportBusy) {
            toast.warning('Đang xuất Excel, vui lòng đợi xong trước khi bấm tiếp.');
            return;
        }
        const ids = resolveActionIds();
        if (!ids.length) {
            toast.error('Không có đơn để xuất. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu.');
            return;
        }
        setExportBusy(true);
        try {
            await postDownload(`${actionApiBase}/bulk/export`, {
                type,
                ids,
                filters,
            }, `warehouse-${type}.xls`);
            toast.success(`${kind}: đã xuất ${ids.length} đơn.`);
        } catch (error) {
            toast.error(error.message);
        } finally {
            setExportBusy(false);
        }
    };

    const issueInvoices = () => {
        const ids = resolveActionIds();
        if (!ids.length) {
            toast.error('Không có đơn để xuất HĐĐT. Chọn đơn hoặc lọc có dữ liệu trên trang.');
            return;
        }
        (async () => {
            try {
                const data = await postJson(`${actionApiBase}/bulk/invoices`, { ids, source: 'warehouse-actions' });
                toast.success(data.message || `Đã tạo yêu cầu xuất HĐĐT cho ${ids.length} đơn.`);
                onReload();
            } catch (error) { toast.error(error.message); }
        })();
    };

    const openBulkUpdatePage = () => {
        const codes = resolveActionCodesText();
        const url = codes
            ? `${bulkUpdatePageUrl}?codes=${encodeURIComponent(codes)}`
            : bulkUpdatePageUrl;
        router.visit(url);
    };

    const openHandoverDialog = () => {
        const rows = resolveActionRows();
        if (!rows.length) {
            toast.error('Không có đơn để thêm vào biên bản.');
            return;
        }
        setHandoverOpen(true);
    };

    return (
        <>
            <nav className={`action-container ps-wh-floating-actions ${open ? 'open' : ''}`} onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)}>
                <div className="hidden-actions" aria-hidden={!open}>
                    <div className="icon-row">
                        <ActionMenuButton title="Đăng đơn" icon="calendar-check-o" tone="primary" onClick={createShipments} />
                        <ActionMenuButton title="Hủy đăng đơn" icon="calendar-times-o" tone="warning" onClick={cancelShipments} />
                    </div>
                    <div className="icon-row">
                        <ActionMenuButton title="Cập nhật trạng thái giao hàng" icon="truck" tone="success" onClick={() => setTtghCodeOpen(true)} />
                        <ActionMenuButton title="Cập nhật trạng thái giao hàng Excel" icon="truck" tone="warning" onClick={() => setTtghExcelOpen(true)} />
                    </div>
                    <div className="icon-row">
                        {printFabButtons.map((button) => (
                            <ActionMenuButton
                                key={button.key}
                                title={button.title}
                                icon={button.icon || 'print'}
                                tone={button.tone || 'success'}
                                onClick={() => openPrintProfile(button.key)}
                            />
                        ))}
                    </div>
                    <div className="icon-row">
                        {excelFabButtons.map((button) => (
                            <ActionMenuButton
                                key={button.key}
                                title={button.title}
                                icon={button.icon || 'file-excel-o'}
                                tone={button.tone || 'primary'}
                                onClick={() => exportExcel(button.title, button.key)}
                                disabled={exportBusy}
                            />
                        ))}
                    </div>
                    <div className="icon-row">
                        <ActionMenuButton title="Thêm đơn vào biên bản" icon="file-text-o" tone="success" onClick={openHandoverDialog} />
                        <ActionMenuButton title="Xuất hóa đơn điện tử theo mã đơn" icon="barcode" tone="success" onClick={issueInvoices} />
                    </div>
                    <div className="icon-row">
                        <ActionMenuButton title="Cập nhật nhiều đơn theo mã Pushsale" icon="gears" tone="success" onClick={openBulkUpdatePage} />
                    </div>
                </div>
                <button type="button" className="main-action ps-wh-main-action" id="warehouseMenuToggle" title={open ? 'Đóng chức năng' : 'Mở chức năng'} onClick={() => setOpen((value) => !value)}>
                    <i className="fa fa-bars" />
                </button>
            </nav>
            <RegisterShipmentDialog
                open={registerOpen}
                onOpenChange={setRegisterOpen}
                eligibleRows={eligibleShipmentRows}
                apiBase={apiBase}
                onDone={() => { onClear(); onReload(); }}
            />
            <AddToHandoverDialog
                open={handoverOpen}
                onOpenChange={setHandoverOpen}
                targetRows={resolveActionRows()}
                providers={shippingProviders}
                onDone={() => { onClear(); onReload(); }}
            />
            <UpdateDeliveryStatusByCodeDialog
                open={ttghCodeOpen}
                onOpenChange={setTtghCodeOpen}
                actionApiBase={actionApiBase}
                initialCodes={resolveActionCodesText()}
                deliveryStatuses={deliveryStatuses}
                onDone={() => { onClear(); onReload(); }}
            />
            <UpdateDeliveryStatusExcelDialog
                open={ttghExcelOpen}
                onOpenChange={setTtghExcelOpen}
                actionApiBase={actionApiBase}
                onDone={() => { onClear(); onReload(); }}
            />
        </>
    );
}

function LegacyStatus({ row }) {
    const className = statusTone[row.deliveryStatusValue] ?? 'ttgh1';
    return <span className={`ps-wh-delivery-status no-wrap ${className}`}>{row.deliveryStatus || 'Chưa cập nhật'}</span>;
}

function CareNoteCell({ row, actionApiBase, onCare, onMessage }) {
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [expanded, setExpanded] = useState(false);

    const saveNote = async () => {
        const note = value.trim();
        if (!note) {
            toast.error('Nhập nội dung tin nhắn nội bộ.');
            return;
        }
        setSaving(true);
        try {
            await apiRequest(`${actionApiBase}/${row.id}/internal-message`, {
                method: 'POST',
                body: { message: note },
            });
            toast.success('Đã lưu tin nhắn nội bộ.');
            setValue('');
            router.reload({ only: ['report', 'filters', 'filterOptions'] });
        } catch (error) {
            toast.error(error.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <td className={`text-left c-care-body ps-care-note-editor${expanded ? ' is-expanded' : ''}`}>
            <div style={{ paddingBottom: 4 }} className="small-tip text-center">
                {formatDateTime(row.warehouseCareUpdatedAt, { withSeconds: false })}
            </div>
            <span className="span-col" style={{ width: 20 }}>
                <InlineIconButton title="Tác nghiệp care đơn" icon="refresh" onClick={onCare} />
            </span>
            <span className="span-col" style={{ width: 'calc(100% - 90px)', textOverflow: 'ellipsis', maxWidth: 150 }}>
                <span className="ps-wh-magenta">{row.warehouseCareStatusLabel || row.warehouseCareStatus || ''}</span>
                <div>
                    <span title="Người đang xử lý" className="small-tip">({row.warehouseCareName || ''})</span>
                </div>
            </span>
            <span className="span-col" style={{ width: 60 }}>
                <InlineIconButton title="Lưu ghi chú" icon="save" onClick={saveNote} disabled={saving} />
                <InlineIconButton title="Tin nhắn nội bộ" icon="commenting-o" onClick={onMessage} />
            </span>
            <div
                className="mof-container text-left ps-care-note-mof"
                onMouseEnter={() => setExpanded(true)}
                onMouseLeave={() => setExpanded(false)}
            >
                <textarea
                    className="txt-mof form-control txt-dotted"
                    value={value}
                    maxLength={200}
                    rows={2}
                    placeholder=""
                    onChange={(event) => setValue(event.target.value)}
                    onFocus={() => setExpanded(true)}
                    onBlur={() => setExpanded(false)}
                    onKeyDown={(event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') saveNote();
                    }}
                />
            </div>
            <div style={{ clear: 'both' }} />
            <span className="item-noidung-other" style={{ display: 'inline-block', marginTop: 4 }}>{row.lastInternalMessage || ''}</span>
        </td>
    );
}

export function WarehouseOrderTable({
    rows = [],
    apiBase,
    actionApiBase,
    filterOptions = {},
    filters = {},
    canDeleteOrder = false,
    printButtons = [],
    exportButtons = [],
    variant = 'warehouse',
}) {
    const isAccounting = variant === 'accounting';
    const [action, setAction] = useState(null);
    const [detailOrderId, setDetailOrderId] = useState(null);
    const [selected, setSelected] = useState([]);
    const checkAllRef = useRef(null);
    const { ask } = useConfirm();
    const rowIds = useMemo(() => rows.map((row) => String(row.id)), [rows]);
    const selectedRows = useMemo(() => rows.filter((row) => selected.includes(String(row.id))), [rows, selected]);
    const eligibleShipmentRows = useMemo(
        () => rows.filter((row) => row.canCreateShipment && !row.hasInsufficientStock),
        [rows],
    );
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

    const printLabel = async (row, reloadAfter = true) => {
        try {
            await apiRequest(`${actionApiBase}/${row.id}/printed`, { method: 'POST', body: {} });
            if (row.canPrintLabel) openShippingLabel(`${apiBase}/${row.id}/label`);
            else window.print();
            if (reloadAfter) reload();
        } catch (error) { toast.error(error.message); }
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
                                    <div className="text-right">
                                        <button
                                            type="button"
                                            className="btn-icon aoh orange ps-wh-code-action"
                                            title="Thay đổi mã đơn"
                                            style={{ color: 'darkorange' }}
                                            onClick={() => setAction({ type: 'changeCode', row })}
                                        >
                                            <i className="fa fa-repeat" />
                                        </button>
                                    </div>
                                    <div className="small-tip sline">{formatDateTime(row.dataArrivedAt, { withSeconds: true })}</div>
                                    <div>
                                        <button type="button" className="item-md ps-wh-order-code" onClick={() => setDetailOrderId(row.id)}>{row.orderCode}</button>
                                    </div>
                                    <div className="small-tip sline">{formatDateTime(row.closedAt, { withSeconds: true })}</div>
                                </td>
                                    <td className="text-center no-wrap ps-wh-shipper-cell">
                                    <div className="text-right">
                                        {!isAccounting && row.canCreateShipment && <InlineIconButton title="Đăng vận đơn" icon="calendar-check-o" onClick={() => createShipment(row)} className="orange" />}
                                        {!isAccounting && row.canPrintLabel && <InlineIconButton title="In đơn" icon="print" onClick={() => printLabel(row)} />}
                                    </div>
                                    <div className="ps-wh-shipper-stack">
                                        <div className="ps-wh-shipper-line">{row.warehouseName || ''}</div>
                                        <div className="ps-wh-shipper-line ps-wh-green">{row.shippingProviderLabel || row.shippingMethod || ''}</div>
                                        <div className="ps-wh-shipper-line">
                                            <button type="button" className="item-mdgv" onClick={() => setDetailOrderId(row.id)}>{row.trackingNumber || ''}</button>
                                        </div>
                                    </div>
                                </td>
                                <CareNoteCell
                                    row={row}
                                    actionApiBase={actionApiBase}
                                    onCare={() => setAction({ type: 'care', row })}
                                    onMessage={() => setAction({ type: 'message', row })}
                                />
                                <td className="text-center area4 ps-wh-delivery-cell">
                    <div className="ps-wh-delivery-stack">
                        <div className="small-tip ps-wh-delivery-date">{formatDateTime(row.lastDeliveryEventAt, { withSeconds: true }) || '—'}</div>
                        <div className="ps-wh-delivery-actions no-wrap">
                            {isAccounting ? (
                                <InlineIconButton title="Đồng bộ trạng thái giao hàng" icon="retweet" onClick={() => setAction({ type: 'delivery', row })} />
                            ) : (
                                <InlineIconButton title="Đưa vào danh sách cảnh báo bom hàng" icon="bomb" onClick={() => setAction({ type: 'blacklist', row })} />
                            )}
                            <InlineIconButton title="Cập nhật trạng thái giao hàng" icon="refresh" onClick={() => setAction({ type: 'delivery', row })} />
                            <InlineIconButton title="Lịch sử tác nghiệp" icon="history" onClick={() => setAction({ type: 'timeline', row })} />
                        </div>
                        <LegacyStatus row={row} />
                        <div className="small-tip sline ps-wh-delivery-date">{formatDateTime(row.shipmentPostedAt || row.printedAt, { withSeconds: false }) || row.shipment?.statusText || '—'}</div>
                        {row.shipmentError ? <div className="ps-wh-error text-left">{row.shipmentError}</div> : null}
                    </div>
                </td>
                <td className="text-center c-customer-body ps-contact-name-phone" title={`${row.id} | ${row.sourceType || ''}`}>
                    <div className="text-right">
                        <InlineIconButton title="Cập nhật ngày muốn nhận hàng" icon="calendar" onClick={() => setAction({ type: 'date', row })} />
                        {!isAccounting && (
                            <InlineIconButton title="Tách đơn" icon="clipboard" onClick={() => setAction({ type: 'split', row })} disabled={!row.canSplit} />
                        )}
                        <InlineIconButton title="Cập nhật đơn vị giao vận" icon="truck" onClick={() => setAction({ type: 'edit', row })} />
                    </div>
                    <div className="sline text-left ps-wh-customer-name">
                        <span>{row.effectiveReceiverName || row.customerName}</span>
                    </div>
                    {row.carrierLabel ? <span className="nha-mang text-left">{row.carrierLabel}</span> : null}
                    <div className="no-wrap ps-contact-phone-row">
                        <div className="ps-phone-main">
                            <a className="text-left ps-phone-link" href={`tel:${row.effectiveReceiverPhone || row.customerPhone}`}>{row.effectiveReceiverPhone || row.customerPhone}</a>
                        </div>
                        <OrderStatusFlags row={row} className="ps-contact-flags" />
                    </div>
                    {row.customerNote ? <div className="text-left khkn sline">{row.customerNote}</div> : null}
                    {row.desiredDeliveryAt ? (
                        <div className="ps-wh-green">{formatDateTime(row.desiredDeliveryAt, { withSeconds: false })}</div>
                    ) : null}
                </td>
                                <td className="c-address-body"><span>{row.shippingAddress || ''}</span>{row.shippingNotes && <><br /><span className="small-tip ps-wh-magenta">{row.shippingNotes}</span></>}</td>
                                <td className="text-left c-products-body"><OrderProductsBreakdown items={row.products || [...(row.mainProducts || []), ...(row.upsellProducts || [])]} order={row} /></td>
                                <OrderMoneyCell className="no-wrap area3 c-money-body" row={row} />
                                <td className="text-right">{formatCurrency(row.deposit)}</td>
                                <td className="text-right">{formatCurrency(row.codAmount || row.total)}</td>
                                <td className="text-right">{formatCurrency(row.carrierServiceFee)}</td>
                                <td className="text-right">{formatCurrency(row.carrierReturnFee || row.carrierOtherFee || row.codFee)}</td>
                                <td className="text-center">
                                    {row.reconciliationStatus && !['pending', 'none', 'null'].includes(String(row.reconciliationStatus).toLowerCase()) ? (
                                        <>
                                            <span>{row.reconciliationStatusLabel || row.reconciliationStatus}</span>
                                            <br />
                                            <span className="small-tip">{row.reconciliationUpdatedAt || ''}</span>
                                        </>
                                    ) : null}
                                </td>
                            </tr>
                        )) : <tr><td colSpan="15" className="ps-wh-empty">Không có đơn phù hợp bộ lọc.</td></tr>}
                    </tbody>
                </table>
            </div>

            <FloatingWarehouseActions
                selectedRows={selectedRows}
                eligibleShipmentRows={eligibleShipmentRows}
                pageRows={rows}
                filters={filters}
                apiBase={apiBase}
                actionApiBase={actionApiBase}
                deliveryStatuses={filterOptions.deliveryStatuses ?? []}
                printButtons={printButtons}
                exportButtons={exportButtons}
                shippingProviders={filterOptions.shippingProviders ?? []}
                onClear={() => setSelected([])}
                onReload={reload}
            />
            {selectedRows.length > 0 && <div className="ps-wh-selected-hint">Đã chọn {selectedRows.length} đơn. Mở nút chức năng màu xanh bên trái để xử lý hàng loạt.</div>}
            <WarehouseActionDialogs action={action} onClose={() => setAction(null)} actionApiBase={actionApiBase} filterOptions={filterOptions} />
            <ShippingOrderDetailDialog orderId={detailOrderId} open={Boolean(detailOrderId)} onOpenChange={(open) => !open && setDetailOrderId(null)} apiBase={apiBase} />
        </>
    );
}
