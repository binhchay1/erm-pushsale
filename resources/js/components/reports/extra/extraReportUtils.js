import { formatCurrency, formatNumber } from '@/lib/format';

export function psText(t, key, fallback) {
    const path = `reports.pushsale.${key}`;
    const translated = t(path);
    return translated !== path ? translated : fallback;
}

export function reportText(t, key, field, fallback) {
    const path = `reports.extra.${key}.${field}`;
    const translated = t(path);
    return translated !== path ? translated : fallback;
}

export function resolveColumnLabel(col, t, labels) {
    if (col.label_type === 'operation_stage' && col.label_key) {
        return labels.operation_stage?.[col.label_key] ?? col.label;
    }
    if (col.label_key) {
        const translated = t(`reports.columns.${col.label_key}`);
        if (translated !== `reports.columns.${col.label_key}`) return translated;
    }
    return col.label;
}

export function formatCell(value, format) {
    if (value === null || value === undefined || value === '') return '';
    if (format === 'currency') return formatCurrency(value);
    if (format === 'number') return formatNumber(value);
    if (format === 'percent') {
        const number = Number(value);
        return `${Number.isInteger(number) ? number : number.toFixed(2)}%`;
    }
    return value;
}

export function hasValue(value) {
    return value !== null && value !== undefined && value !== '' && value !== false;
}

export function cleanReportPayload(values = {}) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => hasValue(value)));
}

export function resolveOperationStages(filterOptions = {}) {
    return (filterOptions.operationStages ?? [])
        .map((stage) => ({
            key: stage.value ?? stage.id ?? stage.key,
            label: stage.label ?? stage.name ?? '',
        }))
        .filter((stage) => stage.key && stage.key !== 'no_operation');
}

export function rateToneClass(value) {
    const rate = Number(value);
    if (!Number.isFinite(rate) || rate <= 0) return '';
    if (rate < 50) return 'color-alert';
    if (rate < 80) return 'color-warning';
    return 'color-success';
}

export function metricValue(record, key, format) {
    return formatCell(record?.[key], format);
}

export function revenueShare(record, groupKey) {
    const total = Number(record?.total_revenue ?? 0);
    const value = Number(record?.[`${groupKey}_revenue`] ?? 0);
    return formatCell(total > 0 ? (value * 100) / total : 0, 'percent');
}

export const REVENUE_DIMENSION_OPTIONS = [
    { value: 'warehouse', label: '1.Kho' },
    { value: 'products_per_order', label: '2.Số sản phẩm/đơn' },
    { value: 'product', label: '3.Sản phẩm' },
    { value: 'sale', label: '4.Sale' },
    { value: 'marketing', label: '5.Marketing' },
    { value: 'care', label: '6.Care đơn' },
    { value: 'sale_team', label: '7.Nhóm sale' },
    { value: 'marketing_team', label: '8.Nhóm marketing' },
    { value: 'province', label: '9.Tỉnh/Thành phố' },
    { value: 'channel', label: '10.Kênh quảng cáo' },
    { value: 'customer_type', label: '11.Khách cũ/mới' },
    { value: 'shipping_method', label: '12.Phương thức giao hàng' },
];
