import { Search } from 'lucide-react';

import { SelectFilter } from '@/components/filters/SelectFilter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useReportSearch } from '@/hooks/useReportSearch';

const PRESET_OPTIONS = [
    { value: 'today', label: 'Hôm nay' },
    { value: 'last_7_days', label: '7 ngày' },
    { value: 'last_30_days', label: '30 ngày' },
    { value: 'this_month', label: 'Tháng này' },
];

export function ReportFilterBar({ routeUrl, filters, filterOptions, filterFields, extra = null }) {
    const { search } = useReportSearch(routeUrl, filters);
    const fields = new Set(
        filterFields ?? [
            'date_from',
            'date_to',
            'date_type',
            'delivery_status',
            'product_id',
            'warehouse_id',
            'sale_id',
            'search',
            'no_closing_date_limit',
            'hide_zero_status',
        ]
    );

    const set = (key, val) => search({ [key]: val, page: 1 });
    const setPreset = (preset) => search({ preset, date_from: null, date_to: null, page: 1 });

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap gap-2">
                {PRESET_OPTIONS.map((option) => (
                    <Button
                        key={option.value}
                        type="button"
                        size="sm"
                        variant={filters.preset === option.value ? 'default' : 'outline'}
                        onClick={() => setPreset(option.value)}
                    >
                        {option.label}
                    </Button>
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                {fields.has('date_from') && (
                <div className="space-y-1">
                    <Label className="text-xs">Từ ngày</Label>
                    <Input
                        type="date"
                        value={filters.date_from ?? ''}
                        onChange={(e) => set('date_from', e.target.value)}
                    />
                </div>
                )}
                {fields.has('date_to') && (
                <div className="space-y-1">
                    <Label className="text-xs">Đến ngày</Label>
                    <Input
                        type="date"
                        value={filters.date_to ?? ''}
                        onChange={(e) => set('date_to', e.target.value)}
                    />
                </div>
                )}
                {fields.has('date_type') && (
                <SelectFilter
                    label="Kiểu ngày"
                    name="date_type"
                    value={filters.date_type}
                    options={filterOptions?.dateTypes}
                    onChange={set}
                />
                )}
                {fields.has('delivery_status') && (
                <SelectFilter
                    label="Trạng thái giao"
                    name="delivery_status"
                    value={filters.delivery_status}
                    options={filterOptions?.deliveryStatuses}
                    onChange={set}
                />
                )}
                {fields.has('product_id') && (
                <SelectFilter
                    label="Sản phẩm"
                    name="product_id"
                    value={filters.product_id}
                    options={filterOptions?.products?.map((p) => ({
                        value: p.id,
                        label: p.name,
                    }))}
                    onChange={set}
                />
                )}
                {fields.has('parent_product_id') && (
                <SelectFilter
                    label="Sản phẩm gốc"
                    name="parent_product_id"
                    value={filters.parent_product_id}
                    options={filterOptions?.parentProducts?.map((p) => ({
                        value: p.id,
                        label: p.name,
                    }))}
                    onChange={set}
                />
                )}
                {fields.has('operation_result') && (
                <SelectFilter
                    label="Kết quả tác nghiệp"
                    name="operation_result"
                    value={filters.operation_result}
                    options={filterOptions?.operationResults}
                    onChange={set}
                />
                )}
                {fields.has('closing_status') && (
                <SelectFilter
                    label="Trạng thái chốt"
                    name="closing_status"
                    value={filters.closing_status}
                    options={filterOptions?.closingStatuses}
                    onChange={set}
                />
                )}
                {fields.has('warehouse_id') && (
                <SelectFilter
                    label="Kho"
                    name="warehouse_id"
                    value={filters.warehouse_id}
                    options={filterOptions?.warehouses?.map((w) => ({
                        value: w.id,
                        label: w.name,
                    }))}
                    onChange={set}
                />
                )}
                {fields.has('sale_id') && (
                <SelectFilter
                    label="Sale"
                    name="sale_id"
                    value={filters.sale_id}
                    options={filterOptions?.salesUsers?.map((u) => ({
                        value: u.id,
                        label: u.name,
                    }))}
                    onChange={set}
                />
                )}
                {fields.has('search') && (
                <div className="space-y-1 sm:col-span-2">
                    <Label className="text-xs">Tìm tên / SĐT / mã đơn</Label>
                    <Input
                        value={filters.search ?? ''}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Họ tên, số điện thoại…"
                    />
                </div>
                )}
            </div>
            {extra}
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    Tìm kiếm
                </Button>
                {fields.has('no_closing_date_limit') && (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={!!filters.no_closing_date_limit}
                        onChange={(e) => set('no_closing_date_limit', e.target.checked ? 1 : 0)}
                    />
                    Không giới hạn ngày chốt
                </label>
                )}
                {fields.has('hide_zero_status') && (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={!!filters.hide_zero_status}
                        onChange={(e) => set('hide_zero_status', e.target.checked ? 1 : 0)}
                    />
                    Ẩn tab trạng thái 0 đơn
                </label>
                )}
                {fields.has('hide_no_phone') && (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={!!filters.hide_no_phone}
                        onChange={(e) => set('hide_no_phone', e.target.checked ? 1 : 0)}
                    />
                    Ẩn đơn không có SĐT
                </label>
                )}
            </div>
        </div>
    );
}
