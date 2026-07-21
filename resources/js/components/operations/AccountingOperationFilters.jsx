import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { DateRangeFilter, dateRangeFilterLabel } from '@/components/filters/DateRangeFilter';
import { ProductSearchSelect } from '@/components/filters/ProductSearchSelect';
import { useLocalizedFilterOptions } from '@/hooks/use-localized-filter-options';
import { cn } from '@/lib/utils';

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false));
}


function FilterSelect({ value, onChange, children, className = '' }) {
    return (
        <select className={cn('form-control ps-acc-control', className)} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            {children}
        </select>
    );
}

function OptionList({ items = [], valueKey = 'id', labelKey = 'name', firstLabel }) {
    return (
        <>
            <option value="">{firstLabel}</option>
            {items.map((item) => (
                <option key={item[valueKey]} value={String(item[valueKey])}>{item[labelKey]}</option>
            ))}
        </>
    );
}

export function AccountingOperationFilters({ routeUrl, filters = {}, filterOptions = {}, statusTabs = [] }) {
    const localized = useLocalizedFilterOptions(filterOptions);
    const [draft, setDraft] = useState({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        delivery_status: filters.delivery_status ?? '',
        search: filters.search ?? '',
        date_type: filters.date_type ?? 'data_arrival',
        product_id: filters.product_id ?? '',
        warehouse_id: filters.warehouse_id ?? '',
        sale_id: filters.sale_id ?? '',
        hide_zero_status: filters.hide_zero_status ? 1 : 0,
    });

    useEffect(() => {
        setDraft({
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            delivery_status: filters.delivery_status ?? '',
            search: filters.search ?? '',
            date_type: filters.date_type ?? 'data_arrival',
            product_id: filters.product_id ?? '',
            warehouse_id: filters.warehouse_id ?? '',
            sale_id: filters.sale_id ?? '',
            hide_zero_status: filters.hide_zero_status ? 1 : 0,
        });
    }, [filters]);

    const statusByValue = useMemo(() => Object.fromEntries((statusTabs ?? []).map((tab) => [tab.status, tab])), [statusTabs]);
    const activeStatus = filters.delivery_status ?? 'all';

    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

    const submit = (overrides = {}) => {
        router.get(routeUrl, cleanParams({ ...filters, ...draft, ...overrides, page: 1 }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const selectStatus = (status) => {
        const next = status === 'all' ? '' : status;
        setDraft((current) => ({ ...current, delivery_status: next }));
        submit({ delivery_status: next });
    };

    const reset = () => {
        router.get(routeUrl, {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="ps-acc-filter-shell">
            <div className="ps-acc-title-row">
                <div className="ps-acc-title-block">
                    <i className="fa fa-calculator" aria-hidden="true" />
                    <strong>Kế toán tác nghiệp</strong>
                </div>
                <DateRangeFilter
                    className="ps-acc-date-filter"
                    inputClassName="ps-acc-control"
                    from={draft.date_from}
                    to={draft.date_to}
                    onChange={({ date_from, date_to }) => setDraft((current) => ({ ...current, date_from, date_to }))}
                />
                <FilterSelect value={draft.delivery_status} onChange={(value) => set('delivery_status', value)}>
                    <option value="">— Tất cả —</option>
                    {(localized?.deliveryStatuses ?? []).map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                </FilterSelect>
                <div className="ps-acc-search-wrap">
                    <input
                        type="text"
                        className="form-control ps-acc-control"
                        value={draft.search}
                        onChange={(event) => set('search', event.target.value)}
                        onKeyDown={(event) => event.key === 'Enter' && submit()}
                        placeholder="Họ tên, số điện thoại, mã đơn..."
                    />
                    <button type="button" className="btn btn-primary ps-acc-search-btn" onClick={() => submit()}>
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                    <button type="button" className="btn btn-default ps-acc-reset-btn" title="Đặt lại" onClick={reset}>
                        <i className="fa fa-refresh" />
                    </button>
                </div>
            </div>

            <div className="ps-acc-filter-row">
                <input className="form-control ps-acc-control ps-acc-date-range" value={dateRangeFilterLabel(draft.date_from, draft.date_to)} readOnly placeholder="Khoảng ngày" />
                <FilterSelect value={draft.date_type} onChange={(value) => set('date_type', value)}>
                    {(localized?.dateTypes ?? []).map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                </FilterSelect>
                <ProductSearchSelect products={filterOptions?.products ?? []} value={draft.product_id} placeholder="--Sản phẩm / gói sản phẩm--" showPrice={false} onChange={(value) => set('product_id', value)} />
                <FilterSelect value={draft.warehouse_id} onChange={(value) => set('warehouse_id', value)}>
                    <OptionList items={filterOptions?.warehouses ?? []} firstLabel="--Chọn kho--" />
                </FilterSelect>
                <FilterSelect value={draft.sale_id} onChange={(value) => set('sale_id', value)}>
                    <OptionList items={filterOptions?.salesUsers ?? []} firstLabel="--Chọn sale--" />
                </FilterSelect>
                <label className="ps-acc-check">
                    <input type="checkbox" checked={!!draft.hide_zero_status} onChange={(event) => set('hide_zero_status', event.target.checked ? 1 : 0)} />
                    Ẩn trạng thái 0
                </label>
            </div>

            <div className="row ttgh-acc ps-acc-status-row">
                <div className="col-sm-12">
                    {(statusTabs ?? []).map((tab, index) => (
                        <button
                            key={tab.status}
                            type="button"
                            className={cn('dm-tac-nghiep', activeStatus === tab.status || (activeStatus === 'all' && tab.status === 'all') ? 'selected' : '')}
                            onClick={() => selectStatus(tab.status)}
                            title={`${tab.label} (${tab.count ?? 0})`}
                        >
                            <span className={`flag level-${index % 4 === 0 ? 4 : 1}`} />
                            <span className="text">{tab.label}</span>
                            <span className="count">({Number(tab.count ?? 0).toLocaleString('vi-VN')})</span>
                            <span className="live-stream" />
                            <i className="fa fa-angle-double-right" />
                        </button>
                    ))}
                    {!statusByValue.all && (
                        <button type="button" className="dm-tac-nghiep selected" onClick={() => selectStatus('all')}>
                            <span className="flag level-4" />
                            <span className="text">Tất cả</span>
                            <span className="count">(0)</span>
                            <i className="fa fa-angle-double-right" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
