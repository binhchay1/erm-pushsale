import { useMemo } from 'react';

import { PushsaleSelect, normalizeOptionText } from './PushsaleSelect';

function asArray(value) {
    return Array.isArray(value) ? value : [];
}

function keyOfProvince(value) {
    return String(value ?? '').trim();
}

function keyOfDistrict(province, district) {
    return `${keyOfProvince(province)}||${String(district ?? '').trim()}`;
}

function uniqueOptions(items = [], currentValue = '') {
    const seen = new Set();
    const options = [];

    asArray(items).forEach((item) => {
        const value = String(item.value ?? item.name ?? item.label ?? '').trim();
        if (!value || seen.has(normalizeOptionText(value))) return;
        seen.add(normalizeOptionText(value));
        options.push({
            value,
            label: String(item.label ?? item.name ?? value),
            subLabel: item.subLabel,
            code: item.code,
            mode: item.mode,
        });
    });

    const current = String(currentValue ?? '').trim();
    if (current && !seen.has(normalizeOptionText(current))) {
        options.unshift({ value: current, label: current, subLabel: 'Giá trị đang lưu' });
    }

    return options;
}

export function oldProvinceOptions(locations = {}, currentValue = '') {
    return uniqueOptions(locations?.old?.provinces ?? [], currentValue);
}

export function newProvinceOptions(locations = {}, currentValue = '') {
    return uniqueOptions(locations?.new2025?.provinces ?? [], currentValue);
}

export function combinedProvinceOptions(locations = {}, currentValue = '') {
    const marker = [{ value: 'Địa chỉ 2 cấp 2025', label: 'Địa chỉ 2 cấp 2025', subLabel: 'Tỉnh/TP chuẩn 2025' }];
    const merged = [
        ...marker,
        ...(locations?.old?.provinces ?? []),
        ...(locations?.new2025?.provinces ?? []).filter((item) => !asArray(locations?.old?.provinces).some((old) => normalizeOptionText(old.name) === normalizeOptionText(item.name))),
    ];

    return uniqueOptions(merged, currentValue);
}

export function oldDistrictOptions(locations = {}, province = '', currentValue = '') {
    const key = keyOfProvince(province);
    const options = key ? (locations?.old?.districts?.[key] ?? []) : [];
    return uniqueOptions(options, currentValue);
}

export function oldWardOptions(locations = {}, province = '', district = '', currentValue = '') {
    const key = keyOfDistrict(province, district);
    const options = key ? (locations?.old?.wards?.[key] ?? []) : [];
    return uniqueOptions(options, currentValue);
}

export function newWardOptions(locations = {}, province = '', currentValue = '') {
    const key = keyOfProvince(province);
    const options = key ? (locations?.new2025?.wards?.[key] ?? []) : [];
    return uniqueOptions(options, currentValue);
}

export function AddressSelect({
    type,
    locations = {},
    mode = 'old',
    province = '',
    district = '',
    value = '',
    onChange,
    placeholder,
    disabled = false,
    className = '',
}) {
    const options = useMemo(() => {
        if (type === 'province') {
            return mode === 'combined'
                ? combinedProvinceOptions(locations, value)
                : mode === 'new2025'
                    ? newProvinceOptions(locations, value)
                    : oldProvinceOptions(locations, value);
        }

        if (type === 'district') {
            return oldDistrictOptions(locations, province, value);
        }

        if (type === 'ward') {
            return mode === 'new2025'
                ? newWardOptions(locations, province, value)
                : oldWardOptions(locations, province, district, value);
        }

        return [];
    }, [district, locations, mode, province, type, value]);

    const defaultPlaceholder = (() => {
        if (type === 'province') return '--Chọn Tỉnh/TP';
        if (type === 'district') return '--Quận/Huyện--';
        if (type === 'ward') return mode === 'new2025' ? '--Chọn Phường/Xã 2025--' : '--Phường/Xã--';
        return '--Chọn--';
    })();

    return (
        <PushsaleSelect
            searchable
            className={`ps-address-select ps-address-select--${type} ${className}`.trim()}
            options={options}
            value={value}
            onChange={onChange}
            placeholder={placeholder || defaultPlaceholder}
            searchPlaceholder="Gõ để tìm kiếm..."
            disabled={disabled}
        />
    );
}
