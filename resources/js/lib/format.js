export function formatCurrency(value) {
    if (value == null || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatNumber(value) {
    if (value == null) return '—';
    return new Intl.NumberFormat('vi-VN').format(value);
}

export function formatPercent(value) {
    if (value == null) return '—';
    return `${value}%`;
}
