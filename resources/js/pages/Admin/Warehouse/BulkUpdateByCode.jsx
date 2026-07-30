import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { apiRequest } from '@/lib/api';
import { useConfirm } from '@/hooks/use-confirm';

const ACTION_FIELDS = {
    CAP_NHAT_DON: ['warehouse', 'dims', 'shipping', 'notes'],
    CAP_NHAT_TTGH: ['delivery_status', 'note'],
    CAP_NHAT_GHI_CHU_KE_TOAN: ['accounting_note'],
    CAP_NHAT_TTDS: ['reconciliation_status'],
    DANG_DON: [],
    HUY_DANG_DON: [],
    HUY_DANG_DON_WITHOUT_API: [],
    DOI_MA_DON_PUSHSALE: [],
    CAP_NHAT_TT_CARE_DON: ['care'],
};

function optionList(items = []) {
    return (items ?? []).map((item) => ({
        value: String(item.value ?? item.id ?? ''),
        label: item.label ?? item.name ?? String(item.value ?? item.id ?? ''),
    })).filter((item) => item.value !== '');
}

export default function BulkUpdateByCode({
    pageTitle = 'Cập nhật contact theo mã pushsale',
    activeMenuCode = '5.1',
    backUrl = '/admin/warehouse/operations',
    executeUrl = '/admin/warehouse/orders/update-by-code',
    initialCodes = '',
    actions = [],
    filterOptions = {},
}) {
    const { ask } = useConfirm();
    const [codeType, setCodeType] = useState('MHT');
    const [isGhtk, setIsGhtk] = useState(false);
    const [codes, setCodes] = useState(initialCodes || '');
    const [action, setAction] = useState(actions[0]?.value || 'CAP_NHAT_DON');
    const [form, setForm] = useState({
        warehouse_id: '',
        shipping_provider: '',
        shipping_method: '',
        shipping_notes: '',
        length_cm: '',
        width_cm: '',
        height_cm: '',
        weight_grams: '',
        delivery_status: '',
        reconciliation_status: '',
        warehouse_care_status: '',
        warehouse_care_note: '',
        accounting_note: '',
        note: '',
    });
    const [busy, setBusy] = useState(false);
    const [results, setResults] = useState([]);

    const visible = ACTION_FIELDS[action] || [];
    const warehouses = useMemo(() => optionList(filterOptions.warehouses), [filterOptions.warehouses]);
    const providers = useMemo(() => optionList(filterOptions.shippingProviders), [filterOptions.shippingProviders]);
    const deliveryStatuses = useMemo(() => optionList(filterOptions.deliveryStatuses), [filterOptions.deliveryStatuses]);
    const reconStatuses = useMemo(() => optionList(filterOptions.reconciliationStatuses), [filterOptions.reconciliationStatuses]);
    const careStatuses = useMemo(() => optionList(filterOptions.warehouseCareStatuses), [filterOptions.warehouseCareStatuses]);

    const setField = (key, value) => setForm((old) => ({ ...old, [key]: value }));

    const submit = async () => {
        if (!String(codes).trim()) {
            toast.error('Nhập danh sách mã đơn.');
            return;
        }

        const ok = await ask({
            title: 'Xác nhận thực hiện',
            description: 'PUSHSALE KHÔNG THỂ HỖ TRỢ KHÔI PHỤC LẠI NẾU BẠN CHỌN SAI VỚI MONG MUỐN. Vui lòng kiểm tra lại để đảm bảo rằng bạn đã lựa chọn đúng. Bạn chắc chắn muốn thực hiện?',
            confirmLabel: 'Thực hiện',
        });
        if (!ok) return;

        setBusy(true);
        try {
            const payload = {
                code_type: codeType,
                is_ghtk: isGhtk,
                codes,
                action,
                warehouse_id: form.warehouse_id || null,
                shipping_provider: form.shipping_provider || null,
                shipping_method: form.shipping_method || null,
                shipping_notes: form.shipping_notes || null,
                length_cm: form.length_cm !== '' ? Number(form.length_cm) : null,
                width_cm: form.width_cm !== '' ? Number(form.width_cm) : null,
                height_cm: form.height_cm !== '' ? Number(form.height_cm) : null,
                weight_grams: form.weight_grams !== '' ? Number(form.weight_grams) : null,
                delivery_status: form.delivery_status || null,
                reconciliation_status: form.reconciliation_status || null,
                warehouse_care_status: form.warehouse_care_status || null,
                warehouse_care_note: form.warehouse_care_note || null,
                accounting_note: form.accounting_note || null,
                note: form.note || null,
            };
            const data = await apiRequest(executeUrl, { method: 'POST', body: payload });
            setResults(data.results || []);
            if (data.failed_count > 0) {
                toast.warning(data.message || 'Hoàn tất với một số lỗi.');
            } else {
                toast.success(data.message || 'Đã thực hiện.');
            }
        } catch (error) {
            toast.error(error.message || 'Không thực hiện được.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={pageTitle} />
            <section className="ps-wh-bulk-page" data-page-code={activeMenuCode}>
                <PageHeader
                    title={pageTitle}
                    pageCode={activeMenuCode}
                    actions={(
                        <Link href={backUrl} className="btn btn-default btn-sm" title="Đóng">
                            <i className="fa fa-close" />
                        </Link>
                    )}
                />

                <div className="ps-wh-bulk-body">
                    <div className="row">
                        <div className="col-sm-6">
                            <div className="form-group">
                                <span className="h-label">Loại mã đơn</span>
                                <select className="form-control" value={codeType} onChange={(e) => setCodeType(e.target.value)}>
                                    <option value="MHT">Mã đơn PUSHSALE</option>
                                    <option value="MGV">Mã vận đơn</option>
                                </select>
                            </div>
                            {codeType === 'MGV' ? (
                                <label className="ps-wh-bulk-check">
                                    <input type="checkbox" checked={isGhtk} onChange={(e) => setIsGhtk(e.target.checked)} />
                                    {' '}
                                    Đơn vị GH là: Giao hàng tiết kiệm
                                </label>
                            ) : null}
                            <div className="form-group">
                                <span className="h-label">Danh sách mã đơn</span>
                                <textarea
                                    className="form-control"
                                    rows={12}
                                    value={codes}
                                    onChange={(e) => setCodes(e.target.value)}
                                    placeholder="PS001... hoặc mã vận đơn — cách nhau bằng ; hoặc xuống dòng"
                                />
                            </div>
                        </div>

                        <div className="col-sm-6">
                            <div className="row">
                                <div className="col-xs-8 form-group">
                                    <select className="form-control" value={action} onChange={(e) => setAction(e.target.value)}>
                                        {actions.map((item) => (
                                            <option key={item.value} value={item.value}>{item.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-xs-4 form-group">
                                    <button type="button" className="btn btn-sm btn-primary" disabled={busy} onClick={submit}>
                                        <i className="fa fa-gears" />
                                        {' '}
                                        Thực hiện
                                    </button>
                                </div>
                            </div>

                            <div className="notice ps-wh-bulk-notice">
                                <b>Chỉ dẫn:</b>
                                <br />
                                -
                                {' '}
                                <span className="text-danger">Mỗi đơn vị chỉ có thể chạy một tiến trình cập nhật tại một thời điểm. Không thể hỗ trợ khôi phục nếu bạn lựa chọn sai.</span>
                                <br />
                                - Nhập mã đơn cách nhau bằng dấu &quot;;&quot; hoặc xuống dòng
                                <br />
                                - Cập nhật đơn: cập nhật kích thước / PTGH / cân nặng giống nhau. Đơn đã đăng hoặc đối soát sẽ không được cập nhật.
                                <br />
                                - Cập nhật TTGH: không cập nhật đơn đã đối soát; không nhảy trạng thái trước/sau đăng đơn sai luồng.
                                <br />
                                - Hủy đăng đơn: gọi API hủy đối tác nếu thành công thì hủy trên hệ thống.
                                <br />
                                - Hủy đăng đơn (without API): chỉ hủy trên hệ thống — cần chắc chắn đã hủy bên đối tác.
                            </div>

                            {visible.includes('warehouse') ? (
                                <div className="form-group">
                                    <span className="h-label">Kho</span>
                                    <select className="form-control" value={form.warehouse_id} onChange={(e) => setField('warehouse_id', e.target.value)}>
                                        <option value="">--Chọn kho--</option>
                                        {warehouses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                    </select>
                                </div>
                            ) : null}

                            {visible.includes('notes') ? (
                                <div className="form-group">
                                    <span className="h-label">Ghi chú giao hàng</span>
                                    <input className="form-control" value={form.shipping_notes} onChange={(e) => setField('shipping_notes', e.target.value)} />
                                </div>
                            ) : null}

                            {visible.includes('dims') ? (
                                <div className="row">
                                    <div className="col-xs-4 form-group">
                                        <span className="h-label">Chiều dài(cm)</span>
                                        <input className="form-control" type="number" min="0" value={form.length_cm} onChange={(e) => setField('length_cm', e.target.value)} />
                                    </div>
                                    <div className="col-xs-4 form-group">
                                        <span className="h-label">Chiều rộng(cm)</span>
                                        <input className="form-control" type="number" min="0" value={form.width_cm} onChange={(e) => setField('width_cm', e.target.value)} />
                                    </div>
                                    <div className="col-xs-4 form-group">
                                        <span className="h-label">Chiều cao(cm)</span>
                                        <input className="form-control" type="number" min="0" value={form.height_cm} onChange={(e) => setField('height_cm', e.target.value)} />
                                    </div>
                                </div>
                            ) : null}

                            {visible.includes('shipping') ? (
                                <div className="row">
                                    <div className="col-xs-6 form-group">
                                        <span className="h-label">Phương thức giao hàng</span>
                                        <select className="form-control" value={form.shipping_provider} onChange={(e) => setField('shipping_provider', e.target.value)}>
                                            <option value="">--Chọn PTGH--</option>
                                            {providers.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                        </select>
                                    </div>
                                    <div className="col-xs-6 form-group">
                                        <span className="h-label">Giao hàng bằng</span>
                                        <input className="form-control" value={form.shipping_method} onChange={(e) => setField('shipping_method', e.target.value)} placeholder="VD: VTK, standard..." />
                                    </div>
                                    <div className="col-xs-6 form-group">
                                        <span className="h-label">Cân nặng(gram)</span>
                                        <input className="form-control" type="number" min="0" value={form.weight_grams} onChange={(e) => setField('weight_grams', e.target.value)} />
                                    </div>
                                </div>
                            ) : null}

                            {visible.includes('delivery_status') ? (
                                <div className="form-group">
                                    <span className="h-label">Trạng thái giao hàng</span>
                                    <select className="form-control" value={form.delivery_status} onChange={(e) => setField('delivery_status', e.target.value)}>
                                        <option value="">--Chọn TTGH--</option>
                                        {deliveryStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                    </select>
                                </div>
                            ) : null}

                            {visible.includes('reconciliation_status') ? (
                                <div className="form-group">
                                    <span className="h-label">Trạng thái đối soát</span>
                                    <select className="form-control" value={form.reconciliation_status} onChange={(e) => setField('reconciliation_status', e.target.value)}>
                                        <option value="">--Chọn--</option>
                                        {reconStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                    </select>
                                </div>
                            ) : null}

                            {visible.includes('accounting_note') ? (
                                <div className="form-group">
                                    <span className="h-label">Ghi chú kho vận (kế toán)</span>
                                    <textarea className="form-control" rows={4} value={form.accounting_note} onChange={(e) => setField('accounting_note', e.target.value)} />
                                </div>
                            ) : null}

                            {visible.includes('care') ? (
                                <div className="row">
                                    <div className="col-xs-6 form-group">
                                        <span className="h-label">Trạng thái care đơn</span>
                                        <select className="form-control" value={form.warehouse_care_status} onChange={(e) => setField('warehouse_care_status', e.target.value)}>
                                            <option value="">--Chọn--</option>
                                            {careStatuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                        </select>
                                    </div>
                                    <div className="col-xs-6 form-group">
                                        <span className="h-label">Ghi chú care</span>
                                        <input className="form-control" value={form.warehouse_care_note} onChange={(e) => setField('warehouse_care_note', e.target.value)} />
                                    </div>
                                </div>
                            ) : null}

                            {visible.includes('note') ? (
                                <div className="form-group">
                                    <span className="h-label">Ghi chú</span>
                                    <input className="form-control" value={form.note} onChange={(e) => setField('note', e.target.value)} />
                                </div>
                            ) : null}
                        </div>
                    </div>

                    {results.length > 0 ? (
                        <div className="ps-wh-bulk-results">
                            <h4>Kết quả</h4>
                            <table className="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã nhập</th>
                                        <th>Mã đơn</th>
                                        <th>KQ</th>
                                        <th>Chi tiết</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {results.map((row, index) => (
                                        <tr key={`${row.code}-${index}`} className={row.ok ? '' : 'danger'}>
                                            <td>{row.code}</td>
                                            <td>{row.order_code || '—'}</td>
                                            <td>{row.ok ? 'OK' : 'Lỗi'}</td>
                                            <td>{row.message}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <button type="button" className="btn btn-default btn-sm" onClick={() => router.visit(backUrl)}>
                                Quay lại tác nghiệp
                            </button>
                        </div>
                    ) : null}
                </div>
            </section>
        </AppLayout>
    );
}
