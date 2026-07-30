import { OrderMoneyCell, OrderProductsBreakdown, OrderStatusFlags } from '@/components/operations/OrderLineBreakdown';
import { DeleteRowButton } from '@/components/ui/delete-row-button';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function money(value) {
    if (value === null || value === undefined || value === '') return '—';
    return formatCurrency(Number(value || 0));
}

function dateTime(value) {
    return value ? formatDateTime(value) : '—';
}

function ReconIcon({ value }) {
    return value ? (
        <i className="fa fa-check-circle ps-acc-recon-ok" title="Đã đối soát nội bộ" />
    ) : (
        <i className="fa fa-circle-o ps-acc-recon-empty" title="Chưa đối soát nội bộ" />
    );
}

function CustomerCell({ row }) {
    return (
        <div className="ps-acc-customer-cell ps-contact-name-phone">
            <b className="ps-customer-name-text">{row.customerName || '—'}</b>
            {row.carrierLabel || row.phoneCarrier ? (
                <div className="nha-mang ps-contact-carrier-line">{row.carrierLabel || `[${row.phoneCarrier}]`}</div>
            ) : null}
            <div className="ps-contact-phone-row">
                <span className="ps-phone-text">{row.customerPhone || '—'}</span>
            </div>
            <div className="ps-contact-flags-row">
                <OrderStatusFlags row={row} className="ps-contact-flags" />
            </div>
            {row.desiredDeliveryAt && <div className="small-tip">{formatDate(row.desiredDeliveryAt)}</div>}
            {row.hasDifferentReceiver && (
                <div className="small-tip ps-acc-receiver">NN: {row.effectiveReceiverName}{row.effectiveReceiverPhone ? ` · ${row.effectiveReceiverPhone}` : ''}</div>
            )}
        </div>
    );
}

export function AccountingReconTable({ rows = [], totals, enableDeleteOrder = false }) {
    const t = useT();
    const colCount = 15 + (enableDeleteOrder ? 1 : 0);

    return (
        <div className="table-responsive ps-acc-table-wrap">
            <table className="table table-bordered table-striped ps-acc-operation-table">
                <thead>
                    <tr>
                        <th className="text-center ps-col-stt">STT</th>
                        <th className="text-center ps-col-sale">Sale</th>
                        <th className="text-center ps-col-code">Ngày data về<br />Mã đơn<br />Ngày chốt đơn</th>
                        <th className="text-center ps-col-warehouse">Kho / PTGH / Mã giao vận</th>
                        <th className="text-center ps-col-care">Care đơn / Ghi chú KT</th>
                        <th className="text-center ps-col-delivery">Trạng thái giao hàng<br />Ngày đăng đơn</th>
                        <th className="text-center ps-col-recon" title="Đối soát nội bộ">ĐSNB</th>
                        <th className="text-center ps-col-products">Sản phẩm - SL - Đơn giá</th>
                        <th className="text-center ps-col-money">Thành tiền<br />CK / VAT SP<br />Phí VC / Tổng tiền</th>
                        <th className="text-center ps-col-deposit">Đặt cọc</th>
                        <th className="text-center ps-col-collect">Tiền thu<br />của khách</th>
                        <th className="text-center ps-col-vc">Giá dịch vụ VC</th>
                        <th className="text-center ps-col-support">Phí VC<br />hỗ trợ khách</th>
                        <th className="text-center ps-col-customer">Họ tên / Số điện thoại</th>
                        <th className="text-center ps-col-address">Địa chỉ<br />Ghi chú giao hàng</th>
                        {enableDeleteOrder && <th className="text-center ps-col-action">Thao tác</th>}
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row, index) => (
                        <tr key={row.id} className="contact-row ps-acc-row">
                            <td className="text-center ps-col-stt">{index + 1}</td>
                            <td className="text-center ps-col-sale"><b>{row.saleName}</b><br /><span className="small-tip">{row.saleGroup}</span></td>
                            <td className="text-center ps-col-code">
                                <span className="small-tip">{dateTime(row.dataArrivedAt)}</span><br />
                                <b className="ps-order-code-text">{row.orderCode || '—'}</b><br />
                                <span className="small-tip">{dateTime(row.closedAt)}</span>
                            </td>
                            <td className="text-center ps-col-warehouse">
                                <b>{row.warehouseName || '—'}</b><br />
                                <span className="text-success">{row.carrierName || row.shippingProvider || 'Thủ công'}</span><br />
                                <span className="text-warning">{row.trackingNumber || '—'}</span>
                            </td>
                            <td className="ps-col-care">
                                <span className="ps-care-name">{row.carePersonName || row.saleName || '—'}</span><br />
                                <span className="small-tip">{row.accountingNotes || '—'}</span>
                            </td>
                            <td className="text-center ps-col-delivery">
                                <span className={`ps-acc-delivery-badge ttgh-${row.deliveryStatusValue || 'none'}`}>{row.deliveryStatus || '—'}</span><br />
                                <span className="small-tip">{row.desiredDeliveryAt ? dateTime(row.desiredDeliveryAt) : '—'}</span>
                            </td>
                            <td className="text-center ps-col-recon"><ReconIcon value={row.internalReconNote} /></td>
                            <td className="ps-col-products"><OrderProductsBreakdown items={row.products ?? []} order={row} /></td>
                            <OrderMoneyCell className="ps-col-money" row={row} />
                            <td className="text-right ps-col-deposit">{money(row.deposit)}</td>
                            <td className="text-right ps-col-collect"><strong className="text-success">{money(row.amountToCollect)}</strong></td>
                            <td className="text-right ps-col-vc">{money(row.carrierServiceFee)}</td>
                            <td className="text-right ps-col-support">{money(row.shippingSupportFee)}</td>
                            <td className="ps-col-customer"><CustomerCell row={row} /></td>
                            <td className="ps-col-address">
                                <div>{row.effectiveShippingAddress || row.shippingAddress || '—'}</div>
                                {(row.shippingNotes || row.customerNote) && (
                                    <div className="small-tip text-fuchsia">{row.shippingNotes || row.customerNote}</div>
                                )}
                            </td>
                            {enableDeleteOrder && (
                                <td className="text-center ps-col-action">
                                    <DeleteRowButton
                                        url={`/admin/orders/${row.id}`}
                                        label={row.orderCode || row.customerPhone}
                                        confirmMessage={t('operations.delete_order_confirm', { code: row.orderCode || row.customerPhone })}
                                    />
                                </td>
                            )}
                        </tr>
                    )) : (
                        <tr><td colSpan={colCount} className="text-center ps-acc-empty">Không có dữ liệu phù hợp.</td></tr>
                    )}
                </tbody>
                {rows.length > 0 && totals && (
                    <tfoot>
                        <tr className="ps-acc-total-row">
                            <td colSpan={7} className="text-right"><b>Tổng:</b></td>
                            <td className="text-center"><b>{formatNumber(totals.quantity ?? 0)}</b></td>
                            <OrderMoneyCell className="" row={totals} />
                            <td className="text-right"><b>{money(totals.deposit)}</b></td>
                            <td className="text-right"><b className="text-success">{money(totals.amountToCollect)}</b></td>
                            <td className="text-right"><b>{money(totals.carrierServiceFee)}</b></td>
                            <td className="text-right"><b>{money(totals.shippingSupportFee)}</b></td>
                            <td colSpan={enableDeleteOrder ? 3 : 2} />
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}
