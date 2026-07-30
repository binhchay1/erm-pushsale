// Toàn hệ thống hiển thị theo giờ Việt Nam, không phụ thuộc múi giờ trình duyệt.
export const APP_TIMEZONE = 'Asia/Ho_Chi_Minh';

function resolveLocale(locale) {
    if (locale) {
        return locale;
    }

    const lang = typeof document !== 'undefined' ? document.documentElement.lang : null;

    return lang?.startsWith('en') ? 'en-US' : 'vi-VN';
}

export function formatCurrency(value, locale) {
    if (value == null || Number.isNaN(Number(value))) return '—';

    return new Intl.NumberFormat(resolveLocale(locale), {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatCurrencyCompact(value, locale) {
    if (value == null || Number.isNaN(Number(value))) return '—';

    return new Intl.NumberFormat(resolveLocale(locale), {
        style: 'currency',
        currency: 'VND',
        notation: 'compact',
        compactDisplay: 'short',
        maximumFractionDigits: 1,
    }).format(Number(value));
}

export function formatNumber(value, locale) {
    if (value == null) return '—';

    return new Intl.NumberFormat(resolveLocale(locale)).format(value);
}

/** Chuỗi hiển thị khi nhập tiền (giữ số 0, có dấu phân cách hàng nghìn). */
export function formatMoneyInput(value, locale) {
    if (value == null || value === '') {
        return '';
    }

    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return '';
    }

    return formatNumber(numeric, locale);
}

/** Parse ô nhập tiền → số nguyên VND. */
export function parseMoneyInput(value) {
    if (value == null || value === '') {
        return 0;
    }

    const digits = String(value).replace(/[^\d]/g, '');

    if (digits === '') {
        return 0;
    }

    return parseInt(digits, 10);
}

export function formatPercent(value, { empty = '—', suffix = '%', spaceBeforeSuffix = false } = {}) {
    if (value == null || value === '') return empty;
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return empty;
    return `${numeric}${spaceBeforeSuffix ? ' ' : ''}${suffix}`;
}

export function formatDateTime(value, { withTime = true, withSeconds = true, locale } = {}) {
    if (!value) return '—';

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) return typeof value === 'string' ? value : '—';

    const formatter = new Intl.DateTimeFormat(resolveLocale(locale), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(withTime
            ? {
                hour: '2-digit',
                minute: '2-digit',
                ...(withSeconds ? { second: '2-digit' } : {}),
                hourCycle: 'h23',
            }
            : {}),
        timeZone: APP_TIMEZONE,
    });

    // vi-VN trả về đúng dạng quen thuộc: 08/07/2026 07:01:58.
    return formatter.format(date).replace(',', '');
}

export function formatDate(value, locale) {
    return formatDateTime(value, { withTime: false, locale });
}
