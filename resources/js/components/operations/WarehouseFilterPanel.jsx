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
            className="form-control date-range"
            value={value}
            onChange={(event) => sync(event.target.value)}
            placeholder="dd/mm/yyyy 00:00 - dd/mm/yyyy 23:59"
        />
    );
}

function SelectField({ name, value, placeholder, children, options: optionList }) {
    return (
        <select className="form-control chosen chosen-x" name={name} defaultValue={value ?? ''}>
            <option value="">{placeholder}</option>
            {children ?? options(optionList)}
        </select>
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
                    <div className="row ps-wh-header-row">
                        <div className="col-xs-12 col-sm-6 form-group ps-wh-title-col">
                            <span className="text">{title}</span>
                        </div>
                        <div className="col-xs-12 col-sm-2 form-group text-right ps-wh-check-col">
                            <label><input type="checkbox" name="hide_zero_status" value="1" defaultChecked={Boolean(filters.hide_zero_status)} /> <span>Ẩn trạng thái không số</span></label>
                        </div>
                        <div className="col-xs-12 col-sm-4 form-group ps-wh-search-col">
                            <div className="ps-wh-keyword"><input className="form-control" name="search" defaultValue={filters.search ?? ''} placeholder="Họ tên, số điện thoại" /></div>
                            <div className="ps-wh-search-actions">
                                <button type="submit" className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                                <button type="button" className="btn-icon ps-wh-summary-toggle" onClick={() => setSummaryOpen((open) => !open)} title={summaryOpen ? 'Ẩn bộ lọc' : 'Hiện bộ lọc'}>
                                    <i className={`fa fa-angle-double-${summaryOpen ? 'up' : 'down'}`} />
                                </button>
                            </div>
                            <div className="clearfix" />
                        </div>
                    </div>
                </div>
            </div>

            {summaryOpen && (
                <div className="box-body ps-wh-filter-body">
                    <div className="ps-wh-filter-row ps-wh-filter-row-main">
                        <div className="ps-wh-filter-cell ps-wh-cell-wide"><DateRangeInput filters={filters} onChange={setDateRange} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="date_type" value={filters.date_type ?? 'data_arrival'} placeholder="--Kiểu ngày--" options={filterOptions.dateTypes} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="printed_status" value={filters.printed_status ?? ''} placeholder="--In đơn--" options={filterOptions.printedStatuses} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="warehouse_care_status" value={filters.warehouse_care_status ?? ''} placeholder="--Trạng thái care--" options={filterOptions.warehouseCareStatuses} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="care_status" value={filters.care_status ?? ''} placeholder="--Chọn care đơn--" options={filterOptions.careUsers} /></div>
                    </div>

                    <div className="ps-wh-filter-row ps-wh-filter-row-six hidden-xs">
                        <div className="ps-wh-filter-cell"><SelectField name="product_id" value={filters.product_id ?? ''} placeholder="--Chọn sản phẩm--" options={filterOptions.products} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="reconciliation_status" value={filters.reconciliation_status ?? ''} placeholder="--Chọn đối soát nội bộ--" options={filterOptions.reconciliationStatuses} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="shipping_provider" value={filters.shipping_provider ?? ''} placeholder="--Chọn PTGH--" options={filterOptions.shippingProviders} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="warehouse_id" value={filters.warehouse_id ?? ''} placeholder="--Chọn kho--" options={filterOptions.warehouses} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="deposit_status" value={filters.deposit_status ?? ''} placeholder="--Đặt cọc--" options={filterOptions.depositStatuses} /></div>
                    </div>

                    <div className="ps-wh-filter-row ps-wh-filter-row-six">
                        <div className="ps-wh-filter-cell"><SelectField name="team_leader_id" value={filters.team_leader_id ?? ''} placeholder="--Chọn trưởng nhóm sale--" options={filterOptions.teamLeaders} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="team_id" value={filters.team_id ?? ''} placeholder="--Chọn nhóm sale--" options={filterOptions.salesTeams} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="sale_id" value={filters.sale_id ?? ''} placeholder="--Chọn sale--" options={filterOptions.salesUsers} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="marketing_team_leader_id" value={filters.marketing_team_leader_id ?? ''} placeholder="--Chọn trưởng nhóm marketing--" options={filterOptions.marketingTeamLeaders} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="marketing_team_id" value={filters.marketing_team_id ?? ''} placeholder="--Chọn nhóm marketing--" options={filterOptions.marketingTeams} /></div>
                        <div className="ps-wh-filter-cell"><SelectField name="marketer_id" value={filters.marketer_id ?? ''} placeholder="--Chọn marketing--" options={filterOptions.marketingUsers} /></div>
                    </div>

                    <div className="ps-wh-filter-row ps-wh-filter-row-six hidden-xs">
                        <div className="ps-wh-filter-cell"><SelectField name="tracking_alert" value={filters.tracking_alert ?? ''} placeholder="--Theo dõi đơn--" options={filterOptions.trackingAlerts} /></div>
                        <div className="ps-wh-filter-cell"><select className="form-control chosen" name="min_product_quantity" defaultValue={filters.min_product_quantity ?? ''}><option value="">--Toàn bộ số lượng--</option>{[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm từ {value}</option>)}</select></div>
                        <div className="ps-wh-filter-cell"><select className="form-control chosen" name="max_product_quantity" defaultValue={filters.max_product_quantity ?? ''}><option value="">--Toàn bộ số lượng--</option>{[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm đến {value}</option>)}<option value="999999">Tổng số lượng sản phẩm không giới hạn</option></select></div>
                        <div className="ps-wh-filter-cell ps-wh-empty-cell" />
                        <div className="ps-wh-filter-cell"><select className="form-control chosen chosen-x" name="invoice_status" defaultValue={filters.invoice_status ?? ''}><option value="">--Xuất HĐĐT--</option><option value="issued">Đã xuất HĐĐT</option><option value="not_issued">Chưa xuất HĐĐT</option></select></div>
                        <div className="ps-wh-filter-cell ps-wh-reset-cell"><button type="button" className="btn btn-sm btn-default ps-wh-btn" onClick={reset}><i className="fa fa-refresh" /> Đặt lại</button></div>
                    </div>
                </div>
            )}
        </form>
    );
}
