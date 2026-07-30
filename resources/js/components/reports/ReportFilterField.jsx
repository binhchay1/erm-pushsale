import { OperationStageSelect } from '@/components/filters/OperationStageSelect';
import {
    PushsaleDateRange,
    PushsaleSelect,
} from '@/components/reports/PushsaleReportChrome';
import {
    getReportFieldDef,
    reportPerPageOptions,
    resolveFilterOptions,
} from '@/config/reportFilters';
import { useT } from '@/providers/I18nProvider';

function translateOr(t, key, fallback) {
    if (!key) return fallback;
    const translated = t(key);
    return translated !== key ? translated : fallback;
}

/**
 * Renders one report filter control from the shared field catalog (DRY #2/#3).
 */
export function ReportFilterField({
    field,
    draft,
    onChange,
    filterOptions = {},
    className = '',
}) {
    const t = useT();
    const def = getReportFieldDef(field);
    if (!def || def.type === 'hidden') return null;

    const placeholder = translateOr(t, def.placeholderKey, def.placeholder ?? '');
    const set = (value) => onChange?.(field, value);

    if (def.type === 'date_range') {
        return (
            <PushsaleDateRange
                filters={draft}
                onChange={onChange}
                className={className}
            />
        );
    }

    if (def.type === 'operation_stage') {
        return (
            <OperationStageSelect
                value={draft?.[field] ?? ''}
                onChange={set}
                filterOptions={filterOptions}
                placeholder={placeholder}
                className={className}
            />
        );
    }

    if (def.type === 'search') {
        return (
            <input
                className={`ps-control ${className}`.trim()}
                value={draft?.[field] ?? ''}
                placeholder={placeholder}
                onChange={(event) => set(event.target.value)}
            />
        );
    }

    if (def.type === 'checkbox') {
        const label = translateOr(t, def.labelKey, def.label ?? field);
        return (
            <label className={`ps-report-check ${className}`.trim()}>
                <input
                    type="checkbox"
                    checked={Boolean(draft?.[field])}
                    onChange={(event) => set(event.target.checked ? 1 : 0)}
                />
                <span>{label}</span>
            </label>
        );
    }

    let options = resolveFilterOptions(filterOptions, ...(def.optionsKeys ?? []));
    if (!options.length) {
        options = def.fallbackOptions ?? (field === 'per_page' ? reportPerPageOptions() : []);
    }

    return (
        <PushsaleSelect
            value={draft?.[field] ?? ''}
            options={options}
            placeholder={placeholder}
            onChange={set}
            className={className}
        />
    );
}
