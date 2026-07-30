import { useT } from '@/providers/I18nProvider';
import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { useInertiaFilters } from '@/hooks/useInertiaFilters';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';

export { PushsaleSearchButton };
export { useInertiaFilters };

function pad(value) {
    return String(value).padStart(2, '0');
}

function parseDate(value) {
    if (!value) return null;
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function displayDateRange(filters = {}) {
    const from = parseDate(filters.date_from);
    const to = parseDate(filters.date_to);
    const format = (date, end = false) => {
        if (!date) return '';
        return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${end ? '23:59' : '00:00'}`;
    };

    if (!from && !to) return '';
    return `${format(from || to)} - ${format(to || from, true)}`;
}

/** @deprecated Prefer useInertiaFilters — alias kept for existing report pages. */
export function usePushsaleFilters(routeUrl, filters = {}) {
    return useInertiaFilters(routeUrl, filters);
}

export function optionValue(option) {
    return option?.id ?? option?.value ?? '';
}

export function optionLabel(option) {
    return option?.name ?? option?.label ?? String(optionValue(option));
}

export function PushsaleSelect({ value = '', onChange, options = [], placeholder, className = '', disabled = false }) {
    return (
        <select
            className={`ps-control ${className}`.trim()}
            value={value ?? ''}
            disabled={disabled}
            onChange={(event) => onChange?.(event.target.value)}
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={optionValue(option)} value={optionValue(option)}>
                    {optionLabel(option)}
                </option>
            ))}
        </select>
    );
}

export function PushsaleDateRange({ filters, onChange, className = '' }) {
    const from = filters?.date_from ?? '';
    const to = filters?.date_to ?? '';

    // Single-button control only — do not add legacy `.ps-date-range` dual-grid
    // or `displayLabel` span (those reintroduce the stray "-" separator).
    // Keep `ps-date-range` class so report toolbar width contracts apply project-wide.
    return (
        <DateRangeFilter
            className={`ps-date-range ${className}`.trim()}
            from={from}
            to={to}
            onChange={({ date_from, date_to }) => {
                onChange?.('date_from', date_from);
                onChange?.('date_to', date_to);
            }}
        />
    );
}

function exportUrl(routeUrl, filters) {
    const params = new URLSearchParams();
    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined && value !== false) {
            params.set(key, String(value));
        }
    });
    params.set('export', '1');
    const separator = routeUrl.includes('?') ? '&' : '?';
    return `${routeUrl}${separator}${params.toString()}`;
}

export function PushsaleExportButton({ routeUrl, filters, label }) {
    const t = useT();
    const text = label ?? t('reports.pushsale.export_excel');

    return (
        <a className="ps-btn ps-btn-primary" href={exportUrl(routeUrl, filters)}>
            <i className="fa fa-file-excel-o" aria-hidden="true" />
            <span>{text}</span>
        </a>
    );
}

export function PushsaleActionMenu({ routeUrl, filters, onNote, exportLabel, noteLabel }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const textExport = exportLabel ?? t('reports.pushsale.export_excel');
    const textNote = noteLabel ?? t('reports.pushsale.note');

    return (
        <div className="ps-action-menu">
            <button
                type="button"
                className="ps-icon-btn ichucnang"
                aria-expanded={open}
                title={t('reports.pushsale.actions')}
                onClick={() => setOpen((value) => !value)}
            >
                <i className="fa fa-gear" aria-hidden="true" />
            </button>
            {open ? (
                <ul className="ps-action-menu__dropdown" role="menu">
                    <li>
                        <a href={exportUrl(routeUrl, filters)} role="menuitem" onClick={() => setOpen(false)}>
                            <i className="fa fa-file-excel-o" aria-hidden="true" />
                            <span>{textExport}</span>
                        </a>
                    </li>
                    {onNote ? (
                        <li>
                            <button type="button" role="menuitem" onClick={() => { setOpen(false); onNote(); }}>
                                <i className="fa fa-question-circle" aria-hidden="true" />
                                <span>{textNote}</span>
                            </button>
                        </li>
                    ) : null}
                </ul>
            ) : null}
        </div>
    );
}

/** Thin alias → unified PushsalePagination (variant=pager). */
export function PushsalePager({ current = 1, totalPages = 1, onPage, max = 7 }) {
    return (
        <PushsalePagination
            variant="pager"
            current={current}
            totalPages={totalPages}
            onPage={onPage}
            max={max}
        />
    );
}
