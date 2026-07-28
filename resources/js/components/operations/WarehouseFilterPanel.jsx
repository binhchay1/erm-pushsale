import { router } from '@inertiajs/react';
import { useState } from 'react';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { ProductSearchSelect } from '@/components/filters/ProductSearchSelect';
import { PageHeader } from '@/components/layout/PageHeader';

const FORM_ID = 'ps-wh-filter-form';

function options(list = []) {
    return list.map((item) => {
        const value = item.value ?? item.id;
        const label = item.label ?? item.name;
        return <option key={value} value={value}>{label}</option>;
    });
}

function SelectField({ name, value, placeholder, children, options: optionList, form }) {
    return (
        <select className="form-control chosen chosen-x" name={name} form={form} defaultValue={value ?? ''}>
            <option value="">{placeholder}</option>
            {children ?? options(optionList)}
        </select>
    );
}

export function WarehouseFilterPanel({ routeUrl, filters = {}, filterOptions = {}, title = 'Thủ kho tác nghiệp', pageCode = '5.1' }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [productId, setProductId] = useState(filters.product_id ?? '');

    const submit = (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.date_from = dateFrom || undefined;
        data.date_to = dateTo || undefined;
        data.product_id = productId || undefined;
        data.hide_zero_status = event.currentTarget.elements.hide_zero_status?.checked ? '1' : '0';
        router.get(routeUrl, { ...filters, ...data, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => router.get(routeUrl, {}, { preserveState: true, preserveScroll: true, replace: true });

    const setDateRange = (from, to) => {
        setDateFrom(from);
        setDateTo(to);
    };

    const advancedFilters = (
        <div className="box-body ps-wh-filter-body ps-adv-filter-panel">
            <div className="ps-wh-filter-row ps-wh-filter-row-main ps-adv-filter-row">
                <div className="ps-wh-filter-cell ps-wh-cell-wide"><DateRangeFilter className="ps-wh-date-range" from={dateFrom} to={dateTo} onChange={({ date_from, date_to }) => setDateRange(date_from, date_to)} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="date_type" value={filters.date_type ?? 'data_arrival'} placeholder="--Kiểu ngày--" options={filterOptions.dateTypes} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="printed_status" value={filters.printed_status ?? ''} placeholder="--In đơn--" options={filterOptions.printedStatuses} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="warehouse_care_status" value={filters.warehouse_care_status ?? ''} placeholder="--Trạng thái care--" options={filterOptions.warehouseCareStatuses} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="care_status" value={filters.care_status ?? ''} placeholder="--Chọn care đơn--" options={filterOptions.careUsers} /></div>
            </div>

            <div className="ps-wh-filter-row ps-wh-filter-row-six ps-adv-filter-row hidden-xs">
                <div className="ps-wh-filter-cell"><ProductSearchSelect form={FORM_ID} name="product_id" products={filterOptions.products ?? []} value={productId} placeholder="--Chọn sản phẩm / gói sản phẩm--" showPrice={false} onChange={setProductId} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="reconciliation_status" value={filters.reconciliation_status ?? ''} placeholder="--Chọn đối soát nội bộ--" options={filterOptions.reconciliationStatuses} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="shipping_provider" value={filters.shipping_provider ?? ''} placeholder="--Chọn PTGH--" options={filterOptions.shippingProviders} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="warehouse_id" value={filters.warehouse_id ?? ''} placeholder="--Chọn kho--" options={filterOptions.warehouses} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="deposit_status" value={filters.deposit_status ?? ''} placeholder="--Đặt cọc--" options={filterOptions.depositStatuses} /></div>
                <div className="ps-wh-filter-cell ps-wh-empty-cell" />
            </div>

            <div className="ps-wh-filter-row ps-wh-filter-row-six ps-adv-filter-row">
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="team_leader_id" value={filters.team_leader_id ?? ''} placeholder="--Chọn trưởng nhóm sale--" options={filterOptions.teamLeaders} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="team_id" value={filters.team_id ?? ''} placeholder="--Chọn nhóm sale--" options={filterOptions.salesTeams} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="sale_id" value={filters.sale_id ?? ''} placeholder="--Chọn sale--" options={filterOptions.salesUsers} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="marketing_team_leader_id" value={filters.marketing_team_leader_id ?? ''} placeholder="--Chọn trưởng nhóm marketing--" options={filterOptions.marketingTeamLeaders} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="marketing_team_id" value={filters.marketing_team_id ?? ''} placeholder="--Chọn nhóm marketing--" options={filterOptions.marketingTeams} /></div>
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="marketer_id" value={filters.marketer_id ?? ''} placeholder="--Chọn marketing--" options={filterOptions.marketingUsers} /></div>
            </div>

            <div className="ps-wh-filter-row ps-wh-filter-row-six ps-adv-filter-row hidden-xs">
                <div className="ps-wh-filter-cell"><SelectField form={FORM_ID} name="tracking_alert" value={filters.tracking_alert ?? ''} placeholder="--Theo dõi đơn--" options={filterOptions.trackingAlerts} /></div>
                <div className="ps-wh-filter-cell"><select form={FORM_ID} className="form-control chosen" name="min_product_quantity" defaultValue={filters.min_product_quantity ?? ''}><option value="">--Toàn bộ số lượng--</option>{[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm từ {value}</option>)}</select></div>
                <div className="ps-wh-filter-cell"><select form={FORM_ID} className="form-control chosen" name="max_product_quantity" defaultValue={filters.max_product_quantity ?? ''}><option value="">--Toàn bộ số lượng--</option>{[1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100].map((value) => <option key={value} value={value}>Tổng số lượng sản phẩm đến {value}</option>)}<option value="999999">Tổng số lượng sản phẩm không giới hạn</option></select></div>
                <div className="ps-wh-filter-cell"><select form={FORM_ID} className="form-control chosen chosen-x" name="invoice_status" defaultValue={filters.invoice_status ?? ''}><option value="">--Xuất HĐĐT--</option><option value="issued">Đã xuất HĐĐT</option><option value="not_issued">Chưa xuất HĐĐT</option></select></div>
                <div className="ps-wh-filter-cell ps-wh-empty-cell" />
                <div className="ps-wh-filter-cell ps-wh-reset-cell"><button type="button" className="btn btn-sm btn-default ps-wh-btn" onClick={reset}><i className="fa fa-refresh" /> Đặt lại</button></div>
            </div>
        </div>
    );

    return (
        <form id={FORM_ID} className="ps-wh-filter ps-wh-legacy-filter" onSubmit={submit}>
            <input type="hidden" name="date_from" value={dateFrom} readOnly />
            <input type="hidden" name="date_to" value={dateTo} readOnly />

            <PageHeader
                title={title}
                pageCode={pageCode}
                className="ps-wh-search-header"
                defaultCollapsed={false}
                filters={(
                    <label className="ps-wh-check-col">
                        <input type="checkbox" form={FORM_ID} name="hide_zero_status" value="1" defaultChecked={Boolean(filters.hide_zero_status)} /> <span>Ẩn trạng thái không số</span>
                    </label>
                )}
                actions={(
                    <>
                        <input className="form-control ps-wh-keyword" form={FORM_ID} name="search" defaultValue={filters.search ?? ''} placeholder="Họ tên, số điện thoại" />
                        <button type="submit" form={FORM_ID} className="btn btn-sm btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                    </>
                )}
                advanced={advancedFilters}
            />
        </form>
    );
}
