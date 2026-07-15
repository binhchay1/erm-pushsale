import { router } from '@inertiajs/react';
import { RotateCcw, Search } from 'lucide-react';

function options(list = []) {
    return list.map((item) => <option key={item.value ?? item.id} value={item.value ?? item.id}>{item.label ?? item.name}</option>);
}

export function WarehouseFilterPanel({ routeUrl, filters = {}, filterOptions = {} }) {
    const update = (key, value) => router.get(routeUrl, { ...filters, [key]: value || undefined, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    const submit = (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.hide_zero_status = event.currentTarget.elements.hide_zero_status?.checked ? '1' : '0';
        router.get(routeUrl, { ...filters, ...data, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const reset = () => router.get(routeUrl, {}, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <form className="ps-wh-filter" onSubmit={submit}>
            <div className="ps-wh-filter-grid">
                <select name="date_type" defaultValue={filters.date_type ?? 'data_arrival'}>
                    <option value="">-- Kiểu ngày --</option>{options(filterOptions.dateTypes)}
                </select>
                <select name="sale_id" defaultValue={filters.sale_id ?? ''}>
                    <option value="">-- Sale --</option>{options(filterOptions.salesUsers)}
                </select>
                <select name="team_id" defaultValue={filters.team_id ?? ''}>
                    <option value="">-- Nhóm sale --</option>{options(filterOptions.salesTeams)}
                </select>
                <select name="marketer_id" defaultValue={filters.marketer_id ?? ''}>
                    <option value="">-- Marketing --</option>{options(filterOptions.marketingUsers)}
                </select>
                <select name="marketing_team_id" defaultValue={filters.marketing_team_id ?? ''}>
                    <option value="">-- Nhóm marketing --</option>{options(filterOptions.marketingTeams)}
                </select>
                <select name="warehouse_id" defaultValue={filters.warehouse_id ?? ''}>
                    <option value="">-- Kho hàng --</option>{options(filterOptions.warehouses)}
                </select>
                <select name="shipping_provider" defaultValue={filters.shipping_provider ?? ''}>
                    <option value="">-- Đơn vị giao hàng --</option>{options(filterOptions.shippingProviders)}
                </select>
                <select name="warehouse_care_status" defaultValue={filters.warehouse_care_status ?? ''}>
                    <option value="">-- Trạng thái care --</option>{options(filterOptions.warehouseCareStatuses)}
                </select>
                <select name="product_id" defaultValue={filters.product_id ?? ''}>
                    <option value="">-- Sản phẩm --</option>{options(filterOptions.products)}
                </select>
                <select name="printed_status" defaultValue={filters.printed_status ?? ''}>
                    <option value="">-- Trạng thái in --</option>{options(filterOptions.printedStatuses)}
                </select>
                <select name="deposit_status" defaultValue={filters.deposit_status ?? ''}>
                    <option value="">-- Trạng thái cọc --</option>{options(filterOptions.depositStatuses)}
                </select>
                <select name="tracking_alert" defaultValue={filters.tracking_alert ?? ''}>
                    <option value="">-- Cảnh báo giao vận --</option>{options(filterOptions.trackingAlerts)}
                </select>
                <select name="reconciliation_status" defaultValue={filters.reconciliation_status ?? ''}>
                    <option value="">-- Đối soát nội bộ --</option>{options(filterOptions.reconciliationStatuses)}
                </select>
                <input name="search" defaultValue={filters.search ?? ''} placeholder="Mã đơn, tên, SĐT, mã vận đơn" />

                <label className="ps-wh-date-field"><span>Từ ngày</span><input type="date" name="date_from" defaultValue={filters.date_from ?? ''} /></label>
                <label className="ps-wh-date-field"><span>Đến ngày</span><input type="date" name="date_to" defaultValue={filters.date_to ?? ''} /></label>
                <input type="number" min="1" name="min_product_quantity" defaultValue={filters.min_product_quantity ?? ''} placeholder="SL sản phẩm từ" />
                <input type="number" min="1" name="max_product_quantity" defaultValue={filters.max_product_quantity ?? ''} placeholder="SL sản phẩm đến" />
                <label className="ps-wh-check"><input type="checkbox" name="hide_zero_status" value="1" defaultChecked={Boolean(filters.hide_zero_status)} /> Ẩn trạng thái không có đơn</label>
                <div className="ps-wh-filter-actions">
                    <button type="submit" className="ps-wh-btn primary"><Search size={14} /> Tìm kiếm</button>
                    <button type="button" className="ps-wh-btn" onClick={reset}><RotateCcw size={14} /> Làm mới</button>
                </div>
            </div>
        </form>
    );
}
