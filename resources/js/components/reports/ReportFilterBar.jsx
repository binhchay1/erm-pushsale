import { ChevronDown, Search, SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';

import { SelectFilter } from '@/components/filters/SelectFilter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useReportSearch } from '@/hooks/useReportSearch';

const PRESET_OPTIONS = [
    { value: 'today', label: 'Hôm nay' },
    { value: 'last_7_days', label: '7 ngày' },
    { value: 'last_30_days', label: '30 ngày' },
    { value: 'this_month', label: 'Tháng này' },
];

// Filter cốt lõi luôn hiển thị; phần còn lại gấp vào "Bộ lọc nâng cao"
const PRIMARY_FIELDS = ['date_from', 'date_to', 'product_id', 'search'];

export function ReportFilterBar({ routeUrl, filters, filterOptions, filterFields, extra = null }) {
    const { search } = useReportSearch(routeUrl, filters);
    const fields = useMemo(
        () => new Set(filterFields ?? PRIMARY_FIELDS),
        [filterFields]
    );

    const advancedFieldKeys = useMemo(
        () => [...fields].filter((f) => !PRIMARY_FIELDS.includes(f)),
        [fields]
    );

    const hasActiveAdvanced = advancedFieldKeys.some((key) => {
        const v = filters?.[key];
        return v !== null && v !== undefined && v !== '' && v !== 0 && v !== false;
    });

    const [showAdvanced, setShowAdvanced] = useState(hasActiveAdvanced);

    const set = (key, val) => search({ [key]: val, page: 1 });
    const setPreset = (preset) => search({ preset, date_from: null, date_to: null, page: 1 });

    const renderField = (key) => {
        switch (key) {
            case 'date_from':
                return (
                    <div key={key} className="space-y-1.5">
                        <Label className="text-xs font-medium text-muted-foreground">Từ ngày</Label>
                        <Input
                            type="date"
                            value={filters.date_from ?? ''}
                            onChange={(e) => set('date_from', e.target.value)}
                        />
                    </div>
                );
            case 'date_to':
                return (
                    <div key={key} className="space-y-1.5">
                        <Label className="text-xs font-medium text-muted-foreground">Đến ngày</Label>
                        <Input
                            type="date"
                            value={filters.date_to ?? ''}
                            onChange={(e) => set('date_to', e.target.value)}
                        />
                    </div>
                );
            case 'product_id':
                return (
                    <SelectFilter
                        key={key}
                        label="Sản phẩm"
                        name="product_id"
                        value={filters.product_id}
                        options={filterOptions?.products?.map((p) => ({ value: p.id, label: p.name }))}
                        onChange={set}
                    />
                );
            case 'search':
                return (
                    <div key={key} className="space-y-1.5 sm:col-span-2">
                        <Label className="text-xs font-medium text-muted-foreground">
                            Tìm tên / SĐT / mã đơn
                        </Label>
                        <Input
                            value={filters.search ?? ''}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Họ tên, số điện thoại…"
                        />
                    </div>
                );
            case 'date_type':
                return (
                    <SelectFilter
                        key={key}
                        label="Kiểu ngày"
                        name="date_type"
                        value={filters.date_type}
                        options={filterOptions?.dateTypes}
                        onChange={set}
                    />
                );
            case 'delivery_status':
                return (
                    <SelectFilter
                        key={key}
                        label="Trạng thái giao"
                        name="delivery_status"
                        value={filters.delivery_status}
                        options={filterOptions?.deliveryStatuses}
                        onChange={set}
                    />
                );
            case 'parent_product_id':
                return (
                    <SelectFilter
                        key={key}
                        label="Sản phẩm gốc"
                        name="parent_product_id"
                        value={filters.parent_product_id}
                        options={filterOptions?.parentProducts?.map((p) => ({ value: p.id, label: p.name }))}
                        onChange={set}
                    />
                );
            case 'operation_result':
                return (
                    <SelectFilter
                        key={key}
                        label="Kết quả tác nghiệp"
                        name="operation_result"
                        value={filters.operation_result}
                        options={filterOptions?.operationResults}
                        onChange={set}
                    />
                );
            case 'closing_status':
                return (
                    <SelectFilter
                        key={key}
                        label="Trạng thái chốt"
                        name="closing_status"
                        value={filters.closing_status}
                        options={filterOptions?.closingStatuses}
                        onChange={set}
                    />
                );
            case 'warehouse_id':
                return (
                    <SelectFilter
                        key={key}
                        label="Kho"
                        name="warehouse_id"
                        value={filters.warehouse_id}
                        options={filterOptions?.warehouses?.map((w) => ({ value: w.id, label: w.name }))}
                        onChange={set}
                    />
                );
            case 'sale_id':
                return (
                    <SelectFilter
                        key={key}
                        label="Sale phụ trách"
                        name="sale_id"
                        value={filters.sale_id}
                        options={filterOptions?.salesUsers?.map((u) => ({ value: u.id, label: u.name }))}
                        onChange={set}
                    />
                );
            case 'marketer_id':
                return (
                    <SelectFilter
                        key={key}
                        label="Nhân viên Marketing"
                        name="marketer_id"
                        value={filters.marketer_id}
                        options={filterOptions?.marketingUsers?.map((u) => ({ value: u.id, label: u.name }))}
                        onChange={set}
                    />
                );
            case 'no_closing_date_limit':
            case 'hide_zero_status':
            case 'hide_no_phone':
                return null;
            default:
                return null;
        }
    };

    const checkboxField = (key, label) =>
        fields.has(key) && (
            <label key={key} className="flex items-center gap-2 text-xs text-muted-foreground">
                <input
                    type="checkbox"
                    className="accent-primary"
                    checked={!!filters[key]}
                    onChange={(e) => set(key, e.target.checked ? 1 : 0)}
                />
                {label}
            </label>
        );

    const primaryToRender = PRIMARY_FIELDS.filter((key) => fields.has(key));
    const advancedToRender = advancedFieldKeys.filter(
        (key) => !['no_closing_date_limit', 'hide_zero_status', 'hide_no_phone'].includes(key)
    );

    return (
        <div className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                    {PRESET_OPTIONS.map((option) => (
                        <Button
                            key={option.value}
                            type="button"
                            size="sm"
                            variant={filters.preset === option.value ? 'default' : 'ghost'}
                            className={filters.preset !== option.value ? 'text-muted-foreground' : ''}
                            onClick={() => setPreset(option.value)}
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
                {advancedToRender.length > 0 && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="text-muted-foreground"
                        onClick={() => setShowAdvanced((v) => !v)}
                    >
                        <SlidersHorizontal className="size-3.5" />
                        Bộ lọc nâng cao
                        <ChevronDown
                            className={cn('size-3.5 transition-transform', showAdvanced && 'rotate-180')}
                        />
                    </Button>
                )}
            </div>

            <div className="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                {primaryToRender.map(renderField)}
            </div>

            {showAdvanced && advancedToRender.length > 0 && (
                <div className="mt-4 grid gap-x-5 gap-y-4 border-t border-border/60 pt-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    {advancedToRender.map(renderField)}
                </div>
            )}

            {extra}

            <div className="mt-5 flex flex-wrap items-center gap-4">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    Tìm kiếm
                </Button>
                {checkboxField('no_closing_date_limit', 'Không giới hạn ngày chốt')}
                {checkboxField('hide_zero_status', 'Ẩn tab trạng thái 0 đơn')}
                {checkboxField('hide_no_phone', 'Ẩn đơn không có SĐT')}
            </div>
        </div>
    );
}
