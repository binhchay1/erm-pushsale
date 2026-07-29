import { useEffect, useState } from 'react';

import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

export function SelectBox({ value, onChange, options = [], placeholder, className = 'form-control ps-filter-control' }) {
    return (
        <select className={className} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => {
                const optionValue = String(option?.value ?? option?.id ?? '');
                const optionLabel = option?.label ?? option?.name ?? '—';
                return <option key={optionValue} value={optionValue}>{optionLabel}</option>;
            })}
        </select>
    );
}

export function CareCampaignConditionFields({ value = {}, onChange, filterOptions = {} }) {
    const set = (key, next) => onChange({ ...value, [key]: next });

    return (
        <div className="ps-care-condition-grid">
            <SelectBox value={value.status} onChange={(v) => set('status', v)} options={[
                { value: 'active', label: 'Đang chạy' },
                { value: 'draft', label: 'Nháp' },
                { value: 'paused', label: 'Tạm dừng' },
                { value: 'completed', label: 'Hoàn thành' },
            ]} placeholder="Trạng thái chiến dịch" />
            <SelectBox value={value.customer_type} onChange={(v) => set('customer_type', v)} options={filterOptions.customerTypes ?? [
                { value: 'new', label: 'Khách mới' },
                { value: 'returning', label: 'Khách mua lại' },
            ]} placeholder="-- Số lần mua lại --" />
            <SelectBox value={value.segment_id} onChange={(v) => set('segment_id', v)} options={(filterOptions.segments ?? []).map((s) => ({ value: s.id, label: s.name }))} placeholder="-- Nhóm khách hàng --" />
            <SelectBox value={value.marital_status} onChange={(v) => set('marital_status', v)} options={[]} placeholder="-- Tình trạng hôn nhân --" />
            <SelectBox value={value.language} onChange={(v) => set('language', v)} options={[]} placeholder="-- Ngôn ngữ --" />

            <SelectBox value={value.province} onChange={(v) => set('province', v)} options={[]} placeholder="-- Chọn Tỉnh/TP --" />
            <SelectBox value={value.district} onChange={(v) => set('district', v)} options={[]} placeholder="-- Chọn Quận/Huyện --" />
            <SelectBox value={value.ward} onChange={(v) => set('ward', v)} options={[]} placeholder="-- Chọn Phường/Xã --" />
            <SelectBox value={value.gender} onChange={(v) => set('gender', v)} options={[
                { value: 'male', label: 'Nam' },
                { value: 'female', label: 'Nữ' },
            ]} placeholder="-- Giới tính --" />
            <SelectBox value={value.birth_month} onChange={(v) => set('birth_month', v)} options={Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: `Tháng ${i + 1}` }))} placeholder="-- Tháng sinh --" />
            <SelectBox value={value.job} onChange={(v) => set('job', v)} options={[]} placeholder="-- Nghề nghiệp --" />

            <input className="form-control" placeholder="Tuổi từ" value={value.age_from ?? ''} onChange={(e) => set('age_from', e.target.value)} />
            <input className="form-control" placeholder="Tuổi đến" value={value.age_to ?? ''} onChange={(e) => set('age_to', e.target.value)} />
            <SelectBox value={value.religion} onChange={(v) => set('religion', v)} options={[]} placeholder="-- Tôn giáo --" />
            <input className="form-control" placeholder="Thu nhập TB tháng từ" value={value.income_from ?? ''} onChange={(e) => set('income_from', e.target.value)} />
            <input className="form-control" placeholder="Thu nhập TB tháng đến" value={value.income_to ?? ''} onChange={(e) => set('income_to', e.target.value)} />
            <input className="form-control" placeholder="Chi tiêu TB tháng từ" value={value.spending_from ?? ''} onChange={(e) => set('spending_from', e.target.value)} />
            <input className="form-control" placeholder="Chi tiêu TB tháng đến" value={value.spending_to ?? ''} onChange={(e) => set('spending_to', e.target.value)} />

            <SelectBox value={value.usage_effectiveness} onChange={(v) => set('usage_effectiveness', v)} options={[]} placeholder="-- Hiệu quả sử dụng --" />
            <SelectBox value={value.customer_status} onChange={(v) => set('customer_status', v)} options={[]} placeholder="-- Tình trạng khách hàng --" />
        </div>
    );
}

const emptyCampaignForm = {
    name: '',
    starts_at: '',
    ends_at: '',
    status: 'active',
    repeat_days: 0,
    filters: {},
};

export function CareCampaignFormDialog({
    open,
    onClose,
    onSave,
    title = 'Thêm mới chiến dịch chăm sóc',
    initial = {},
    filterOptions = {},
    processing = false,
}) {
    const [form, setForm] = useState({ ...emptyCampaignForm, ...initial });

    useEffect(() => {
        if (!open) return;
        setForm({ ...emptyCampaignForm, ...initial, filters: { ...(initial.filters ?? {}) } });
    }, [open, initial?.id]);

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title={title}
            width="980px"
            bodyClassName="ps-dialog-body ps-care-campaign-dialog"
            footer={(
                <>
                    <button type="button" className="btn btn-default" disabled={processing} onClick={onClose}>Đóng</button>
                    <button type="button" className="btn btn-primary" disabled={processing} onClick={() => onSave(form)}>
                        <i className={`fa ${processing ? 'fa-spinner fa-spin' : 'fa-plus'}`} /> {processing ? 'Đang lưu…' : '+ Thêm mới'}
                    </button>
                </>
            )}
        >
            <div className="ps-care-campaign-form">
                <label>Tên chiến dịch (*)</label>
                <input className="form-control" value={form.name ?? ''} onChange={(e) => setForm((c) => ({ ...c, name: e.target.value }))} />
                <div className="ps-care-campaign-dates">
                    <div>
                        <label>Ngày bắt đầu (*)</label>
                        <input type="date" className="form-control" value={form.starts_at ?? ''} onChange={(e) => setForm((c) => ({ ...c, starts_at: e.target.value }))} />
                    </div>
                    <div>
                        <label>Ngày kết thúc (*)</label>
                        <input type="date" className="form-control" value={form.ends_at ?? ''} onChange={(e) => setForm((c) => ({ ...c, ends_at: e.target.value }))} />
                    </div>
                </div>
                <h4 className="ps-care-condition-title">THÔNG TIN ĐIỀU KIỆN KHÁCH HÀNG THUỘC CHIẾN DỊCH</h4>
                <CareCampaignConditionFields
                    value={form.filters ?? {}}
                    onChange={(filters) => setForm((c) => ({ ...c, filters }))}
                    filterOptions={filterOptions}
                />
            </div>
        </PushsaleDialog>
    );
}
