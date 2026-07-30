import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

import { SelectBox } from '@/components/customers/CareCampaignDialogs';
import { cleanInertiaFilters } from '@/hooks/useInertiaFilters';

export function toCustomerDateRangeLabel(filters) {
    const from = filters?.date_from ? String(filters.date_from).split('-').reverse().join('/') : '';
    const to = filters?.date_to ? String(filters.date_to).split('-').reverse().join('/') : '';
    if (!from && !to) return '';
    return `${from} 00:00 - ${to} 23:59`.trim();
}

export function parseCustomerDateRange(value) {
    const matches = String(value ?? '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4}).*?(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (!matches) return null;
    const [, fd, fm, fy, td, tm, ty] = matches;
    return {
        date_from: `${fy}-${String(fm).padStart(2, '0')}-${String(fd).padStart(2, '0')}`,
        date_to: `${ty}-${String(tm).padStart(2, '0')}-${String(td).padStart(2, '0')}`,
    };
}

/**
 * Shared draft + date-range text + search for customer 3.3.x reports (DRY #11).
 * Keeps the existing `form-control date-range` UX (not PushsaleDateRange).
 */
export function useCustomerReportFilters(routeUrl, initial = {}) {
    const [draft, setDraft] = useState(initial);
    const [dateRange, setDateRange] = useState(toCustomerDateRangeLabel(initial));

    const queryFilters = useMemo(() => {
        const parsed = parseCustomerDateRange(dateRange);
        return cleanInertiaFilters({ ...draft, ...(parsed ?? {}) });
    }, [draft, dateRange]);

    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const setMany = (patch) => setDraft((current) => ({ ...current, ...patch }));

    const onDateRangeBlur = () => {
        const parsed = parseCustomerDateRange(dateRange);
        if (parsed) setMany(parsed);
    };

    const search = (extra = {}) => {
        router.get(routeUrl, cleanInertiaFilters({ ...queryFilters, ...extra }), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return {
        draft,
        set,
        setMany,
        setDraft,
        dateRange,
        setDateRange,
        onDateRangeBlur,
        queryFilters,
        search,
    };
}

export function CustomerReportDateRangeInput({ dateRange, setDateRange, onBlur }) {
    return (
        <input
            className="form-control date-range"
            value={dateRange}
            onChange={(event) => setDateRange(event.target.value)}
            onBlur={onBlur}
        />
    );
}

/**
 * Advanced sale + marketer row used by Spending / Multidimensional.
 */
export function CustomerReportAdvancedFilters({
    draft,
    set,
    filterOptions = {},
    cols = 2,
    children = null,
}) {
    return (
        <div className="ps-adv-filter-panel">
            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': cols }}>
                <SelectBox
                    value={draft.sale_id ?? ''}
                    onChange={(value) => set('sale_id', value)}
                    options={filterOptions.sales ?? []}
                    placeholder="--Chọn sale--"
                />
                <SelectBox
                    value={draft.marketer_id ?? ''}
                    onChange={(value) => set('marketer_id', value)}
                    options={filterOptions.marketers ?? []}
                    placeholder="--Chọn marketing--"
                />
                {children}
            </div>
        </div>
    );
}
