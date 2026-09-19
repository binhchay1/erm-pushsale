import { useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { ShippingOrderDetailDialog } from '@/components/shipping/ShippingOrderDetailDialog';
import { WarehouseActionDialogs } from '@/components/operations/WarehouseActionDialogs';
import { RegisterShipmentDialog } from '@/components/operations/RegisterShipmentDialog';
import { AddToHandoverDialog } from '@/components/operations/AddToHandoverDialog';
import { UpdateDeliveryStatusByCodeDialog } from '@/components/operations/UpdateDeliveryStatusByCodeDialog';
import { UpdateDeliveryStatusExcelDialog } from '@/components/operations/UpdateDeliveryStatusExcelDialog';
import { UpdateReconByCodeDialog } from '@/components/operations/UpdateReconByCodeDialog';
import { UpdateReconExcelDialog } from '@/components/operations/UpdateReconExcelDialog';
import { OrderMoneyCell, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiPost, apiRequest, getCsrfToken, toastApiError } from '@/lib/api';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { openShippingLabel } from '@/lib/shipping';
import { useConfirm } from '@/hooks/use-confirm';
import { useAppName } from '@/hooks/use-app-name';
import { useOrderLockPresence } from '@/hooks/useOrderLockPresence';
import { useT } from '@/providers/I18nProvider';

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

function rowProductItems(row = {}) {
    if (Array.isArray(row.products) && row.products.length) return row.products;
    return [...(row.mainProducts || []), ...(row.upsellProducts || [])];
}

function productLineName(item = {}) {
    const raw = String(item.productName ?? item.product_name ?? item.name ?? '').trim();
    if (!raw) return '—';
    try {
        const decoded = decodeURIComponent(raw.replace(/\+/g, ' '));
        if (decoded && decoded !== raw) return decoded.trim() || raw;
    } catch {
        // keep raw
    }
    return raw;
}

function productLineQty(item = {}) {
    if (item.quantity === 0 || item.qty === 0) return 0;
    const raw = Number(item.quantity ?? item.qty ?? 1);
    return Number.isFinite(raw) && raw >= 0 ? raw : 1;
}

function productLineSku(item = {}) {
    return String(item.sku ?? item.productSku ?? item.product_sku ?? '').trim();
}

function productLineUnitPrice(item = {}) {
    return Math.max(0, Number(item.unitPrice ?? item.unit_price ?? item.price ?? 0));
}

/** Aggregate products + money for sticky table footer (Pushsale 5.1 parity). */
function buildWarehouseTableFooter(sourceRows = []) {
    const productMap = new Map();
    let qtyTotal = 0;
    let subtotal = 0;
    let discount = 0;
    let vat = 0;
    let shipping = 0;
    let total = 0;

    for (const row of sourceRows) {
        for (const item of rowProductItems(row)) {
            const name = productLineName(item);
            const qty = productLineQty(item);
            const sku = productLineSku(item);
            const unitPrice = productLineUnitPrice(item);
            const key = String(item.productId ?? item.product_id ?? `${sku}|${name}`);
            const prev = productMap.get(key);
            if (prev) {
                prev.qty += qty;
                if (!prev.sku && sku) prev.sku = sku;
                if (prev.unitPrice <= 0 && unitPrice > 0) prev.unitPrice = unitPrice;
                prev.amount += qty * unitPrice;
            } else {
                productMap.set(key, {
                    name,
                    sku,
                    qty,
                    unitPrice,
                    amount: qty * unitPrice,
                });
            }
            qtyTotal += qty;
        }

        const storedSubtotal = Number(row.subtotal ?? row.sub_total ?? 0);
        const lineSubtotal = storedSubtotal > 0
            ? storedSubtotal
            : rowProductItems(row).reduce((sum, item) => {
                const unit = productLineUnitPrice(item);
                return sum + (productLineQty(item) * unit);
            }, 0);
        const lineDiscount = Math.max(0, Number(row.discount ?? row.discountAmount ?? 0));
        const lineVat = Math.max(0, Number(row.vat ?? row.tax ?? 0));
        const lineShip = Math.max(0, Number(row.shippingFeeCollected ?? row.shipping_fee_collected ?? row.shippingFee ?? 0));
        const storedTotal = Number(row.total ?? 0);
        const lineTotal = storedTotal > 0
            ? storedTotal
            : Math.max(0, lineSubtotal - lineDiscount + lineShip);

        subtotal += lineSubtotal;
        discount += lineDiscount;
        vat += lineVat;
        shipping += lineShip;
        total += lineTotal;
    }

    return {
        products: Array.from(productMap.values())
            .filter((p) => p.qty > 0)
            .sort((a, b) => a.name.localeCompare(b.name, 'vi')),
        qtyTotal,
        subtotal,
        discount,
        vat,
        shipping,
        total,
        orderCount: sourceRows.length,
        scopedToSelection: false,
    };
}

function formatFooterMoney(value, { signed = false } = {}) {
    const n = Number(value) || 0;
    const text = formatNumber(Math.abs(n));
    if (signed && n !== 0) return `-${text}`;
    return text;
}

function WarehouseFooterDetailDialog({ open, onOpenChange, summary, labels }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="ps-wh-tfoot-detail-dialog max-w-5xl gap-3 p-4 sm:p-5" showClose>
                <DialogHeader className="gap-1 pr-8">
                    <DialogTitle className="text-base">
                        {labels.title}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        {labels.scope}
                    </DialogDescription>
                </DialogHeader>

                <div className="ps-wh-tfoot-detail-summary">
                    <div><span>{labels.orders}</span><b>{formatNumber(summary.orderCount)}</b></div>
                    <div><span>{labels.qty}</span><b>{formatNumber(summary.qtyTotal)}</b></div>
                    <div><span>{labels.subtotal}</span><b>{formatFooterMoney(summary.subtotal)}</b></div>
                    <div><span>{labels.discount}</span><b>{summary.discount ? formatFooterMoney(summary.discount, { signed: true }) : '0'}</b></div>
                    <div><span>{labels.shipping}</span><b>{formatFooterMoney(summary.shipping)}</b></div>
                    <div className="is-total"><span>{labels.total}</span><b>{formatFooterMoney(summary.total)}</b></div>
                </div>

                <div className="ps-wh-tfoot-detail-table-wrap">
                    <table className="ps-wh-tfoot-detail-table">
                        <thead>
                            <tr>
                                <th className="is-idx">#</th>
                                <th>{labels.product}</th>
                                <th>{labels.sku}</th>
                                <th className="is-num">{labels.qtyCol}</th>
                                <th className="is-num">{labels.unitPrice}</th>
                                <th className="is-num">{labels.amount}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {summary.products.length ? summary.products.map((line, index) => (
                                <tr key={`${line.sku || line.name}-${line.qty}-${index}`}>
                                    <td className="is-idx">{index + 1}</td>
                                    <td>{line.name}</td>
                                    <td className="is-muted">{line.sku || '—'}</td>
                                    <td className="is-num">x{formatNumber(line.qty)}</td>
                                    <td className="is-num">{line.unitPrice > 0 ? formatFooterMoney(line.unitPrice) : '—'}</td>
                                    <td className="is-num">{line.amount > 0 ? formatFooterMoney(line.amount) : '—'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={6} className="is-empty">—</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function WarehouseTableFooter({
    isAccounting,
    summary,
    onOpenDetail,
    viewDetailLabel,
}) {
    if (!summary.orderCount) return null;

    const productsCell = (
        <td className="text-left c-products-body ps-wh-tfoot-products">
            <button
                type="button"
                className="ps-wh-tfoot-detail-link"
                onClick={onOpenDetail}
            >
                {summary.qtyTotal} ({viewDetailLabel})
            </button>
        </td>
    );

    const moneyStack = (
        <div className="ps-wh-tfoot-money">
            <div>{formatFooterMoney(summary.subtotal)}</div>
            <div>{summary.discount ? formatFooterMoney(summary.discount, { signed: true }) : '0'}</div>
            <div>{formatFooterMoney(summary.vat)}</div>
            <div>{formatFooterMoney(summary.shipping)}</div>
            <div className="is-total"><b>{formatFooterMoney(summary.total)}</b></div>
        </div>
    );

    if (isAccounting) {
        return (
            <tfoot className="ps-wh-tfoot">
                <tr>
                    <td colSpan={7} className="ps-wh-tfoot-spacer" />
                    {productsCell}
                    <td className="text-right no-wrap c-money-sub">{formatFooterMoney(summary.subtotal)}</td>
                    <td className="text-right no-wrap c-money-ck">{summary.discount ? formatFooterMoney(summary.discount, { signed: true }) : ''}</td>
                    <td className="text-right no-wrap c-money-vat">{formatFooterMoney(summary.vat)}</td>
                    <td className="text-right no-wrap c-money-ship">{formatFooterMoney(summary.shipping)}</td>
                    <td className="text-right no-wrap c-money-total"><b>{formatFooterMoney(summary.total)}</b></td>
                    <td colSpan={6} className="ps-wh-tfoot-spacer" />
                </tr>
            </tfoot>
        );
    }

    return (
        <tfoot className="ps-wh-tfoot">
            <tr>
                <td colSpan={8} className="ps-wh-tfoot-spacer" />
                {productsCell}
                <td className="text-right no-wrap area3 c-money-body ps-wh-tfoot-money-cell">
                    {moneyStack}
                </td>
                <td colSpan={5} className="ps-wh-tfoot-spacer" />
            </tr>
        </tfoot>
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
    reconciliationStatuses = [],
    printButtons = [],
    exportButtons = [],
    shippingProviders = [],
    variant = 'warehouse',
    onClear,
    onReload,
}) {
    const t = useT();
    const appName = useAppName();
    const { ask } = useConfirm();
    const isAccounting = variant === 'accounting';
    const [open, setOpen] = useState(false);
    const [registerOpen, setRegisterOpen] = useState(false);
    const [handoverOpen, setHandoverOpen] = useState(false);
    const [ttghCodeOpen, setTtghCodeOpen] = useState(false);
    const [ttghExcelOpen, setTtghExcelOpen] = useState(false);
    const [reconCodeOpen, setReconCodeOpen] = useState(false);
    const [reconExcelOpen, setReconExcelOpen] = useState(false);
    const [exportBusy, setExportBusy] = useState(false);
    const selectedIds = selectedRows.map((row) => row.id);
    const selectedValidForShipment = selectedRows.filter((row) => row.canCreateShipment);

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
            toast.error(t('operations.warehouse_ops.no_orders_print'));
            return;
        }
        router.visit(`${actionApiBase}/print/${profileKey}?ids=${encodeURIComponent(ids.join(','))}`);
    };

    const createShipments = async () => {
        // Xuất âm: không chặn đăng vận đơn theo tồn thiếu.
        const targetRows = selectedRows.length
            ? selectedValidForShipment
            : pageRows.filter((row) => row.canCreateShipment);

        if (!selectedRows.length && !targetRows.length) {
            setRegisterOpen(true);
            return;
        }
        if (!targetRows.length) {
            toast.error(t('operations.warehouse_ops.no_orders_ship'));
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
            toast.success(t('operations.warehouse_ops.registered_count', { count: targetRows.length }));
            onClear();
            onReload();
        } catch (error) { toastApiError(error); }
    };

    const cancelShipments = async () => {
        const rows = resolveActionRows();
        if (!rows.length) {
            toast.error(t('operations.warehouse_ops.no_orders_cancel'));
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
            toast.success(t('operations.warehouse_ops.cancel_sent_count', { count: rows.length }));
            onClear();
            onReload();
        } catch (error) { toastApiError(error); }
    };

    const exportExcel = async (kind, type = 'standard') => {
        if (exportBusy) {
            toast.warning(t('operations.warehouse_ops.export_busy'));
            return;
        }
        const ids = resolveActionIds();
        if (!ids.length) {
            toast.error(t('operations.warehouse_ops.no_orders_export'));
            return;
        }
        setExportBusy(true);
        try {
            await postDownload(`${actionApiBase}/bulk/export`, {
                type,
                ids,
                filters,
            }, `warehouse-${type}.xls`);
            toast.success(t('operations.warehouse_ops.exported_count', { kind, count: ids.length }));
        } catch (error) {
            toastApiError(error);
        } finally {
            setExportBusy(false);
        }
    };

    const issueInvoices = () => {
        const ids = resolveActionIds();
        if (!ids.length) {
            toast.error(t('operations.warehouse_ops.no_orders_invoice'));
            return;
        }
        (async () => {
            try {
                const data = await postJson(`${actionApiBase}/bulk/invoices`, { ids, source: 'warehouse-actions' });
                toast.success(data.message || t('operations.warehouse_ops.invoice_requested', { count: ids.length }));
                onReload();
            } catch (error) { toastApiError(error); }
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
            toast.error(t('operations.warehouse_ops.no_orders_handover'));
            return;
        }
        setHandoverOpen(true);
    };

    const printRow = (
        <div className="icon-row" key="print">
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
    );
    const ttghRow = (
        <div className="icon-row" key="ttgh">
            <ActionMenuButton
                title={t('operations.warehouse_fab.ttgh')}
                icon="truck"
                tone={isAccounting ? 'primary' : 'success'}
                onClick={() => setTtghCodeOpen(true)}
            />
            <ActionMenuButton
                title={t('operations.warehouse_fab.ttgh_excel')}
                icon="truck"
                tone="warning"
                onClick={() => setTtghExcelOpen(true)}
            />
        </div>
    );
    const excelRow = (
        <div className="icon-row" key="excel">
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
    );
    const invoiceRow = (
        <div className="icon-row" key="invoice">
            {!isAccounting ? (
                <ActionMenuButton
                    title={t('operations.warehouse_fab.handover')}
                    icon="file-text-o"
                    tone="success"
                    onClick={openHandoverDialog}
                />
            ) : null}
            <ActionMenuButton
                title={t('operations.warehouse_fab.einvoice')}
                icon="barcode"
                tone="success"
                onClick={issueInvoices}
            />
        </div>
    );
    const reconRow = isAccounting ? (
        <div className="icon-row" key="recon">
            <ActionMenuButton
                title={t('operations.warehouse_fab.recon')}
                icon="sliders"
                tone="success"
                onClick={() => setReconCodeOpen(true)}
            />
            <ActionMenuButton
                title={t('operations.warehouse_fab.recon_excel')}
                icon="sliders"
                tone="warning"
                onClick={() => setReconExcelOpen(true)}
            />
        </div>
    ) : null;
    const bulkRow = (
        <div className="icon-row" key="bulk">
            <ActionMenuButton
                title={t('operations.warehouse_fab.bulk_by_code', { app: appName })}
                icon="gears"
                tone="success"
                onClick={openBulkUpdatePage}
            />
        </div>
    );

    // Accounting (htmk1): Đăng/Hủy → Print → TTGH → Đối soát → HĐĐT → Excel → mã PS
    // Warehouse: Đăng/Hủy → TTGH → Print → Excel → Handover+HĐĐT → mã PS
    const fabRows = isAccounting
        ? [printRow, ttghRow, reconRow, invoiceRow, excelRow, bulkRow]
        : [ttghRow, printRow, excelRow, invoiceRow, bulkRow];

    return (
        <>
            <nav className={`action-container ps-wh-floating-actions ${open ? 'open' : ''}`} onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)}>
                <div className="hidden-actions" aria-hidden={!open}>
                    <div className="icon-row">
                        <ActionMenuButton title={t('operations.warehouse_fab.register')} icon="calendar-check-o" tone="primary" onClick={createShipments} />
                        <ActionMenuButton title={t('operations.warehouse_fab.cancel_register')} icon="calendar-times-o" tone="warning" onClick={cancelShipments} />
                    </div>
                    {fabRows}
                </div>
                <button
                    type="button"
                    className="main-action ps-wh-main-action"
                    id="warehouseMenuToggle"
                    title={open ? t('operations.warehouse_fab.close_menu') : t('operations.warehouse_fab.open_menu')}
                    onClick={() => setOpen((value) => !value)}
                >
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
            {!isAccounting ? (
                <AddToHandoverDialog
                    open={handoverOpen}
                    onOpenChange={setHandoverOpen}
                    targetRows={resolveActionRows()}
                    providers={shippingProviders}
                    onDone={() => { onClear(); onReload(); }}
                />
            ) : null}
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
            {isAccounting ? (
                <>
                    <UpdateReconByCodeDialog
                        open={reconCodeOpen}
                        onOpenChange={setReconCodeOpen}
                        actionApiBase={actionApiBase}
                        initialCodes={resolveActionCodesText()}
                        reconciliationStatuses={reconciliationStatuses}
                        onDone={() => { onClear(); onReload(); }}
                    />
                    <UpdateReconExcelDialog
                        open={reconExcelOpen}
                        onOpenChange={setReconExcelOpen}
                        actionApiBase={actionApiBase}
                        onDone={() => { onClear(); onReload(); }}
                    />
                </>
            ) : null}
        </>
    );
}

function LegacyStatus({ row }) {
    const className = statusTone[row.deliveryStatusValue] ?? 'ttgh1';
    return <span className={`ps-wh-delivery-status no-wrap ${className}`}>{row.deliveryStatus || 'Chưa cập nhật'}</span>;
}

function CareNoteCell({ row, actionApiBase, onCare, onMessage }) {
    const t = useT();
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [expanded, setExpanded] = useState(false);

    const saveNote = async () => {
        const note = value.trim();
        if (!note) {
            toast.error(t('operations.warehouse_ops.internal_message_required'));
            return;
        }
        setSaving(true);
        try {
            await apiRequest(`${actionApiBase}/${row.id}/internal-message`, {
                method: 'POST',
                body: { message: note },
            });
            toast.success(t('operations.warehouse_ops.internal_message_saved'));
            setValue('');
            router.reload({ only: ['report', 'filters', 'filterOptions'] });
        } catch (error) {
            toastApiError(error);
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
    const t = useT();
    const isAccounting = variant === 'accounting';
    const [action, setAction] = useState(null);
    const [detailOrderId, setDetailOrderId] = useState(null);
    const [selected, setSelected] = useState([]);
    const [footerDetailOpen, setFooterDetailOpen] = useState(false);
    const checkAllRef = useRef(null);
    const { ask } = useConfirm();
    const authUserId = usePage().props?.auth?.user?.id;
    const rowIds = useMemo(() => rows.map((row) => String(row.id)), [rows]);
    const locks = useOrderLockPresence({ actionApiBase, orderIds: rowIds });
    const selectedRows = useMemo(() => rows.filter((row) => selected.includes(String(row.id))), [rows, selected]);
    const eligibleShipmentRows = useMemo(
        () => rows.filter((row) => row.canCreateShipment),
        [rows],
    );
    const footerSourceRows = selectedRows.length ? selectedRows : rows;
    const footerSummary = useMemo(
        () => ({
            ...buildWarehouseTableFooter(footerSourceRows),
            scopedToSelection: selectedRows.length > 0,
        }),
        [footerSourceRows, selectedRows.length],
    );
    const footerDetailLabels = useMemo(() => ({
        title: t('operations.warehouse_ops.footer_detail_title'),
        scope: footerSummary.scopedToSelection
            ? t('operations.warehouse_ops.footer_detail_scope_selected', { count: footerSummary.orderCount })
            : t('operations.warehouse_ops.footer_detail_scope_page', { count: footerSummary.orderCount }),
        orders: t('operations.warehouse_ops.footer_detail_orders'),
        qty: t('operations.warehouse_ops.footer_detail_qty'),
        subtotal: t('operations.warehouse_ops.footer_detail_subtotal'),
        discount: t('operations.warehouse_ops.footer_detail_discount'),
        shipping: t('operations.warehouse_ops.footer_detail_shipping'),
        total: t('operations.warehouse_ops.footer_detail_total'),
        product: t('operations.warehouse_ops.footer_detail_product'),
        sku: t('operations.warehouse_ops.footer_detail_sku'),
        qtyCol: t('operations.warehouse_ops.footer_detail_qty_col'),
        unitPrice: t('operations.warehouse_ops.footer_detail_unit_price'),
        amount: t('operations.warehouse_ops.footer_detail_amount'),
    }), [t, footerSummary.scopedToSelection, footerSummary.orderCount]);
    const allSelected = rowIds.length > 0 && rowIds.every((id) => selected.includes(id));

    const openAction = (next) => {
        const holder = locks[String(next.row?.id)];
        if (holder && Number(holder.user_id) !== Number(authUserId)) {
            const role = holder.role_label || holder.role || '';
            toast.error(t('operations.warehouse_ops.order_locked_by', {
                name: holder.user_name,
                role: role ? ` (${role})` : '',
            }));
            return;
        }
        setAction(next);
    };
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
        } catch (error) { toastApiError(error); }
    };

    const createShipment = async (row) => {
        try {
            await apiPost(`${apiBase}/${row.id}/create-shipment`);
            toast.success(t('operations.warehouse_ops.shipment_created', { code: row.orderCode }));
            reload();
        } catch (error) { toastApiError(error); }
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
            toast.success(t('operations.warehouse_ops.deleted_data'));
            reload();
        } catch (error) {
            toastApiError(error);
        }
    };

    return (
        <>
            <div className="ps-wh-table-shell dragscroll1 tableFixHead">
                <table className="table table-bordered table-multi-select table-sale ps-wh-table ps-wh-legacy-table">
                    <thead>
                        <tr className="drags-area hidden">
                            {isAccounting ? (
                                <>
                                    <th className="text-center" colSpan="8">GIAO VẬN</th>
                                    <th className="text-center" colSpan="10">THÔNG TIN ĐƠN HÀNG</th>
                                    <th className="text-center" colSpan="2">THÔNG TIN GIAO HÀNG</th>
                                </>
                            ) : (
                                <>
                                    <th className="text-center" colSpan="11">THÔNG TIN ĐƠN HÀNG</th>
                                    <th className="text-center" colSpan="4">THÔNG TIN GIAO HÀNG</th>
                                </>
                            )}
                        </tr>
                        <tr className="drags-area">
                            <th className="text-center c-check"><span className="chk-all"><input ref={checkAllRef} type="checkbox" checked={allSelected} onChange={toggleAll} /><label>&nbsp;</label></span></th>
                            <th className="text-center c-sale">Sale</th>
                            <th className="text-center no-wrap c-order">Ngày data về<br />Mã đơn<br />Ngày chốt đơn</th>
                            <th className="text-center no-wrap c-shipper">Kho<br /><span title="Phương thức giao hàng" style={{ display: 'inline-block', minWidth: 120 }}>PTGH</span><br />Mã giao vận</th>
                            <th className="text-center no-wrap c-care">Ngày cập nhật care đơn<br /><span className="span-col" style={{ display: 'inline-block', minWidth: 180 }}>Care đơn<br />Ghi chú kế toán</span></th>
                            <th className="text-center no-wrap c-status">Ngày cập nhật<br />Trạng thái giao hàng<br />Ngày đăng đơn</th>
                            {isAccounting ? (
                                <>
                                    <th className="text-center c-recon" title="Đối soát nội bộ">ĐSNB</th>
                                    <th className="text-left no-wrap c-products">
                                        <span style={{ display: 'inline-block', minWidth: 200 }}>Sản phẩm - Số lượng - Đơn giá</span>
                                        <br />
                                        Mã HĐĐT
                                    </th>
                                    <th className="text-right no-wrap c-money-sub">Thành tiền</th>
                                    <th className="text-center no-wrap c-money-ck">CK</th>
                                    <th className="text-center no-wrap c-money-vat">Tiền<br />VAT SP</th>
                                    <th className="text-center no-wrap c-money-ship">Phí VC<br />thu của khách</th>
                                    <th className="text-center no-wrap c-money-total">Tổng tiền</th>
                                    <th className="text-center no-wrap c-deposit">Đặt cọc</th>
                                    <th className="text-center no-wrap c-cod">Tiền thu<br />của khách</th>
                                    <th className="text-center c-fee">Giá dịch vụ<br />VC</th>
                                    <th className="text-center c-fee">Phí VC<br />hỗ trợ khách</th>
                                    <th className="text-center no-wrap c-customer"><span style={{ display: 'inline-block', minWidth: 100 }}>Họ tên</span><br />Số điện thoại<br />Ngày muốn nhận hàng</th>
                                    <th className="text-center c-address"><span style={{ display: 'inline-block', width: 120 }}>Địa chỉ<br />Ghi chú giao hàng</span></th>
                                </>
                            ) : (
                                <>
                                    <th className="text-center no-wrap c-customer"><span>Họ tên</span><br />Số điện thoại<br />Ngày muốn nhận hàng</th>
                                    <th className="text-center c-address"><span>Địa chỉ<br />Ghi chú giao hàng</span><br />Hóa đơn điện tử</th>
                                    <th className="text-left no-wrap c-products"><span>Sản phẩm - Số lượng - Đơn giá</span></th>
                                    <th className="text-center no-wrap area3 c-money"><span>Thành tiền<br />CK / VAT SP<br />Phí VC / Tổng tiền</span></th>
                                    <th className="text-center no-wrap c-deposit">Đặt cọc</th>
                                    <th className="text-center no-wrap c-cod">Tiền thu<br />của khách</th>
                                    <th className="text-center c-fee">Giá dịch vụ<br />VC</th>
                                    <th className="text-center c-fee">Phí VC<br />hỗ trợ khách</th>
                                    <th className="text-center c-recon" title="Đối soát nội bộ">ĐSNB</th>
                                </>
                            )}
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
                                            onClick={() => openAction({ type: 'changeCode', row })}
                                        >
                                            <i className="fa fa-repeat" />
                                        </button>
                                    </div>
                                    <div className="small-tip sline">{formatDateTime(row.dataArrivedAt, { withSeconds: true })}</div>
                                    <div>
                                        <button type="button" className="item-md ps-wh-order-code" onClick={() => setDetailOrderId(row.id)}>{row.orderCode}</button>
                                    </div>
                                    {locks[String(row.id)] && Number(locks[String(row.id)].user_id) !== Number(authUserId) ? (
                                        <div className="small-tip ps-wh-lock-badge" title="Đơn đang được thao tác">
                                            Đang thao tác: {locks[String(row.id)].user_name}
                                            {locks[String(row.id)].role_label ? ` (${locks[String(row.id)].role_label})` : ''}
                                        </div>
                                    ) : null}
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
                                    onCare={() => openAction({ type: 'care', row })}
                                    onMessage={() => openAction({ type: 'message', row })}
                                />
                                <td className="text-center area4 ps-wh-delivery-cell">
                    <div className="ps-wh-delivery-stack">
                        <div className="small-tip ps-wh-delivery-date">{formatDateTime(row.lastDeliveryEventAt, { withSeconds: true }) || '—'}</div>
                        <div className="ps-wh-delivery-actions no-wrap">
                            {isAccounting ? (
                                <InlineIconButton title="Đồng bộ trạng thái giao hàng" icon="retweet" onClick={() => openAction({ type: 'delivery', row })} />
                            ) : (
                                <InlineIconButton title="Đưa vào danh sách cảnh báo bom hàng" icon="bomb" onClick={() => openAction({ type: 'blacklist', row })} />
                            )}
                            <InlineIconButton title="Cập nhật trạng thái giao hàng" icon="refresh" onClick={() => openAction({ type: 'delivery', row })} />
                            <InlineIconButton title="Lịch sử tác nghiệp" icon="history" onClick={() => openAction({ type: 'timeline', row })} />
                        </div>
                        <LegacyStatus row={row} />
                        <div className="small-tip sline ps-wh-delivery-date">{formatDateTime(row.shipmentPostedAt || row.printedAt, { withSeconds: false }) || row.shipment?.statusText || '—'}</div>
                        {row.shipmentError ? <div className="ps-wh-error text-left">{row.shipmentError}</div> : null}
                    </div>
                </td>
                {isAccounting ? (
                    <>
                        <td className="text-center c-recon-body">
                            {row.reconciliationStatus && !['pending', 'none', 'null'].includes(String(row.reconciliationStatus).toLowerCase()) ? (
                                <>
                                    <span>{row.reconciliationStatusLabel || row.reconciliationStatus}</span>
                                    <br />
                                    <span className="small-tip">{row.reconciliationUpdatedAt || ''}</span>
                                </>
                            ) : (
                                <i className="fa fa-circle-o ps-acc-recon-empty" title="Chưa đối soát nội bộ" />
                            )}
                        </td>
                        <td className="text-left c-products-body">
                            <OrderProductsBreakdown items={row.products || [...(row.mainProducts || []), ...(row.upsellProducts || [])]} order={row} />
                            {row.einvoiceCode || row.invoiceCode ? (
                                <div className="small-tip sline">{row.einvoiceCode || row.invoiceCode}</div>
                            ) : null}
                        </td>
                        <td className="text-right no-wrap c-money-sub">{formatCurrency(row.subtotal)}</td>
                        <td className="text-right no-wrap c-money-ck">{Number(row.discount) ? formatCurrency(row.discount) : ''}</td>
                        <td className="text-right no-wrap c-money-vat">{Number(row.vat) ? formatCurrency(row.vat) : ''}</td>
                        <td className="text-right no-wrap c-money-ship">{Number(row.shippingFeeCollected) ? formatCurrency(row.shippingFeeCollected) : ''}</td>
                        <td className="text-right no-wrap c-money-total"><strong>{formatCurrency(row.total)}</strong></td>
                        <td className="text-right">{formatCurrency(row.deposit)}</td>
                        <td className="text-right">{formatCurrency(row.codAmount || row.total)}</td>
                        <td className="text-right">{formatCurrency(row.carrierServiceFee)}</td>
                        <td className="text-right">{formatCurrency(row.carrierReturnFee || row.carrierOtherFee || row.codFee)}</td>
                        <td className="text-center c-customer-body ps-contact-name-phone" title={`${row.id} | ${row.sourceType || ''}`}>
                            <div className="text-right">
                                <InlineIconButton title="Cập nhật ngày muốn nhận hàng" icon="calendar" onClick={() => openAction({ type: 'date', row })} />
                                <InlineIconButton title="Cập nhật đơn vị giao vận" icon="truck" onClick={() => openAction({ type: 'edit', row })} />
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
                    </>
                ) : (
                    <>
                        <td className="text-center c-customer-body ps-contact-name-phone" title={`${row.id} | ${row.sourceType || ''}`}>
                            <div className="text-right">
                                <InlineIconButton title="Cập nhật ngày muốn nhận hàng" icon="calendar" onClick={() => openAction({ type: 'date', row })} />
                                <InlineIconButton title="Tách đơn" icon="clipboard" onClick={() => openAction({ type: 'split', row })} disabled={!row.canSplit} />
                                <InlineIconButton title="Cập nhật đơn vị giao vận" icon="truck" onClick={() => openAction({ type: 'edit', row })} />
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
                    </>
                )}
                            </tr>
                        )) : <tr><td colSpan={isAccounting ? 19 : 15} className="ps-wh-empty">Không có đơn phù hợp bộ lọc.</td></tr>}
                    </tbody>
                    <WarehouseTableFooter
                        isAccounting={isAccounting}
                        summary={footerSummary}
                        onOpenDetail={() => setFooterDetailOpen(true)}
                        viewDetailLabel={t('operations.view_detail')}
                    />
                </table>
            </div>

            <WarehouseFooterDetailDialog
                open={footerDetailOpen}
                onOpenChange={setFooterDetailOpen}
                summary={footerSummary}
                labels={footerDetailLabels}
            />

            <FloatingWarehouseActions
                selectedRows={selectedRows}
                eligibleShipmentRows={eligibleShipmentRows}
                pageRows={rows}
                filters={filters}
                apiBase={apiBase}
                actionApiBase={actionApiBase}
                deliveryStatuses={filterOptions.deliveryStatuses ?? []}
                reconciliationStatuses={filterOptions.reconciliationStatuses ?? []}
                printButtons={printButtons}
                exportButtons={exportButtons}
                shippingProviders={filterOptions.shippingProviders ?? []}
                variant={variant}
                onClear={() => setSelected([])}
                onReload={reload}
            />
            {selectedRows.length > 0 && <div className="ps-wh-selected-hint">Đã chọn {selectedRows.length} đơn. Mở nút chức năng màu xanh bên trái để xử lý hàng loạt.</div>}
            <WarehouseActionDialogs action={action} onClose={() => setAction(null)} actionApiBase={actionApiBase} filterOptions={filterOptions} />
            <ShippingOrderDetailDialog orderId={detailOrderId} open={Boolean(detailOrderId)} onOpenChange={(open) => !open && setDetailOrderId(null)} apiBase={apiBase} />
        </>
    );
}
