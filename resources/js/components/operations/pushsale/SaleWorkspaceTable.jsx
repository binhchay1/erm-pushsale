import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { CustomerSupplementPacketsDialog } from '@/components/customers/CustomerSupplementPacketsDialog';
import { OrderMoneyBreakdown, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import {
    CustomerContactCell,
    DeliveryStatusCell,
    MessageCell,
    NextOperationCell,
    OperationResultCell,
    OpsIconButton,
    OrderCodeCell,
    SaleAssigneeCell,
    SourceDataCell,
    TimeRemainingCell,
    moneyDisplay,
} from '@/components/operations/cells/OpsTableCells';
import { formatCurrency } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';
import { useConfirm } from '@/hooks/use-confirm';
import { PushsalePagination } from './PushsalePagination';

function CallButton({ order, actionBaseUrl }) {
    const call = () => router.post(`${actionBaseUrl}/orders/${order.id}/call`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã ghi nhận cuộc gọi.'),
        onError: (errors) => toast.error(errors.order ?? 'Không thể ghi nhận cuộc gọi.'),
    });

    return (
        <OpsIconButton
            title="Ghi nhận cuộc gọi"
            icon="phone"
            onClick={call}
            disabled={!order.canCall}
        />
    );
}

function OperationNeededCell({ order, actionBaseUrl, onMessages }) {
    const t = useT();
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [focused, setFocused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const textareaRef = useRef(null);
    const expanded = focused || hovered;

    useEffect(() => {
        const node = textareaRef.current;
        if (!node) return;
        node.style.height = expanded ? '128px' : '48px';
    }, [expanded]);

    const save = () => {
        setSaving(true);
        router.patch(`${actionBaseUrl}/orders/${order.id}/operation-note`, { note: value }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('operations.sale_workspace.note_saved'));
                setValue('');
            },
            onError: (errors) => toast.error(errors.note ?? errors.order ?? t('operations.sale_workspace.note_save_failed')),
            onFinish: () => setSaving(false),
        });
    };

    // Hover phóng / leave thu; click(focus) giữ phóng; blur thu lại.
    return (
        <td className={`area2 hidden-xs ps-operation-note-editor${expanded ? ' is-expanded' : ''}${focused ? ' is-focused' : ''}`}>
            <span className="fb span-col ttgh7" style={{ cursor: 'pointer', display: 'block', marginTop: 2 }}>
                {order.currentOperation || t('operations.sale_workspace.default_stage')}
            </span>
            <div className="text-right">
                <OpsIconButton
                    title={t('operations.sale_workspace.internal_message')}
                    icon="commenting-o"
                    onClick={() => onMessages(order)}
                    style={{ float: 'left' }}
                />
                <div className="text-right">
                    <OpsIconButton
                        title={t('operations.sale_workspace.save_note')}
                        icon="save"
                        onClick={save}
                        disabled={saving}
                    />
                </div>
            </div>
            <div
                className="mof-container ps-note-mof"
                onMouseEnter={() => setHovered(true)}
                onMouseLeave={() => {
                    if (!focused) setHovered(false);
                }}
            >
                <textarea
                    ref={textareaRef}
                    className="form-control txt-mof txt-dotted"
                    maxLength={500}
                    rows={2}
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onFocus={() => {
                        setFocused(true);
                        setHovered(true);
                    }}
                    onBlur={() => {
                        setFocused(false);
                        setHovered(false);
                    }}
                    onKeyDown={(event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') save();
                    }}
                    aria-label={t('operations.sale_workspace.note_aria', { name: order.customerName ?? order.customerPhone })}
                    title={t('operations.sale_workspace.note_helper')}
                    placeholder=""
                />
            </div>
            <div style={{ clear: 'both' }} />
            <span className="lnk-noidung-other" style={{ marginTop: 4, display: 'inline-block' }} />
        </td>
    );
}

export function SaleWorkspaceTable({
    rows,
    meta,
    filters,
    routeUrl,
    actionBaseUrl,
    operationStatusOptions = [],
    onEdit,
    onHistory,
    onDataViewHistory,
    onMessages,
    onPurchaseHistory,
    onDuplicateOrders,
    onDesiredDate,
    onResult,
    onBulkClose,
}) {
    const { ask } = useConfirm();
    const [selected, setSelected] = useState([]);
    const checkAllRef = useRef(null);
    const rowIds = useMemo(() => rows.map((row) => String(row.id)), [rows]);
    const allSelected = rowIds.length > 0 && rowIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    useEffect(() => {
        setSelected((current) => current.filter((id) => rowIds.includes(id)));
    }, [rowIds.join('|')]);

    useEffect(() => {
        if (checkAllRef.current) checkAllRef.current.indeterminate = someSelected;
    }, [someSelected]);

    const toggleAll = () => setSelected(allSelected ? [] : rowIds);
    const toggle = (id) => setSelected((current) => (
        current.includes(id) ? current.filter((item) => item !== id) : [...current, id]
    ));

    const deleteData = async (order) => {
        const ok = await ask({
            description: `Bạn chắc chắn muốn xóa data của ${order.customerName || order.customerPhone || `#${order.id}`}?`,
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${actionBaseUrl}/orders/${order.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa data.'),
            onError: (errors) => toast.error(errors.order ?? 'Không thể xóa data.'),
        });
    };

    return (
        <>
            <div className="table-responsive ps-sale-table-wrap">
                <table className="table table-bordered table-multi-select table-sale ps-sale-operation-table">
                    <thead>
                        <tr className="drags-area">
                            <th>
                                <span className="chk-all">
                                    <input ref={checkAllRef} type="checkbox" checked={allSelected} onChange={toggleAll} aria-label="Chọn tất cả" />
                                </span>
                            </th>
                            <th>Mã đơn</th>
                            <th className="text-center no-wrap area5 hidden-xs ps-col-source">
                                <span className="span-col" style={{ width: 100 }}>Nguồn dữ liệu</span>
                                <br />
                                Ngày data về
                            </th>
                            <th className="text-center no-wrap area5 hidden-xs">
                                <span className="span-col" style={{ minWidth: 120, width: 120 }}>
                                    Sale
                                    <br />
                                    Ngày nhận data
                                </span>
                            </th>
                            <th className="text-center no-wrap area1">
                                <span className="span-col text-center">
                                    Họ tên
                                    <br />
                                    <span className="span-col" style={{ display: 'inline-block', minWidth: 130 }}>Số điện thoại</span>
                                    <br />
                                    Ngày muốn nhận hàng
                                </span>
                            </th>
                            <th className="text-center no-wrap area1 hidden-xs">
                                <span className="span-col td-message td-793">Tin nhắn</span>
                            </th>
                            <th className="text-center no-wrap area2 hidden-xs">
                                <span className="span-col" style={{ display: 'inline-block', minWidth: 150 }}>TN cần</span>
                            </th>
                            <th className="text-center no-wrap area2">
                                <span className="span-col" style={{ width: 150 }}>Kết quả</span>
                            </th>
                            <th className="text-center no-wrap area2 hidden-xs">
                                <span className="span-col" style={{ minWidth: 80 }}>TN tiếp</span>
                            </th>
                            <th className="text-center no-wrap area2 hidden-xs">
                                <span className="span-col">Sau</span>
                                <br />
                                Còn lại
                            </th>
                            <th className="text-center no-wrap area3 hidden-xs">
                                <span className="span-col" style={{ display: 'inline-block', minWidth: 200 }}>Sản phẩm - Số lượng - Đơn giá</span>
                            </th>
                            <th className="text-center no-wrap area3 hidden-xs">
                                <span className="span-col">
                                    Thành tiền
                                    <br />
                                    CK / VAT SP
                                    <br />
                                    Phí VC / Tổng tiền
                                </span>
                            </th>
                            <th className="text-center no-wrap area3 hidden-xs">
                                <span className="span-col">Đặt cọc</span>
                            </th>
                            <th className="text-center no-wrap area4">
                                <span className="span-col">
                                    Trạng thái giao hàng
                                    <br />
                                    Ngày muốn nhận hàng
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((order, index) => {
                            const id = String(order.id);
                            const isSelected = selected.includes(id);

                            return (
                                <tr
                                    key={order.id}
                                    data-id={order.id}
                                    className={`contact-row item${order.id} ${order.closedAt ? 'is-closed' : ''} ${isSelected ? 'row-selected' : ''}`}
                                >
                                    <td className="text-center">
                                        <span className="chk-item">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => toggle(id)}
                                                aria-label={`Chọn ${order.customerName ?? order.customerPhone}`}
                                                id={`sale-chk-${order.id}`}
                                            />
                                            <label htmlFor={`sale-chk-${order.id}`}>{Number(meta?.from ?? 1) + index}</label>
                                        </span>
                                    </td>

                                    <OrderCodeCell
                                        orderCode={order.orderCode}
                                        onHistory={() => onDataViewHistory(order)}
                                    />

                                    <SourceDataCell
                                        sourceName={order.sourceName}
                                        sourceUrl={order.sourceUrl}
                                        dataArrivedAt={order.dataArrivedAt}
                                    />

                                    <SaleAssigneeCell
                                        saleName={order.saleName}
                                        saleUsername={order.saleUsername}
                                        assignedAt={order.assignedAt}
                                        canDelete={Boolean(order.canDeleteData)}
                                        onDelete={() => deleteData(order)}
                                    />

                                    <CustomerContactCell
                                        order={order}
                                        onEdit={() => onEdit(order, false)}
                                        onPurchaseHistory={() => onPurchaseHistory(order)}
                                        onDuplicateOrders={() => onDuplicateOrders(order)}
                                        phoneActions={<CallButton order={order} actionBaseUrl={actionBaseUrl} />}
                                        flags={(
                                            <OrderStatusFlags
                                                row={order}
                                                onDuplicate={onDuplicateOrders ? () => onDuplicateOrders(order) : null}
                                                className="ps-contact-flags"
                                            />
                                        )}
                                        supplement={order.pendingSupplementCount > 0 ? (
                                            <CustomerSupplementPacketsDialog order={order} count={order.pendingSupplementCount} />
                                        ) : null}
                                    />

                                    <MessageCell
                                        note={order.customerNote}
                                        onClick={() => onMessages(order)}
                                    />

                                    <OperationNeededCell
                                        order={order}
                                        actionBaseUrl={actionBaseUrl}
                                        onMessages={onMessages}
                                    />

                                    <OperationResultCell
                                        canChangeStatus={Boolean(order.canChangeStatus)}
                                        options={operationStatusOptions}
                                        currentLabel={order.operationResult || order.closingStatusLabel || ''}
                                        onHistory={() => onHistory(order)}
                                        onChange={(option) => onResult(order, option)}
                                    />

                                    <NextOperationCell
                                        nextOperationAt={order.nextOperationAt}
                                        onEdit={() => onDesiredDate(order)}
                                    />

                                    <TimeRemainingCell nextOperationAt={order.nextOperationAt} />

                                    <td className="text-left area3 hidden-xs ps-col-products">
                                        <OrderProductsBreakdown items={order.products ?? []} order={order} />
                                    </td>

                                    <td className="area3 text-right hidden-xs ps-col-money">
                                        <OrderMoneyBreakdown row={order} items={order.products ?? []} />
                                    </td>

                                    <td className="no-wrap area3 text-right hidden-xs">
                                        {moneyDisplay(order.deposit) || (Number(order.deposit) === 0 ? formatCurrency(0) : '')}
                                    </td>

                                    <DeliveryStatusCell
                                        deliveryStatus={order.deliveryStatus}
                                        deliveryStatusValue={order.deliveryStatusValue}
                                        trackingNumber={order.trackingNumber}
                                        desiredDeliveryAt={order.desiredDeliveryAt}
                                        onCalendar={() => onDesiredDate(order)}
                                        onHistory={() => onHistory(order, 'accounting')}
                                        showAccountingHistory={Boolean(order.closedAt)}
                                    />
                                </tr>
                            );
                        })}
                        {!rows.length && (
                            <tr>
                                <td colSpan={14} className="ps-sale-empty text-center">Không có dữ liệu phù hợp.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <PushsalePagination meta={meta} routeUrl={routeUrl} filters={filters} />

            {selected.length > 0 && (
                <div className="ps-sale-selection-bar">
                    <strong>Đã chọn {selected.length} dòng</strong>
                    <button type="button" className="btn btn-primary btn-sm" onClick={() => onBulkClose(selected)}>
                        <i className="fa fa-check-square-o" /> Chốt đơn nhiều
                    </button>
                    <button type="button" className="btn btn-default btn-sm" onClick={() => setSelected([])}>Bỏ chọn</button>
                </div>
            )}
        </>
    );
}
