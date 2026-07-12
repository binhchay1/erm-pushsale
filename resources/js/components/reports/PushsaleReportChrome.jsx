import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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

export function usePushsaleFilters(routeUrl, filters = {}) {
    const [draft, setDraft] = useState(filters);

    useEffect(() => {
        setDraft(filters);
    }, [filters]);

    const set = (key, value) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const apply = (extra = {}) => {
        router.get(
            routeUrl,
            { ...draft, ...extra, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return { draft, set, apply };
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
    const label = useMemo(() => displayDateRange(filters), [from, to]);

    return (
        <div className={`ps-date-range ${className}`.trim()} title={label}>
            <input
                aria-label="Từ ngày"
                type="date"
                value={from}
                onChange={(event) => onChange?.('date_from', event.target.value)}
            />
            <span>-</span>
            <input
                aria-label="Đến ngày"
                type="date"
                value={to}
                onChange={(event) => onChange?.('date_to', event.target.value)}
            />
            <span className="ps-date-range-label">{label}</span>
        </div>
    );
}

export function PushsaleSearchButton({ onClick, label = 'Tìm kiếm' }) {
    return (
        <button type="button" className="ps-btn ps-btn-primary" onClick={onClick}>
            <i className="fa fa-search" aria-hidden="true" />
            <span>{label}</span>
        </button>
    );
}

function exportUrl(routeUrl, filters) {
    const params = new URLSearchParams();
    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined && value !== false) {
            params.set(key, String(value));
        }
    });
    params.set('export', 'xls');
    return `${routeUrl}?${params.toString()}`;
}

export function PushsaleExportButton({ routeUrl, filters, label = 'Xuất Excel' }) {
    return (
        <a className="ps-btn ps-btn-primary" href={exportUrl(routeUrl, filters)}>
            <i className="fa fa-file-excel-o" aria-hidden="true" />
            <span>{label}</span>
        </a>
    );
}

export function PushsalePager({ current = 1, totalPages = 1, onPage, max = 7 }) {
    const pageCount = Math.max(1, Math.min(totalPages, max));
    const pages = Array.from({ length: pageCount }, (_, index) => index + 1);

    return (
        <div className="ps-pager" aria-label="Phân trang">
            <button type="button" disabled={current <= 1} onClick={() => onPage?.(Math.max(1, current - 1))}>«</button>
            {pages.map((page) => (
                <button
                    type="button"
                    key={page}
                    className={page === current ? 'is-active' : ''}
                    onClick={() => onPage?.(page)}
                >
                    {page}
                </button>
            ))}
            <button type="button" disabled={current >= totalPages} onClick={() => onPage?.(Math.min(totalPages, current + 1))}>»</button>
        </div>
    );
}
