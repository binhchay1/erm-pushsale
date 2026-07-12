import { ChevronDown, Search, SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';

import { SelectFilter } from '@/components/filters/SelectFilter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useLocalizedFilterOptions } from '@/hooks/use-localized-filter-options';
import { useReportSearch } from '@/hooks/useReportSearch';
import { useT } from '@/providers/I18nProvider';

const PRIMARY_FIELDS = ['date_from', 'date_to', 'product_id', 'search'];

const PRESET_KEYS = ['today', 'last_7_days', 'last_30_days', 'this_month'];

export function ReportFilterBar({ routeUrl, filters, filterOptions, filterFields, extra = null }) {
    const t = useT();
    const localizedOptions = useLocalizedFilterOptions(filterOptions);
    const { search } = useReportSearch(routeUrl, filters);
    const fields = useMemo(
        () => new Set(filterFields ?? PRIMARY_FIELDS),
        [filterFields],
    );

    const advancedFieldKeys = useMemo(
        () => [...fields].filter((f) => !PRIMARY_FIELDS.includes(f)),
        [fields],
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
                        <Label className="text-xs font-medium text-foreground/80">{t('filters.date_from')}</Label>
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
                        <Label className="text-xs font-medium text-foreground/80">{t('filters.date_to')}</Label>
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
                        label={t('filters.product')}
                        name="product_id"
                        value={filters.product_id}
                        options={filterOptions?.products?.map((p) => ({ value: p.id, label: p.name }))}
                        onChange={set}
                    />
                );
            case 'search':
                return (
                    <div key={key} className="space-y-1.5 sm:col-span-2">
                        <Label className="text-xs font-medium text-foreground/80">
                            {t('filters.search_label')}
                        </Label>
                        <Input
                            value={filters.search ?? ''}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder={t('filters.search_placeholder')}
                        />
                    </div>
                );
            case 'date_type':
                return (
                    <SelectFilter
                        key={key}
                        label={t('filters.date_type')}
                        name="date_type"
                        value={filters.date_type}
                        options={localizedOptions?.dateTypes}
                        onChange={set}
                    />
                );
            case 'delivery_status':
                return (
                    <SelectFilter
                        key={key}
                        label={t('filters.delivery_status')}
                        name="delivery_status"
                        value={filters.delivery_status}
                        options={localizedOptions?.deliveryStatuses}
                        onChange={set}
                    />
                );
            case 'parent_product_id':
                return (
                    <SelectFilter
                        key={key}
                        label={t('filters.parent_product')}
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
                        label={t('filters.operation_result')}
                        name="operation_result"
                        value={filters.operation_result}
                        options={localizedOptions?.operationResults}
                        onChange={set}
                    />
                );
            case 'closing_status':
                return (
                    <SelectFilter
                        key={key}
                        label={t('filters.closing_status')}
                        name="closing_status"
                        value={filters.closing_status}
                        options={localizedOptions?.closingStatuses}
                        onChange={set}
                    />
                );
            case 'warehouse_id':
                return (
                    <SelectFilter
                        key={key}
                        label={t('filters.warehouse')}
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
                        label={t('filters.sale')}
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
                        label={t('filters.marketer')}
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
            <label key={key} className="flex items-center gap-2 text-xs text-foreground/80">
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
        (key) => !['no_closing_date_limit', 'hide_zero_status', 'hide_no_phone'].includes(key),
    );

    return (
        <div className="pushsale-filter-bar">
            <div className="pushsale-filter-presets flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                    {PRESET_KEYS.map((value) => (
                        <Button
                            key={value}
                            type="button"
                            size="sm"
                            variant={filters.preset === value ? 'default' : 'ghost'}
                            className={filters.preset !== value ? 'text-muted-foreground' : ''}
                            onClick={() => setPreset(value)}
                        >
                            {t(`filters.presets.${value}`)}
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
                        {t('filters.advanced')}
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

            <div className="mt-3 flex flex-wrap items-center gap-4">
                <Button size="sm" onClick={() => search()}>
                    <Search className="size-4" />
                    {t('common.search')}
                </Button>
                {checkboxField('no_closing_date_limit', t('filters.no_closing_date_limit'))}
                {checkboxField('hide_zero_status', t('filters.hide_zero_status'))}
                {checkboxField('hide_no_phone', t('filters.hide_no_phone'))}
            </div>
        </div>
    );
}
