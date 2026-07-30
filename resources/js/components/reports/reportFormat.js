import { formatCurrency, formatNumber, formatPercent as formatPercentBase } from '@/lib/format';

/**
 * Shared report format helpers (DRY #9) — thin wrappers over `@/lib/format`
 * with report-table edge cases (∞ %, blank, strip ₫).
 */

export function formatReportNumber(value, { empty = '0', locale } = {}) {
    if (value === null || value === undefined || value === '') return empty;
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return empty;
    return formatNumber(numeric, locale);
}

export function formatReportPercent(value, {
    empty = '—',
    infinity = '∞ %',
    spaceBeforeSuffix = true,
    digits,
} = {}) {
    if (value === null || value === undefined || value === '') return empty;
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return infinity;

    let body;
    if (digits !== undefined) {
        body = numeric.toFixed(digits);
    } else if (Number.isInteger(numeric)) {
        body = String(numeric);
    } else {
        body = numeric.toFixed(2);
    }

    return `${body}${spaceBeforeSuffix ? ' ' : ''}%`;
}

export function formatReportMoney(value, {
    empty = '—',
    stripCurrencySymbol = false,
    locale,
} = {}) {
    if (value === null || value === undefined || value === '') return empty;
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return empty;
    const formatted = formatCurrency(numeric, locale);
    if (!stripCurrencySymbol) return formatted;
    return formatted.replace(/\s?₫$/, '').trim();
}

export { formatPercentBase as formatPercent };
