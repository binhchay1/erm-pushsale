import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function options(list = []) {
    return list.map((item) => {
        const value = item.value ?? item.id;
        const label = item.label ?? item.name;
        return <option key={value} value={value}>{label}</option>;
    });
}

function toDisplayDate(value) {
    if (!value) return '';
    const [year, month, day] = String(value).slice(0, 10).split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year}`;
}

function toInputDate(value) {
    if (!value) return '';
    const trimmed = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return trimmed;
    const match = trimmed.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (!match) return '';
    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

function DateRangeInput({ filters, onChange }) {
    const initial = useMemo(() => {
        const from = toDisplayDate(filters.date_from);
        const to = toDisplayDate(filters.date_to);
        return from && to ? `${from} 00:00 - ${to} 23:59` : '';
    }, [filters.date_from, filters.date_to]);
    const [value, setValue] = useState(initial);

    const sync = (next) => {
        setValue(next);
        const [fromRaw = '', toRaw = ''] = next.split('-').map((part) => part.trim());
        const from = toInputDate(fromRaw);
        const to = toInputDate(toRaw);
        onChange(from, to);
    };

    return (
        <input
            className="date-range"
            value={value}
            onChange={(event) => sync(event.target.value)}
            placeholder="dd/mm/yyyy 00:00 - dd/mm/yyyy 23:59"
        />
    );
}

export function WarehouseFilterPanel({ routeUrl, filters = {}, filterOptions = {}, title = 'Thủ kho tác nghiệp' }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [summaryOpen, setSummaryOpen] = useState(true);

    const submit = (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.date_from = dateFrom || undefined;
        data.date_to = dateTo || undefined;
        data.hide_zero_status = event.currentTarget.elements.hide_zero_status?.checked ? '1' : '0';
        router.get(routeUrl, { ...filters, ...data, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => router.get(routeUrl, {}, { preserveState: true, preserveScroll: true, replace: true });

    const setDateRange = (from, to) => {
        setDateFrom(from);
        setDateTo(to);
    };

    return (
        <form className="ps-wh-filter ps-wh-legacy-filter" onSubmit={submit}>
            <input type="hidden" name="date_from" value={dateFrom} readOnly />
            <input type="hidden" name="date_to" value={dateTo} readOnly />

            <div className="m-header-wrap ps-wh-search-header">
                <div className="m-header">
                    <div className="ps-wh-header-grid">
                        <div className="ps-wh-header-title"><span className="text">{title}</span></div>
                        <label className="ps-wh-header-check"><input type="checkbox" name="hide_zero_status" value="1" defaultChecked={Boolean(filters.hide_zero_status)} /> <span>Ẩn trạng thái không số</span></label>
                        <div className="ps-wh-header-search">
                            <input name="search" defaultValue={filters.search ?? ''} placeholder="Họ tên, số điện thoại" />
                            <button type="submit" className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                            <button type="button" className="btn-icon ps-wh-summary-toggle" onClick={() => setSummaryOpen((open) => !open)} title={summaryOpen ? 'Ẩn bộ lọc' : 'Hiện bộ lọc'}>
                                <i className={`fa fa-angle-double-${summaryOpen ? 'up' : 'down'}`} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {summaryOpen && (
                <div className="box-body ps-wh-filter-body">
                    <div className="ps-wh-filter-grid legacy-grid">
                        <DateRangeInput filters={filters} onChange={setDateRange} />
                        <select name="date_type" defaultValue={filters.date_type ?? 'data_arrival'}>
                            <option value="">--Kiểu ngày--</option>{options(filterOptions.dateTypes)}
                        </select>
                        <select name="printed_status" defaultValue={filters.printed_status ?? ''}>
                            <option value="">--In đơn--</option>{options(filterOptions.printedStatuses)}
                        </select>
                        <select name="warehouse_care_status" defaultValue={filters.warehouse_care_status ?? ''}>
                            <option value="">--Trạng thái care--</option>{options(filterOptions.warehouseCareStatuses)}
                        </select>
                        <select name="care_status" defaultValue={filters.care_status ?? ''}>
                            <option value="">--Chọn care đơn--</option>{options(filterOptions.careUsers)}
                        </select>

                        <select name="product_id" defaultValue={filters.product_id ?? ''}>
                            <option value="">--Chọn sản phẩm--</option>{options(filterOptions.products)}
                        </select>
                        <select name="reconciliation_status" defaultValue={filters.reconciliation_status ?? ''}>
                            <option value="">--Chọn đối soát nội bộ--</option>{options(filterOptions.reconciliationStatuses)}
                        </select>
                        <select name="shipping_provider" defaultValue={filters.shipping_provider ?? ''}>
                            <option value="">--Chọn PTGH--</option>{options(filterOptions.shippingProviders)}
                        </select>
                        <select name="warehouse_id" defaultValue={filters.warehouse_id ?? ''}>
                            <option value="">--Chọn kho--</option>{options(filterOptions.warehouses)}
                        </select>
                        <select name="deposit_status" defaultValue={filters.deposit_status ?? ''}>
                            <option value="">--Đặt cọc--</option>{options(filterOptions.depositStatuses)}
                        </select>

                        <select name="team_leader_id" defaultValue={filters.team_leader_id ?? ''}>
                            <option value="">--Chọn trưởng nhóm sale--</option>{options(filterOptions.teamLeaders)}
                        </select>
                        <select name="team_id" defaultValue={filters.team_id ?? ''}>
                            <option value="">--Chọn nhóm sale--</option>{options(filterOptions.salesTeams)}
                        </select>
                        <select name="sale_id" defaultValue={filters.sale_id ?? ''}>
                            <option value="">--Chọn sale--</option>{options(filterOptions.salesUsers)}
                        </select>
                        <select name="marketing_team_leader_id" defaultValue={filters.marketing_team_leader_id ?? ''}>
                            <option value="">--Chọn trưởng nhóm marketing--</option>{options(filterOptions.marketingTeamLeaders)}
                        </select>
                        <select name="marketing_team_id" defaultValue={filters.marketing_team_id ?? ''}>
                            <option value="">--Chọn nhóm marketing--</option>{options(filterOptions.marketingTeams)}
                        </select>

                        <select name="tracking_alert" defaultValue={filters.tracking_alert ?? ''}>
                            <option value="">--Theo dõi đơn--</option>{options(filterOptions.trackingAlerts)}
                        </select>
                        <select name="min_product_quantity" defaultValue={filters.min_product_quantity ?? ''}>
                            <option value="">--Toàn bộ số lượng--</option>
                            {[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm từ {value}</option>)}
                        </select>
                        <select name="max_product_quantity" defaultValue={filters.max_product_quantity ?? ''}>
                            <option value="">--Toàn bộ số lượng--</option>
                            {[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm đến {value}</option>)}
                            <option value="999999">Tổng số lượng sản phẩm không giới hạn</option>
                        </select>
                        <div className="ps-wh-filter-spacer" />
                        <select name="invoice_status" defaultValue={filters.invoice_status ?? ''}>
                            <option value="">--Xuất HĐĐT--</option>
                            <option value="issued">Đã xuất HĐĐT</option>
                            <option value="not_issued">Chưa xuất HĐĐT</option>
                        </select>
                        <div className="ps-wh-filter-actions legacy-actions">
                            <button type="button" className="ps-wh-btn" onClick={reset}><i className="fa fa-refresh" /> Đặt lại</button>
                        </div>
                    </div>
                </div>
            )}
        </form>
    );
}
