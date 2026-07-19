import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { ReportPagination } from '@/components/reports/ReportPagination';
import { apiRequest } from '@/lib/api';

function optionValue(option) {
    return String(option?.value ?? option?.id ?? '');
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? '—';
}

function SelectBox({ value, onChange, options = [], placeholder }) {
    return (
        <select className="form-control ps-filter-control" value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => <option key={optionValue(option)} value={optionValue(option)}>{optionLabel(option)}</option>)}
        </select>
    );
}

function toDateRangeLabel(filters) {
    const from = filters?.date_from ? String(filters.date_from).split('-').reverse().join('/') : '';
    const to = filters?.date_to ? String(filters.date_to).split('-').reverse().join('/') : '';
    return `${from} 00:00 - ${to} 23:59`.trim();
}

function parseDateRange(value) {
    const matches = String(value ?? '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4}).*?(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (!matches) return null;
    const [, fd, fm, fy, td, tm, ty] = matches;
    return {
        date_from: `${fy}-${String(fm).padStart(2, '0')}-${String(fd).padStart(2, '0')}`,
        date_to: `${ty}-${String(tm).padStart(2, '0')}-${String(td).padStart(2, '0')}`,
    };
}

function DialogShell({ title, open, onClose, children, footer }) {
    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width="980px"
            bodyClassName="ps-dialog-body"
            footer={footer}
        >
            {children}
        </PushsaleDialog>
    );
}

function Customer360Table({ rows, pagination, selected, setSelected, onAdd }) {
    const allSelected = rows.length > 0 && rows.every((row) => selected.has(String(row.id)));

    const toggleAll = (checked) => {
        setSelected((current) => {
            const next = new Set(current);
            rows.forEach((row) => checked ? next.add(String(row.id)) : next.delete(String(row.id)));
            return next;
        });
    };

    const toggleOne = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            checked ? next.add(String(id)) : next.delete(String(id));
            return next;
        });
    };

    return (
        <div id="customer360-table" className="ps-customer360-table-wrap">
            <table className="table table-bordered table-multi-select table-sale ps-customer360-table">
                <thead>
                    <tr>
                        <th className="text-center ps-col-check">
                            <span className="chk-all">
                                <input id="customer360-check-all" type="checkbox" checked={allSelected} onChange={(event) => toggleAll(event.target.checked)} />
                                <label htmlFor="customer360-check-all">&nbsp;</label>
                            </span>
                        </th>
                        <th className="text-center">Sale</th>
                        <th className="text-center">Marketing</th>
                        <th className="text-center">Mã KH</th>
                        <th className="text-center">Tên KH</th>
                        <th className="text-center">Tuổi</th>
                        <th className="text-center">Số điện thoại</th>
                        <th className="text-center">Giới tính</th>
                        <th className="text-center">Lời nhắn</th>
                        <th className="text-center">Ngày tạo</th>
                        <th className="text-center">Cập nhật</th>
                        <th className="text-center no-wrap">
                            <button type="button" className="btn-icon ps-customer360-add" title="Thêm khách hàng" onClick={onAdd}>
                                <i className="fa fa-plus" /> <span className="text">Thêm</span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row) => {
                        const checked = selected.has(String(row.id));
                        return (
                            <tr key={row.id} className={checked ? 'ps-row-selected' : ''}>
                                <td className="text-center">
                                    <span className="chk-item">
                                        <input id={`customer360-row-${row.id}`} type="checkbox" checked={checked} onChange={(event) => toggleOne(row.id, event.target.checked)} />
                                        <label htmlFor={`customer360-row-${row.id}`}>&nbsp;</label>
                                    </span>
                                </td>
                                <td>{row.saleName ?? '—'}{row.saleEmail ? <><br /><span className="small-tip">({row.saleEmail})</span></> : null}</td>
                                <td>{row.marketingName ?? '—'}{row.marketingEmail ? <><br /><span className="small-tip">({row.marketingEmail})</span></> : null}</td>
                                <td className="text-center"><a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? row.customerCode ?? '')}`}>{row.customerCode}</a></td>
                                <td>{row.customerName ?? '—'}</td>
                                <td className="text-center">{row.age ?? ''}</td>
                                <td className="text-center"><a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? '')}`}>{row.customerPhone ?? '—'}</a></td>
                                <td className="text-center">{row.gender ?? ''}</td>
                                <td>{row.message ?? ''}</td>
                                <td className="text-center no-wrap">{row.createdAt ?? ''}</td>
                                <td className="text-center no-wrap">{row.updatedAt ?? ''}</td>
                                <td className="text-center ps-customer360-row-actions">
                                    <a href={`/admin/customers?search=${encodeURIComponent(row.customerPhone ?? '')}`} title="Mở hồ sơ khách hàng"><i className="fa fa-external-link" /></a>
                                    <button type="button" title="Thêm vào chiến dịch" onClick={() => toggleOne(row.id, true)}><i className="fa fa-user-plus" /></button>
                                </td>
                            </tr>
                        );
                    }) : (
                        <tr>
                            <td colSpan={12} className="text-left">
                                <span className="tbl-chk"><input id="customer360-confirm-delete" type="checkbox" defaultChecked /><label htmlFor="customer360-confirm-delete">Hỏi lại khi xóa</label></span>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function Customer360Management({ filters = {}, filterOptions = {}, report, routeUrl = '/admin/customer-management', pageTitle = 'Khách hàng 360' }) {
    const rows = report?.rows?.data ?? [];
    const pagination = report?.rows?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0, from: 0, to: 0 };
    const [form, setForm] = useState({ ...filters, keyword: filters.search ?? '' });
    const [dateRange, setDateRange] = useState(toDateRangeLabel(filters));
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [selected, setSelected] = useState(new Set());
    const [dialog, setDialog] = useState(null);
    const [campaignName, setCampaignName] = useState('');
    const [campaignId, setCampaignId] = useState('');
    const [segments, setSegments] = useState(filterOptions.segments ?? []);

    useEffect(() => setSelected(new Set()), [rows.map((row) => row.id).join(',')]);

    const selectedIds = useMemo(() => [...selected].map((id) => Number(id)).filter(Boolean), [selected]);
    const setField = (name, value) => setForm((current) => ({ ...current, [name]: value, page: 1 }));

    const normalizedForm = () => {
        const parsed = parseDateRange(dateRange);
        return {
            ...form,
            ...(parsed ?? {}),
            search: form.keyword ?? form.search ?? '',
            page: 1,
        };
    };

    const search = () => router.get(routeUrl, normalizedForm(), { preserveState: true, preserveScroll: true, replace: true });
    const exportCsv = () => {
        const query = new URLSearchParams(normalizedForm());
        selectedIds.forEach((id) => query.append('ids[]', String(id)));
        window.location.assign(`${routeUrl}/export?${query.toString()}`);
    };

    const postJson = async (url, body, successMessage) => {
        try {
            const data = await apiRequest(url, { method: url.endsWith('/segments') ? 'PUT' : 'POST', body });
            toast.success(data.message ?? successMessage);
            setDialog(null);
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message ?? 'Không thực hiện được thao tác.');
        }
    };

    const createCampaign = () => {
        const name = campaignName.trim();
        if (!name) {
            toast.warning('Nhập tên chiến dịch trước khi lưu.');
            return;
        }
        postJson(`${routeUrl}/campaigns`, { name, filters: normalizedForm(), customer_ids: selectedIds }, 'Đã tạo chiến dịch.');
    };

    const attachCampaign = () => {
        if (!campaignId) {
            toast.warning('Chọn chiến dịch trước khi thêm khách hàng.');
            return;
        }
        if (!selectedIds.length) {
            toast.warning('Tích chọn ít nhất một khách hàng.');
            return;
        }
        postJson(`${routeUrl}/campaigns/attach`, { campaign_id: Number(campaignId), customer_ids: selectedIds }, 'Đã thêm khách hàng vào chiến dịch.');
    };

    const saveSegments = () => {
        const clean = segments.map((segment) => ({ name: String(segment.name ?? '').trim(), color: segment.color ?? '#337ab7' })).filter((segment) => segment.name);
        if (!clean.length) {
            toast.warning('Cần ít nhất một nhóm phân loại.');
            return;
        }
        postJson(`${routeUrl}/segments`, { segments: clean }, 'Đã lưu phân loại khách hàng.');
    };

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <section className="ps-customer360-page">
                <div className="m-header-wrap ps-customer360-header">
                    <div className="m-header">
                        <div className="ps-customer360-title"><span>{pageTitle}</span></div>
                        <div className="ps-customer360-header-spacer" />
                        <input className="form-control date-range" value={dateRange} onChange={(event) => setDateRange(event.target.value)} onBlur={() => setForm((current) => ({ ...current, ...(parseDateRange(dateRange) ?? {}) }))} />
                        <SelectBox value={form.campaign_id} onChange={(value) => setField('campaign_id', value)} options={filterOptions.campaigns} placeholder="-- Chọn chiến dịch để thiết lập điều kiện tìm kiếm --" />
                        <input className="form-control" value={form.keyword ?? ''} placeholder="Tên, phone, mã kh" onChange={(event) => setField('keyword', event.target.value)} onKeyDown={(event) => event.key === 'Enter' && search()} />
                        <button type="button" className="btn-icon ps-filter-toggle" onClick={() => setFiltersOpen((current) => !current)} title="Bộ lọc nâng cao"><i className={`fa ${filtersOpen ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} /></button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={exportCsv}><i className="fa fa-file-excel-o" /> Xuất excel</button>
                    </div>
                </div>

                {filtersOpen ? (
                    <div className="box-body ps-customer360-advanced">
                        <div className="ps-customer360-filter-grid">
                            <SelectBox value={form.sale_id} onChange={(value) => setField('sale_id', value)} options={filterOptions.sales} placeholder="--Chọn sale--" />
                            <SelectBox value={form.marketer_id} onChange={(value) => setField('marketer_id', value)} options={filterOptions.marketers} placeholder="--Chọn marketing--" />
                            <SelectBox value={form.customer_type} onChange={(value) => setField('customer_type', value)} options={filterOptions.customerTypes} placeholder="-- Số lần mua lại --" />
                            <SelectBox value={form.segment_id} onChange={(value) => setField('segment_id', value)} options={segments.map((segment) => ({ value: segment.id, label: segment.name }))} placeholder="-- Nhóm khách hàng --" />
                            <input className="form-control" placeholder="Tuổi từ" value={form.age_from ?? ''} onChange={(event) => setField('age_from', event.target.value)} />
                            <input className="form-control" placeholder="Tuổi đến" value={form.age_to ?? ''} onChange={(event) => setField('age_to', event.target.value)} />
                            <SelectBox value={form.province} onChange={(value) => setField('province', value)} options={[]} placeholder="--Chọn Tỉnh/TP" />
                            <SelectBox value={form.district} onChange={(value) => setField('district', value)} options={[]} placeholder="--Chọn Quận/Huyện" />
                            <SelectBox value={form.ward} onChange={(value) => setField('ward', value)} options={[]} placeholder="--Chọn Phường/Xã--" />
                            <SelectBox value={form.gender} onChange={(value) => setField('gender', value)} options={[{ value: 'male', label: 'Nam' }, { value: 'female', label: 'Nữ' }]} placeholder="-- Giới tính KHĐ --" />
                            <SelectBox value={form.birth_month} onChange={(value) => setField('birth_month', value)} options={Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: `Tháng ${i + 1}` }))} placeholder="Tháng sinh" />
                            <SelectBox value={form.job} onChange={(value) => setField('job', value)} options={[]} placeholder="-- Nghề nghiệp --" />
                            <SelectBox value={form.religion} onChange={(value) => setField('religion', value)} options={[]} placeholder="-- Tôn giáo --" />
                            <SelectBox value={form.income_from} onChange={(value) => setField('income_from', value)} options={[]} placeholder="-- Thu nhập TB tháng từ --" />
                            <SelectBox value={form.income_to} onChange={(value) => setField('income_to', value)} options={[]} placeholder="-- Thu nhập TB tháng đến --" />
                            <SelectBox value={form.spending_from} onChange={(value) => setField('spending_from', value)} options={[]} placeholder="-- Chi tiêu TB tháng từ --" />
                            <SelectBox value={form.spending_to} onChange={(value) => setField('spending_to', value)} options={[]} placeholder="-- Chi tiêu TB tháng từ --" />
                            <SelectBox value={form.customer_status} onChange={(value) => setField('customer_status', value)} options={[]} placeholder="-- Tình trạng khách hàng --" />
                            <SelectBox value={form.usage_effectiveness} onChange={(value) => setField('usage_effectiveness', value)} options={[]} placeholder="-- Hiệu quả sử dụng--" />
                            <SelectBox value={form.usage_status} onChange={(value) => setField('usage_status', value)} options={[]} placeholder="-- Tình trạng sử dụng--" />
                            <SelectBox value={form.data_quality} onChange={(value) => setField('data_quality', value)} options={[]} placeholder="-- Chất lượng data --" />
                            <SelectBox value={form.reject_reason} onChange={(value) => setField('reject_reason', value)} options={[]} placeholder="-- Lý do từ chối --" />
                            <SelectBox value={form.per_page} onChange={(value) => setField('per_page', value)} options={[20, 50, 100, 200, 500, 1000, 3000].map((value) => ({ value, label: value }))} placeholder="20" />
                        </div>
                    </div>
                ) : null}

                <div className="box-body ps-customer360-actions">
                    <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('createCampaign')}><i className="fa fa-plus" /> Tạo chiến dịch từ bộ lọc tìm kiếm</button>
                    <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('attachCampaign')}><i className="fa fa-user-plus" /> Thêm khách hàng vào chiến dịch</button>
                    <button type="button" className="btn btn-sm btn-primary" onClick={() => setDialog('segments')}><i className="fa fa-diamond" /> Quản lý phân loại khách hàng</button>
                </div>

                <Customer360Table rows={rows} pagination={pagination} selected={selected} setSelected={setSelected} onAdd={() => setDialog('createCustomer')} />
                <ReportPagination routeUrl={routeUrl} filters={normalizedForm()} meta={pagination} scrollTargetId="customer360-table" />
            </section>

            <DialogShell
                title="Tạo chiến dịch theo điều kiện tìm kiếm lọc"
                open={dialog === 'createCampaign'}
                onClose={() => setDialog(null)}
                footer={<><button type="button" className="btn btn-default" onClick={() => setDialog(null)}>Đóng</button><button type="button" className="btn btn-primary" onClick={createCampaign}><i className="fa fa-save" /> Lưu</button></>}
            >
                <label>Tên chiến dịch</label>
                <input className="form-control" value={campaignName} onChange={(event) => setCampaignName(event.target.value)} placeholder="Nhập tên chiến dịch chăm sóc" />
                <p className="small-tip mt-2">Chiến dịch sẽ lưu bộ lọc hiện tại và danh sách khách hàng đã tích chọn nếu có.</p>
            </DialogShell>

            <DialogShell
                title="Thêm khách hàng vào chiến dịch"
                open={dialog === 'attachCampaign'}
                onClose={() => setDialog(null)}
                footer={<><button type="button" className="btn btn-default" onClick={() => setDialog(null)}>Đóng</button><button type="button" className="btn btn-primary" onClick={attachCampaign}><i className="fa fa-save" /> Lưu</button></>}
            >
                <label>Chiến dịch</label>
                <SelectBox value={campaignId} onChange={setCampaignId} options={filterOptions.campaigns} placeholder="-- Chọn chiến dịch --" />
                <p className="small-tip mt-2">Đã chọn {selectedIds.length} khách hàng.</p>
            </DialogShell>

            <DialogShell
                title="Quản lý phân loại khách hàng"
                open={dialog === 'segments'}
                onClose={() => setDialog(null)}
                footer={<><button type="button" className="btn btn-default" onClick={() => setDialog(null)}>Đóng</button><button type="button" className="btn btn-primary" onClick={saveSegments}><i className="fa fa-save" /> Lưu</button></>}
            >
                <div className="ps-segment-editor">
                    {segments.map((segment, index) => (
                        <div className="ps-segment-row" key={segment.id ?? index}>
                            <input className="form-control" value={segment.name ?? ''} onChange={(event) => setSegments((current) => current.map((item, i) => i === index ? { ...item, name: event.target.value } : item))} />
                            <input type="color" value={segment.color ?? '#337ab7'} onChange={(event) => setSegments((current) => current.map((item, i) => i === index ? { ...item, color: event.target.value } : item))} />
                            <button type="button" className="btn-icon" onClick={() => setSegments((current) => current.filter((_, i) => i !== index))}><i className="fa fa-trash" /></button>
                        </div>
                    ))}
                    <button type="button" className="btn btn-default btn-sm" onClick={() => setSegments((current) => [...current, { id: Date.now(), name: 'Nhóm khách hàng mới', color: '#337ab7' }])}><i className="fa fa-plus" /> Thêm nhóm</button>
                </div>
            </DialogShell>

            <DialogShell
                title="Thêm khách hàng"
                open={dialog === 'createCustomer'}
                onClose={() => setDialog(null)}
                footer={<button type="button" className="btn btn-default" onClick={() => setDialog(null)}>Đóng</button>}
            >
                <p>Màn này dùng dữ liệu khách hàng phát sinh từ lead/đơn hàng thật. Để thêm khách mới, dùng luồng nhập data thủ công hoặc import lead để đảm bảo đi đúng phân bổ.</p>
            </DialogShell>
        </AppLayout>
    );
}
