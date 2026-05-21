import { Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useReportSearch } from '@/hooks/useReportSearch';

function SelectFilter({ label, name, value, options, onChange }) {
    return (
        <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">{label}</Label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value || null)}
                className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
            >
                <option value="">— Tất cả —</option>
                {(options ?? []).map((opt) => (
                    <option key={opt.value ?? opt.id} value={opt.value ?? opt.id}>
                        {opt.label ?? opt.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

export function ReportFilterBar({ routeUrl, filters, filterOptions, extra = null }) {
    const { search } = useReportSearch(routeUrl, filters);

    const set = (key, val) => search({ [key]: val, page: 1 });

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <div className="space-y-1">
                    <Label className="text-xs">Từ ngày</Label>
                    <Input
                        type="date"
                        value={filters.date_from ?? ''}
                        onChange={(e) => set('date_from', e.target.value)}
                    />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">Đến ngày</Label>
                    <Input
                        type="date"
                        value={filters.date_to ?? ''}
                        onChange={(e) => set('date_to', e.target.value)}
                    />
                </div>
                <SelectFilter
                    label="Kiểu ngày"
                    name="date_type"
                    value={filters.date_type}
                    options={filterOptions?.dateTypes}
                    onChange={set}
                />
                <SelectFilter
                    label="Trạng thái giao"
                    name="delivery_status"
                    value={filters.delivery_status}
                    options={filterOptions?.deliveryStatuses}
                    onChange={set}
                />
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
                <div className="space-y-1 sm:col-span-2">
                    <Label className="text-xs">Tìm tên / SĐT / mã đơn</Label>
                    <Input
                        value={filters.search ?? ''}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Họ tên, số điện thoại…"
                    />
                </div>
            </div>
            {extra}
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    Tìm kiếm
                </Button>
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={!!filters.no_closing_date_limit}
                        onChange={(e) => set('no_closing_date_limit', e.target.checked ? 1 : 0)}
                    />
                    Không giới hạn ngày chốt
                </label>
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={!!filters.hide_zero_status}
                        onChange={(e) => set('hide_zero_status', e.target.checked ? 1 : 0)}
                    />
                    Ẩn trạng thái không số
                </label>
            </div>
        </div>
    );
}
