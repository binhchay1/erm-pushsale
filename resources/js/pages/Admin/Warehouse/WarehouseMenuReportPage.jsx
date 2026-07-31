import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN');

const MOVEMENT_OPTIONS = [
    { value: 'changed', label: 'Có biến động' },
    { value: 'unchanged', label: 'Không biến động' },
    { value: 'all', label: 'Tất cả' },
];

const SORT_OPTIONS = [
    { value: 'available_asc', label: 'Sắp xếp tồn chưa lên đơn thấp đến cao' },
    { value: 'available_desc', label: 'Sắp xếp tồn chưa lên đơn cao đến thấp' },
    { value: 'closing_asc', label: 'Sắp xếp tồn cuối kỳ thấp đến cao' },
    { value: 'closing_desc', label: 'Sắp xếp tồn cuối kỳ cao đến thấp' },
];

const DATE_TYPE_OPTIONS = [
    { value: 'sale_received', label: 'Ngày sale nhận data' },
    { value: 'order_received', label: 'Ngày nhận đơn' },
    { value: 'closed', label: 'Ngày chốt' },
    { value: 'shipping', label: 'Ngày giao' },
];

const WAREHOUSE_OP_OPTIONS = [
    { value: 'pending_export', label: 'Chờ xuất' },
    { value: 'export', label: 'Xuất kho' },
    { value: 'intake', label: 'Nhập kho' },
    { value: 'all', label: 'Tất cả nghiệp vụ' },
];

const PER_PAGE_OPTIONS = [
    { value: '20', label: '20' },
    { value: '50', label: '50' },
    { value: '100', label: '100' },
    { value: '200', label: '200' },
];

/** Filter sets for warehouse menu 5.5.x — React only, no template DOM. */
const FILTERS_BY_CODE = {
    '5.5.1': ['date', 'warehouse_id', 'product_id', 'movement', 'sort'],
    '5.5.2': ['date', 'warehouse_id', 'product_id', 'movement'],
    '5.5.4': ['date_type', 'date', 'warehouse_id', 'parent_product_id', 'product_id', 'warehouse_op', 'delivery_status', 'reconciliation', 'per_page', 'no_closing_limit'],
    '5.5.5': ['date_type', 'date', 'shipping_account_id'],
    '5.5.6': ['date', 'sale_leader_id', 'sale_team_id'],
    '5.5.7': ['date_type', 'date', 'shipping_group_id', 'shipping_account_id', 'sort'],
    '5.5.8': ['date', 'shipping_account_id'],
};

const EMPTY_BY_CODE = {
    '5.5.7': 'Cần tạo đội nhóm vận đơn để xem được báo cáo này.',
};

const COLUMN_LABELS = {
    index: 'STT',
    warehouse: 'Kho',
    product: 'Sản phẩm',
    batch_code: 'Mã lô',
    opening: 'Tồn đầu kỳ',
    closing: 'Tồn cuối kỳ',
    available: 'Tồn chưa lên đơn',
    intake: 'Nhập kho',
    internal_intake: 'Nhập NB',
    returns: 'Nhập hoàn',
    export: 'Xuất kho',
    internal_export: 'Xuất NB',
    sold_export: 'Xuất bán',
    destroyed: 'Xuất hủy',
    avg_closed_daily: 'Tỉ lệ chốt đơn/ngày',
    avg_sold_daily: 'Tỉ lệ xuất bán/ngày',
    days_remaining: 'Số ngày dự kiến hết hàng',
    pending_opening: 'Đầu kỳ',
    pending: 'Chờ xuất',
    pending_sold: 'Xuất bán',
    pending_closing: 'Cuối kỳ',
    total_quantity: 'Tổng số lượng',
    total_pending: 'Tổng số lượng chờ xuất',
    quantity: 'Số lượng',
    sale: 'Sales',
    team: 'Tên nhóm',
    old_phone: 'Số cũ',
    new_phone: 'Số mới',
    editor: 'Người sửa',
    updated_at: 'Ngày sửa',
    care_user: 'TK vận đơn',
    received: 'Đã nhận',
    uncared: 'Chưa care',
    caring: 'Đang care',
    success: 'Thành công',
    shipping: 'Đang giao',
    delivered: 'Đã giao',
    returned: 'Hoàn đơn',
    cancelled: 'Hủy',
    total: 'Tổng',
    order_code: 'Mã đơn',
    care_status: 'Trạng thái care',
    note: 'Ghi chú',
    operated_at: 'Thời gian',
    previous_status: 'Trạng thái trước',
};

function daysAgoIso(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return date.toISOString().slice(0, 10);
}

function todayIso() {
    return new Date().toISOString().slice(0, 10);
}

function optionList(raw = []) {
    return (raw ?? []).map((item) => ({
        value: String(item.id ?? item.value ?? ''),
        label: String(item.label ?? item.name ?? item.id ?? ''),
    })).filter((item) => item.value !== '');
}

function formatCell(value, format) {
    if (value === null || value === undefined || value === '') return '—';
    if (format === 'number') return numberFormatter.format(Number(value) || 0);
    if (format === 'datetime' || format === 'date') {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            ...(format === 'datetime' ? { hour: '2-digit', minute: '2-digit' } : {}),
        }).format(date);
    }
    return String(value);
}

function resolveColumns(schema = {}) {
    const labelMap = Object.fromEntries((schema.columns ?? []).map((col) => [col.key, col.label]));
    const source = (schema.display_columns?.length ? schema.display_columns : schema.columns) ?? [];
    return source
        .filter((col) => col.key && col.key !== 'select' && col.key !== 'export')
        .map((col) => ({
            key: col.key,
            label: col.label || labelMap[col.key] || COLUMN_LABELS[col.key] || col.key,
            format: col.format || 'text',
        }));
}

function FilterSelect({ value, onChange, options, placeholder }) {
    return (
        <PushsaleSelect
            searchable={options.length > 12}
            options={[{ value: '', label: placeholder }, ...options]}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
        />
    );
}

export default function WarehouseMenuReportPage({
    schema = {},
    rows = [],
    pagination = {},
    filterOptions = {},
    routeUrl = '',
    activeMenuCode = '',
    pageRuntimeError = null,
}) {
    const pageCode = String(schema.code || activeMenuCode || '');
    const filterKeys = FILTERS_BY_CODE[pageCode] ?? ['date', 'warehouse_id', 'product_id'];
    const columns = useMemo(() => resolveColumns(schema), [schema]);
    const emptyMessage = EMPTY_BY_CODE[pageCode] || 'Không có dữ liệu phù hợp bộ lọc.';

    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [draft, setDraft] = useState({
        date_from: params.get('date_from') || daysAgoIso(13),
        date_to: params.get('date_to') || todayIso(),
        date_type: params.get('date_type') || (pageCode === '5.5.7' ? 'order_received' : 'sale_received'),
        warehouse_id: params.get('warehouse_id') || '',
        product_id: params.get('product_id') || '',
        parent_product_id: params.get('parent_product_id') || '',
        movement: params.get('movement') || 'changed',
        sort: params.get('sort') || 'available_asc',
        warehouse_op: params.get('warehouse_op') || 'pending_export',
        delivery_status: params.get('delivery_status') || '',
        reconciliation: params.get('reconciliation') || '',
        sale_leader_id: params.get('sale_leader_id') || '',
        sale_team_id: params.get('sale_team_id') || '',
        shipping_group_id: params.get('shipping_group_id') || '',
        shipping_account_id: params.get('shipping_account_id') || '',
        per_page: params.get('per_page') || String(pagination.per_page || 50),
        no_closing_date_limit: params.get('no_closing_date_limit') === '1',
    });

    const setField = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

    const warehouseOptions = optionList(filterOptions.warehouses);
    const productOptions = optionList(filterOptions.products);
    const parentProductOptions = optionList(filterOptions.productGroups ?? filterOptions.parentProducts ?? []);
    const deliveryOptions = optionList(filterOptions.deliveryStatuses);
    const reconciliationOptions = optionList(filterOptions.reconciliationStatuses);
    const leaderOptions = optionList(filterOptions.saleLeaders ?? filterOptions.teamLeaders);
    const teamOptions = optionList(filterOptions.saleTeams ?? filterOptions.teams);
    const shippingGroupOptions = optionList(filterOptions.shippingGroups ?? filterOptions.careTeams);
    const shippingAccountOptions = optionList(filterOptions.careUsers ?? filterOptions.warehouseUsers ?? filterOptions.users);

    const apply = (event) => {
        event?.preventDefault?.();
        const payload = {
            date_from: draft.date_from,
            date_to: draft.date_to,
            page: 1,
            per_page: draft.per_page || 50,
        };
        filterKeys.forEach((key) => {
            if (key === 'date' || key === 'no_closing_limit') return;
            const value = draft[key];
            if (value !== '' && value !== null && value !== undefined) payload[key] = value;
        });
        if (filterKeys.includes('no_closing_limit') && draft.no_closing_date_limit) {
            payload.no_closing_date_limit = 1;
        }
        router.get(routeUrl, payload, { preserveState: true, replace: true });
    };

    const exportExcel = () => {
        const payload = {
            date_from: draft.date_from,
            date_to: draft.date_to,
            export: 1,
            per_page: draft.per_page || 50,
        };
        filterKeys.forEach((key) => {
            if (key === 'date' || key === 'no_closing_limit') return;
            const value = draft[key];
            if (value !== '' && value !== null && value !== undefined) payload[key] = value;
        });
        const query = new URLSearchParams(
            Object.entries(payload).map(([key, value]) => [key, String(value)]),
        ).toString();
        window.location.href = `${routeUrl}?${query}`;
    };

    const phoneSummary = useMemo(() => {
        if (pageCode !== '5.5.6') return [];
        const map = new Map();
        rows.forEach((row) => {
            const sale = String(row.sale || '—');
            const team = String(row.team || '');
            const key = `${sale}||${team}`;
            map.set(key, (map.get(key) || 0) + 1);
        });
        return [...map.entries()].map(([key, quantity], index) => {
            const [sale, team] = key.split('||');
            return { index: index + 1, sale, team, quantity };
        });
    }, [pageCode, rows]);

    const title = schema.title || 'Báo cáo kho';

    const filterControls = (
        <form id={`ps-wh-report-filters-${pageCode}`} className="ps-wh-menu-report-filters" onSubmit={apply}>
            {filterKeys.includes('date_type') ? (
                <FilterSelect
                    value={draft.date_type}
                    onChange={(value) => setField('date_type', value)}
                    options={DATE_TYPE_OPTIONS}
                    placeholder="--Kiểu ngày--"
                />
            ) : null}
            {filterKeys.includes('date') ? (
                <DateRangeFilter
                    className="ps-wh-menu-report-daterange"
                    from={draft.date_from}
                    to={draft.date_to}
                    onChange={({ date_from, date_to }) => setDraft((current) => ({
                        ...current,
                        date_from: date_from || current.date_from,
                        date_to: date_to || current.date_to,
                    }))}
                />
            ) : null}
            {filterKeys.includes('warehouse_id') ? (
                <FilterSelect
                    value={draft.warehouse_id}
                    onChange={(value) => setField('warehouse_id', value)}
                    options={warehouseOptions}
                    placeholder="--Chọn kho--"
                />
            ) : null}
            {filterKeys.includes('parent_product_id') ? (
                <FilterSelect
                    value={draft.parent_product_id}
                    onChange={(value) => setField('parent_product_id', value)}
                    options={parentProductOptions}
                    placeholder="--Sản phẩm cha--"
                />
            ) : null}
            {filterKeys.includes('product_id') ? (
                <FilterSelect
                    value={draft.product_id}
                    onChange={(value) => setField('product_id', value)}
                    options={productOptions}
                    placeholder="--Chọn sản phẩm--"
                />
            ) : null}
            {filterKeys.includes('movement') ? (
                <FilterSelect
                    value={draft.movement}
                    onChange={(value) => setField('movement', value)}
                    options={MOVEMENT_OPTIONS}
                    placeholder="--Biến động--"
                />
            ) : null}
            {filterKeys.includes('sort') ? (
                <FilterSelect
                    value={draft.sort}
                    onChange={(value) => setField('sort', value)}
                    options={SORT_OPTIONS}
                    placeholder="--Sắp xếp--"
                />
            ) : null}
            {filterKeys.includes('warehouse_op') ? (
                <FilterSelect
                    value={draft.warehouse_op}
                    onChange={(value) => setField('warehouse_op', value)}
                    options={WAREHOUSE_OP_OPTIONS}
                    placeholder="--Nghiệp vụ kho--"
                />
            ) : null}
            {filterKeys.includes('delivery_status') ? (
                <FilterSelect
                    value={draft.delivery_status}
                    onChange={(value) => setField('delivery_status', value)}
                    options={deliveryOptions}
                    placeholder="--Trạng thái giao hàng--"
                />
            ) : null}
            {filterKeys.includes('reconciliation') ? (
                <FilterSelect
                    value={draft.reconciliation}
                    onChange={(value) => setField('reconciliation', value)}
                    options={reconciliationOptions}
                    placeholder="--Đối soát--"
                />
            ) : null}
            {filterKeys.includes('sale_leader_id') ? (
                <FilterSelect
                    value={draft.sale_leader_id}
                    onChange={(value) => setField('sale_leader_id', value)}
                    options={leaderOptions}
                    placeholder="--Trưởng nhóm--"
                />
            ) : null}
            {filterKeys.includes('sale_team_id') ? (
                <FilterSelect
                    value={draft.sale_team_id}
                    onChange={(value) => setField('sale_team_id', value)}
                    options={teamOptions}
                    placeholder="--Nhóm--"
                />
            ) : null}
            {filterKeys.includes('shipping_group_id') ? (
                <FilterSelect
                    value={draft.shipping_group_id}
                    onChange={(value) => setField('shipping_group_id', value)}
                    options={shippingGroupOptions}
                    placeholder="-- Chọn --"
                />
            ) : null}
            {filterKeys.includes('shipping_account_id') ? (
                <FilterSelect
                    value={draft.shipping_account_id}
                    onChange={(value) => setField('shipping_account_id', value)}
                    options={shippingAccountOptions}
                    placeholder="-- TK vận đơn --"
                />
            ) : null}
            {filterKeys.includes('per_page') ? (
                <FilterSelect
                    value={draft.per_page}
                    onChange={(value) => setField('per_page', value)}
                    options={PER_PAGE_OPTIONS}
                    placeholder="50"
                />
            ) : null}
            {filterKeys.includes('no_closing_limit') ? (
                <label className="ps-wh-menu-report-check">
                    <input
                        type="checkbox"
                        checked={Boolean(draft.no_closing_date_limit)}
                        onChange={(event) => setField('no_closing_date_limit', event.target.checked)}
                    />
                    Không giới hạn ngày chốt
                </label>
            ) : null}
        </form>
    );

    return (
        <AppLayout activeMenuCode={activeMenuCode || pageCode}>
            <Head title={title} />
            <section className="ps-adminlte-page ps-wh-menu-report-page" data-page-code={pageCode}>
                <PageHeader
                    title={title}
                    pageCode={pageCode}
                    className="ps-wh-menu-report-header"
                    filters={filterControls}
                    actions={(
                        <div className="ps-wh-menu-report-actions">
                            <button className="btn btn-sm btn-primary" type="submit" form={`ps-wh-report-filters-${pageCode}`}>
                                <i className="fa fa-search" /> Tìm kiếm
                            </button>
                            <button className="btn btn-sm btn-primary" type="button" onClick={exportExcel}>
                                <i className="fa fa-file-excel-o" /> Xuất Excel
                            </button>
                        </div>
                    )}
                    collapsible={false}
                />

                {pageRuntimeError ? (
                    <div className="pushsale-error-banner ps-wh-menu-report-error">
                        <i className="fa fa-exclamation-triangle" /> {pageRuntimeError}
                    </div>
                ) : null}

                {pageCode === '5.5.6' ? (
                    <div className="ps-wh-menu-report-table-wrap">
                        <table className="table table-bordered ps-source-table ps-wh-menu-report-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Sales</th>
                                    <th>Tên nhóm</th>
                                    <th>Số lượng</th>
                                </tr>
                            </thead>
                            <tbody>
                                {phoneSummary.length ? phoneSummary.map((row) => (
                                    <tr key={`${row.sale}-${row.team}-${row.index}`}>
                                        <td className="text-center">{row.index}</td>
                                        <td>{row.sale}</td>
                                        <td>{row.team || '—'}</td>
                                        <td className="text-center">{numberFormatter.format(row.quantity)}</td>
                                    </tr>
                                )) : (
                                    <TableEmptyRow colSpan={4} message={emptyMessage} className="text-center ps-empty" />
                                )}
                            </tbody>
                        </table>
                        <p className="ps-wh-menu-report-note">Dữ liệu lưu trữ tối đa 60 ngày</p>
                    </div>
                ) : null}

                <div className="ps-wh-menu-report-table-wrap">
                    <table className="table table-bordered ps-source-table ps-wh-menu-report-table">
                        <thead>
                            <tr>
                                {columns.map((col) => (
                                    <th key={col.key}>{col.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row._record_id || `${row.index}-${index}`}>
                                    {columns.map((col) => (
                                        <td key={col.key} className={col.format === 'number' || col.key === 'index' ? 'text-center' : ''}>
                                            {formatCell(row[col.key], col.format)}
                                        </td>
                                    ))}
                                </tr>
                            )) : (
                                <TableEmptyRow colSpan={Math.max(columns.length, 1)} message={emptyMessage} className="text-center ps-empty" />
                            )}
                        </tbody>
                    </table>
                </div>

                {(pagination?.total ?? 0) > 0 ? (
                    <PushsalePagination
                        meta={pagination}
                        routeUrl={routeUrl}
                        filters={{
                            date_from: draft.date_from,
                            date_to: draft.date_to,
                            date_type: draft.date_type,
                            warehouse_id: draft.warehouse_id,
                            product_id: draft.product_id,
                            parent_product_id: draft.parent_product_id,
                            movement: draft.movement,
                            sort: draft.sort,
                            warehouse_op: draft.warehouse_op,
                            delivery_status: draft.delivery_status,
                            reconciliation: draft.reconciliation,
                            sale_leader_id: draft.sale_leader_id,
                            sale_team_id: draft.sale_team_id,
                            shipping_group_id: draft.shipping_group_id,
                            shipping_account_id: draft.shipping_account_id,
                            per_page: draft.per_page,
                            ...(draft.no_closing_date_limit ? { no_closing_date_limit: 1 } : {}),
                        }}
                    />
                ) : null}
            </section>
        </AppLayout>
    );
}
