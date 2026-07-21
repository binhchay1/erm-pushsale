import { useMemo, useState } from 'react';
import { toast } from 'sonner';

function normalizeDate(value) {
    const text = String(value ?? '').trim();
    if (!text) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;

    const slash = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (slash) {
        return `${slash[3]}-${slash[2].padStart(2, '0')}-${slash[1].padStart(2, '0')}`;
    }

    const iso = text.match(/^(\d{4}-\d{2}-\d{2})/);
    return iso?.[1] ?? '';
}

function displayDate(value) {
    const normalized = normalizeDate(value);
    if (!normalized) return '';
    const [year, month, day] = normalized.split('-');
    return `${day}/${month}/${year}`;
}

function rangeLabel(from, to, withTime = true) {
    const fromLabel = displayDate(from);
    const toLabel = displayDate(to);

    if (!fromLabel && !toLabel) return '';
    if (!withTime) return `${fromLabel || '...'} - ${toLabel || '...'}`;

    return `${fromLabel || '...'} 00:00 - ${toLabel || '...'} 23:59`;
}

function compareDates(from, to) {
    const left = normalizeDate(from);
    const right = normalizeDate(to);
    if (!left || !right) return 0;
    return left.localeCompare(right);
}

/**
 * Pushsale reusable date-range filter.
 *
 * Rule: `date_to` may not be earlier than `date_from`. When a user picks an
 * invalid range we keep the form usable by auto-correcting the opposite side
 * and showing the same warning everywhere this component is reused.
 */
export function DateRangeFilter({
    from,
    to,
    onChange,
    className = '',
    inputClassName = '',
    separator = '-',
    label,
    displayLabel = false,
    withTimeLabel = true,
    disabled = false,
}) {
    const [touched, setTouched] = useState(false);
    const normalizedFrom = normalizeDate(from);
    const normalizedTo = normalizeDate(to);
    const invalid = compareDates(normalizedFrom, normalizedTo) > 0;
    const title = useMemo(() => rangeLabel(normalizedFrom, normalizedTo, withTimeLabel), [normalizedFrom, normalizedTo, withTimeLabel]);

    const notifyInvalid = () => {
        toast.warning('Ngày đến không thể nhỏ hơn ngày từ.');
    };

    const setFrom = (value) => {
        const nextFrom = normalizeDate(value);
        let nextTo = normalizedTo;

        if (nextFrom && nextTo && compareDates(nextFrom, nextTo) > 0) {
            nextTo = nextFrom;
            notifyInvalid();
        }

        setTouched(true);
        onChange?.({ date_from: nextFrom, date_to: nextTo });
    };

    const setTo = (value) => {
        let nextTo = normalizeDate(value);
        const nextFrom = normalizedFrom;

        if (nextFrom && nextTo && compareDates(nextFrom, nextTo) > 0) {
            nextTo = nextFrom;
            notifyInvalid();
        }

        setTouched(true);
        onChange?.({ date_from: nextFrom, date_to: nextTo });
    };

    return (
        <div className={`ps-date-filter ${invalid ? 'has-error' : ''} ${touched ? 'is-touched' : ''} ${className}`.trim()} title={title}>
            {label ? <span className="ps-date-filter-title">{label}</span> : null}
            <input
                type="date"
                className={`form-control ps-date-filter-input ${inputClassName}`.trim()}
                value={normalizedFrom}
                max={normalizedTo || undefined}
                onChange={(event) => setFrom(event.target.value)}
                aria-label="Từ ngày"
                disabled={disabled}
            />
            <span className="ps-date-filter-separator">{separator}</span>
            <input
                type="date"
                className={`form-control ps-date-filter-input ${inputClassName}`.trim()}
                value={normalizedTo}
                min={normalizedFrom || undefined}
                onChange={(event) => setTo(event.target.value)}
                aria-label="Đến ngày"
                disabled={disabled}
            />
            {displayLabel ? <span className="ps-date-filter-label">{title}</span> : null}
            {invalid ? <span className="ps-date-filter-error">Ngày đến không thể nhỏ hơn ngày từ</span> : null}
        </div>
    );
}

export { normalizeDate as normalizeDateFilterValue, rangeLabel as dateRangeFilterLabel };
