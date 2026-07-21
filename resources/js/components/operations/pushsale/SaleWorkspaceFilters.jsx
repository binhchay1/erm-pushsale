import { router } from '@inertiajs/react';
import { useState } from 'react';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';

const toArray = (value) => Array.isArray(value) ? value : (value?.data ?? Object.values(value ?? {}));
const optionValue = (item) => item.value ?? item.id;
const optionLabel = (item) => item.label ?? item.name;

function clean(values) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false));
}

function Select({ value, onChange, placeholder, options = [] }) {
    return (
        <select className="form-control ps-sale-select" value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            <option value="">{placeholder}</option>
            {toArray(options).map((item) => (
                <option key={String(optionValue(item))} value={optionValue(item)}>{optionLabel(item)}</option>
            ))}
        </select>
    );
}

export function SaleWorkspaceFilters({ routeUrl, filters, filterOptions = {} }) {
    const [form, setForm] = useState({
        ...filters,
        date_type: filters.date_type || '',
        per_page: filters.per_page || 20,
    });
    const [expanded, setExpanded] = useState(true);

    const update = (key, value) => setForm((current) => ({ ...current, [key]: value, page: 1 }));
    const submit = (event) => {
        event?.preventDefault();
        router.get(routeUrl, clean(form), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <form className="ps-sale-filter-shell" onSubmit={submit}>
            <div className="ps-sale-title-row">
                <div className="ps-sale-page-title">Sale tác nghiệp</div>
                <div className="ps-sale-title-spacer" />
                <Select value={form.team_leader_id} onChange={(value) => update('team_leader_id', value)} placeholder="--Chọn trưởng nhóm--" options={filterOptions.teamLeaders} />
                <Select value={form.team_id} onChange={(value) => update('team_id', value)} placeholder="--Chọn nhóm--" options={filterOptions.teams} />
                <Select value={form.sale_id} onChange={(value) => update('sale_id', value)} placeholder="--Tất cả sale--" options={filterOptions.salesUsers} />
                <div className="ps-sale-search-wrap">
                    <input className="form-control" value={form.search ?? ''} onChange={(event) => update('search', event.target.value)} placeholder="Họ tên, số điện thoại" />
                    <button type="submit" className="btn btn-primary btn-sm"><i className="fa fa-search" /> Tìm kiếm</button>
                    <button type="button" className="btn-icon ps-sale-filter-toggle" onClick={() => setExpanded((value) => !value)} title="Thu gọn bộ lọc">
                        <i className={`fa fa-angle-double-${expanded ? 'up' : 'down'}`} />
                    </button>
                </div>
            </div>

            {expanded && (
                <div className="ps-sale-extra-filters">
                    <DateRangeFilter
                        className="ps-sale-date-range form-control"
                        from={form.date_from}
                        to={form.date_to}
                        displayLabel
                        onChange={({ date_from, date_to }) => setForm((current) => ({ ...current, date_from, date_to, page: 1 }))}
                    />
                    <Select value={form.date_type} onChange={(value) => update('date_type', value)} placeholder="--Kiểu ngày--" options={filterOptions.dateTypes} />
                    <select className="form-control ps-sale-select" value={form.care_status ?? ''} onChange={(event) => update('care_status', event.target.value)}>
                        <option value="">--Care đơn--</option>
                        <option value="waiting">Chờ care đơn</option>
                        <option value="deliver_now">Giao ngay</option>
                        <option value="waiting_delivery">Chờ giao</option>
                        <option value="postponed">Hoãn giao hàng</option>
                        <option value="saved">Sale vừa cứu đơn</option>
                        <option value="complaint">Khách hàng khiếu nại</option>
                        <option value="complaint_done">Hoàn tất xử lý khiếu nại</option>
                    </select>
                    <Select value={form.marketing_source_id} onChange={(value) => update('marketing_source_id', value)} placeholder="--Chọn nguồn dữ liệu--" options={filterOptions.marketingSources} />
                    <label className="ps-sale-check"><input type="checkbox" checked={Boolean(form.hide_zero_status)} onChange={(event) => update('hide_zero_status', event.target.checked ? 1 : '')} /> Ẩn tác nghiệp không số</label>

                    <Select value={form.product_id} onChange={(value) => update('product_id', value)} placeholder="--Chọn sản phẩm--" options={filterOptions.products} />
                    <Select value={form.operation_activity_status} onChange={(value) => update('operation_activity_status', value)} placeholder="--Chọn trạng thái tác nghiệp--" options={[{ value: 'not_operated', label: 'Chưa tác nghiệp' }, { value: 'operated', label: 'Đã tác nghiệp' }]} />
                    <Select value={form.operation_result} onChange={(value) => update('operation_result', value)} placeholder="--Chọn kết quả tác nghiệp--" options={filterOptions.operationResults} />
                    <Select value={form.closing_status} onChange={(value) => update('closing_status', value)} placeholder="--Trạng thái chốt đơn--" options={filterOptions.closingStatuses} />
                    <Select value={form.delivery_status} onChange={(value) => update('delivery_status', value)} placeholder="--Chọn trạng thái giao hàng--" options={filterOptions.deliveryStatuses} />
                </div>
            )}
        </form>
    );
}
