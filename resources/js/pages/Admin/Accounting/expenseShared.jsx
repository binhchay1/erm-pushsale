export function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

export function valueFromSearch(key, fallback = '') {
    if (typeof window === 'undefined') return fallback;
    return new URLSearchParams(window.location.search).get(key) ?? fallback;
}

export function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formErrorText(errors = {}) {
    return Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(Boolean)
        .join(' · ');
}

export function yearOptions(span = 8) {
    const current = new Date().getFullYear();
    return Array.from({ length: span }, (_, index) => {
        const year = current + 1 - index;
        return { id: String(year), label: `Năm ${year}` };
    });
}

export function monthOptions() {
    return Array.from({ length: 12 }, (_, index) => {
        const month = index + 1;
        return { id: String(month), label: `Tháng ${month}` };
    });
}

export function RequiredMark() {
    return <b className="required">(*)</b>;
}
