import { PageHeader } from '@/components/layout/PageHeader';
import { ReportExportControl } from '@/components/reports/ReportExportControl';
import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';

/**
 * Shared CEO plan/KPI filter chrome (DRY #8).
 * Keeps existing form-control selects; pages own filter shape + body tables.
 */
export function CeoPlanToolbar({
    title,
    pageCode,
    filtersSlot,
    onSearch,
    routeUrl,
    exportFilters,
    showExport = true,
    exportLabel = 'Xuất Excel',
    actionsExtra = null,
    notice = null,
    className = '',
    searchDisabled = false,
}) {
    return (
        <PageHeader
            title={title}
            pageCode={pageCode}
            className={className}
            filters={filtersSlot}
            notice={notice}
            actions={(
                <>
                    <PushsaleSearchButton onClick={onSearch} label="Tìm kiếm" disabled={searchDisabled} />
                    {showExport ? (
                        <ReportExportControl
                            mode="visit"
                            routeUrl={routeUrl}
                            filters={exportFilters}
                            label={exportLabel}
                        />
                    ) : null}
                    {actionsExtra}
                </>
            )}
        />
    );
}

export function ceoCurrentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

export function ceoNumberValue(value) {
    const number = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(number) ? number : 0;
}

export function ceoMoney(value) {
    return ceoNumberValue(value).toLocaleString('vi-VN');
}

export function ceoFormatDateTime(value) {
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

export const CEO_POSITIONS = [
    ['marketing', 'Marketing'],
    ['sales', 'Sale'],
];

export function ceoYearOptions(span = 8) {
    const current = new Date().getFullYear();
    return Array.from({ length: span }, (_, index) => current + 1 - index);
}

export function ceoMonthOptions() {
    return Array.from({ length: 12 }, (_, index) => index + 1);
}

export function CeoNativeSelect({ value, onChange, children, className = 'form-control' }) {
    return (
        <select className={className} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            {children}
        </select>
    );
}
