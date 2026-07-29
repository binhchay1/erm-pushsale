import { useEffect, useState } from 'react';

import { ConfirmActionDialog } from '@/components/ui/ConfirmActionDialog';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

function emptySegment(name = 'Phân loại mới') {
    return {
        id: Date.now(),
        name,
        color: '#337ab7',
        min_successful_order_value: 0,
    };
}

export function CustomerSegmentDialog({
    open,
    onClose,
    segments = [],
    onSave,
    onRecalculate,
    processing = false,
    recalcStatus = null,
}) {
    const [rows, setRows] = useState(segments);
    const [confirmRecalc, setConfirmRecalc] = useState(false);

    useEffect(() => {
        if (!open) return;
        setRows((segments?.length ? segments : [emptySegment('Khách mới')]).map((row, index) => ({
            id: row.id ?? index + 1,
            name: row.name ?? '',
            color: row.color ?? '#337ab7',
            min_successful_order_value: Number(row.min_successful_order_value ?? 0),
        })));
    }, [open, segments]);

    return (
        <>
            <PushsaleDialog
                open={open}
                onOpenChange={(next) => !next && onClose()}
                title="Thiết lập phân loại khách hàng"
                width="920px"
                bodyClassName="ps-dialog-body ps-segment-dialog"
                footer={(
                    <>
                        <button type="button" className="btn btn-default" disabled={processing} onClick={onClose}>Đóng</button>
                        <button type="button" className="btn btn-primary" disabled={processing} onClick={() => onSave(rows)}>
                            <i className={`fa ${processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Lưu
                        </button>
                    </>
                )}
            >
                <div className="ps-segment-toolbar">
                    <button type="button" className="btn btn-sm btn-primary" disabled={processing} onClick={() => setConfirmRecalc(true)}>
                        <i className="fa fa-calculator" /> Tính toán phân loại khách hàng
                    </button>
                    <span className="ps-segment-status">
                        {recalcStatus || 'Chưa có tác vụ tính toán phân loại'}
                    </span>
                </div>

                <div className="ps-segment-table-wrap">
                    <table className="table table-bordered ps-segment-table">
                        <thead>
                            <tr>
                                <th className="text-center">STT</th>
                                <th>Tên phân loại</th>
                                <th>Tổng giá trị đơn thành công từ</th>
                                <th className="text-center">Cập nhật</th>
                                <th className="text-center">
                                    <button type="button" className="btn-icon ps-segment-add-th" onClick={() => setRows((current) => [...current, emptySegment()])}>
                                        <i className="fa fa-plus" /> <span className="text">+ Thêm</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={row.id ?? index}>
                                    <td className="text-center">{index + 1}</td>
                                    <td>
                                        <input
                                            className="form-control"
                                            value={row.name}
                                            onChange={(event) => setRows((current) => current.map((item, i) => i === index ? { ...item, name: event.target.value } : item))}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            min="0"
                                            className="form-control"
                                            value={row.min_successful_order_value}
                                            onChange={(event) => setRows((current) => current.map((item, i) => i === index ? { ...item, min_successful_order_value: Number(event.target.value || 0) } : item))}
                                        />
                                    </td>
                                    <td className="text-center">—</td>
                                    <td className="text-center">
                                        <button type="button" className="btn-icon" title="Xóa" onClick={() => setRows((current) => current.filter((_, i) => i !== index))}>
                                            <i className="fa fa-trash" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </PushsaleDialog>

            <ConfirmActionDialog
                open={confirmRecalc}
                tone="primary"
                title="Tính toán phân loại khách hàng"
                description="Tác vụ này sẽ được hệ thống ghi nhận và tính toán ngầm, khách hàng 360 sẽ được phân loại lại toàn bộ. Chắc chắn bạn muốn thực hiện tác vụ này?"
                confirmLabel="Đồng ý"
                cancelLabel="Hủy"
                processing={processing}
                onCancel={() => setConfirmRecalc(false)}
                onConfirm={async () => {
                    setConfirmRecalc(false);
                    await onRecalculate?.();
                }}
            />
        </>
    );
}
