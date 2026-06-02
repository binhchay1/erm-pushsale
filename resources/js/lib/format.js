export function formatCurrency(value) {
    if (value == null || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatCurrencyCompact(value) {
    if (value == null || Number.isNaN(Number(value))) return '—';
    const n = Number(value);
    const abs = Math.abs(n);
    if (abs >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1).replace(/\.0$/, '')} tỷ`;
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, '')} tr`;
    if (abs >= 1_000) return `${Math.round(n / 1_000)}k`;
    return formatNumber(n);
}

export function formatNumber(value) {
    if (value == null) return '—';
    return new Intl.NumberFormat('vi-VN').format(value);
}

export function formatPercent(value) {
    if (value == null) return '—';
    return `${value}%`;
}

export function formatDateTime(value, { withTime = true } = {}) {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return typeof value === 'string' ? value : '—';
    return date.toLocaleString('vi-VN', withTime
        ? { dateStyle: 'short', timeStyle: 'short' }
        : { dateStyle: 'short' });
}

export function formatDate(value) {
    return formatDateTime(value, { withTime: false });
}
