import { useEffect, useMemo, useRef, useState } from 'react';
import { translate } from '@/i18n/translate';
import { translateLegacyText } from '@/lib/legacyI18n';

export function normalizeOptionText(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function useOutsideClose(open, rootRef, onClose) {
    useEffect(() => {
        if (!open) return undefined;

        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) onClose();
        };
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') onClose();
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('touchstart', close, { passive: true });
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('touchstart', close);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open, onClose, rootRef]);
}

function toIntArray(value) {
    if (!Array.isArray(value)) return [];
    return value
        .map((item) => Number(item))
        .filter((item, index, source) => item > 0 && source.indexOf(item) === index);
}

function filterOptions(options, keyword) {
    const needle = normalizeOptionText(keyword);
    if (!needle) return options;

    return options.filter((option) => normalizeOptionText(`${option.label ?? ''} ${option.subLabel ?? ''}`).includes(needle));
}

export function PushsaleSelect({
    options = [],
    value = '',
    onChange,
    placeholder = '--Chọn--',
    disabled = false,
    searchable = false,
    searchPlaceholder,
    className = '',
    emptyLabel,
}) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const rootRef = useRef(null);
    const selected = options.find((option) => String(option.value) === String(value));
    const selectedLabel = selected ? translateLegacyText(selected.label) : null;
    const resolvedPlaceholder = translateLegacyText(placeholder);
    const resolvedEmptyLabel = emptyLabel ? translateLegacyText(emptyLabel) : null;
    const filtered = useMemo(() => filterOptions(options, keyword), [keyword, options]);
    useOutsideClose(open, rootRef, () => setOpen(false));

    const selectValue = (nextValue) => {
        onChange?.(nextValue);
        setOpen(false);
        setKeyword('');
    };

    return (
        <div ref={rootRef} className={`ps-select ${open ? 'is-open' : ''} ${disabled ? 'is-disabled' : ''} ${className}`.trim()}>
            <button
                type="button"
                className="form-control ps-select__control"
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => !disabled && setOpen((current) => !current)}
            >
                <span className={selected ? '' : 'is-placeholder'}>{selectedLabel || resolvedPlaceholder}</span>
                <i className="fa fa-caret-down" />
            </button>
            {open && !disabled && (
                <div className="ps-select__menu" role="listbox">
                    {searchable && (
                        <div className="ps-select__search-wrap">
                            <input
                                className="form-control ps-select__search"
                                autoFocus
                                value={keyword}
                                placeholder={translateLegacyText(searchPlaceholder || translate('common.search'))}
                                onChange={(event) => setKeyword(event.target.value)}
                            />
                            <i className="fa fa-search" aria-hidden="true" />
                        </div>
                    )}
                    <button type="button" className={`ps-select__option is-empty ${String(value) === '' ? 'active' : ''}`} onClick={() => selectValue('')}>
                        {resolvedPlaceholder}
                    </button>
                    <div className="ps-select__options">
                        {filtered.length ? filtered.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                className={`ps-select__option ${String(option.value) === String(value) ? 'active' : ''}`}
                                onClick={() => selectValue(option.value)}
                            >
                                <span>{translateLegacyText(option.label)}</span>
                                {option.subLabel && <small>{translateLegacyText(option.subLabel)}</small>}
                            </button>
                        )) : <div className="ps-select__empty">{resolvedEmptyLabel || translate('pages.empty_data')}</div>}
                    </div>
                </div>
            )}
        </div>
    );
}

export function PushsaleMultiSelect({
    label = 'nhân sự',
    enabled = true,
    selectedIds = [],
    options = [],
    onEnabledChange,
    onChange,
    placeholder,
    allLabel,
    emptyLabel,
    searchable = true,
    searchPlaceholder,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const rootRef = useRef(null);
    const ids = toIntArray(selectedIds);
    const allSelected = enabled && ids.length === 0;
    const resolvedLabel = translateLegacyText(label);
    const resolvedAllLabel = allLabel ? translateLegacyText(allLabel) : null;
    const resolvedEmptyLabel = emptyLabel ? translateLegacyText(emptyLabel) : null;
    const resolvedPlaceholder = placeholder ? translateLegacyText(placeholder) : null;
    const optionMap = useMemo(() => new Map(options.map((option) => [Number(option.value), option])), [options]);
    const filtered = useMemo(() => filterOptions(options, keyword), [keyword, options]);
    useOutsideClose(open, rootRef, () => setOpen(false));

    const displayText = (() => {
        if (!enabled) return resolvedEmptyLabel || translateLegacyText(`Không cho ${resolvedLabel} sử dụng`);
        if (allSelected) return resolvedAllLabel || translateLegacyText(`Tất cả ${resolvedLabel} đều có quyền`);
        const names = ids.map((id) => optionMap.get(id)?.label).filter(Boolean);
        if (names.length === 0) return resolvedPlaceholder || translateLegacyText(`--Chọn ${resolvedLabel}--`);
        if (names.length <= 2) return names.join(', ');
        return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
    })();

    const setAll = () => {
        onEnabledChange?.(true);
        onChange?.([]);
        setKeyword('');
    };

    const setNone = () => {
        onEnabledChange?.(false);
        onChange?.([]);
        setKeyword('');
    };

    const toggleId = (id) => {
        const numberId = Number(id);
        const next = ids.includes(numberId) ? ids.filter((item) => item !== numberId) : [...ids, numberId];
        onEnabledChange?.(true);
        onChange?.(next);
    };

    return (
        <div ref={rootRef} className={`ps-select ps-select--multi ${open ? 'is-open' : ''} ${!enabled ? 'is-disabled-value' : ''} ${className}`.trim()}>
            <button
                type="button"
                className="form-control ps-select__control"
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
            >
                <span className={!enabled ? 'is-placeholder' : ''}>{displayText}</span>
                <i className="fa fa-caret-down" />
            </button>
            {open && (
                <div className="ps-select__menu" role="listbox">
                    {searchable && (
                        <div className="ps-select__search-wrap">
                            <input
                                className="form-control ps-select__search"
                                autoFocus
                                value={keyword}
                                placeholder={translateLegacyText(searchPlaceholder || `Tìm ${String(resolvedLabel).toLowerCase()}...`)}
                                onChange={(event) => setKeyword(event.target.value)}
                            />
                            <i className="fa fa-search" aria-hidden="true" />
                        </div>
                    )}
                    <button type="button" className={`ps-select__option ${allSelected ? 'active' : ''}`} onClick={setAll}>
                        <span>{resolvedAllLabel || translateLegacyText(`Tất cả ${resolvedLabel} đều có quyền`)}</span>
                    </button>
                    <button type="button" className={`ps-select__option ${!enabled ? 'active' : ''}`} onClick={setNone}>
                        <span>{resolvedEmptyLabel || translateLegacyText(`Không cho ${resolvedLabel} sử dụng sản phẩm này`)}</span>
                    </button>
                    <div className="ps-select__options">
                        {filtered.length ? filtered.map((option) => {
                            const checked = ids.includes(Number(option.value));
                            return (
                                <button key={option.value} type="button" className={`ps-select__option ${checked ? 'active' : ''}`} onClick={() => toggleId(option.value)}>
                                    <input type="checkbox" readOnly checked={checked} />
                                    <span>{translateLegacyText(option.label)}{option.subLabel && <small>{translateLegacyText(option.subLabel)}</small>}</span>
                                </button>
                            );
                        }) : <div className="ps-select__empty">{resolvedEmptyLabel || translate('pages.empty_data')}</div>}
                    </div>
                </div>
            )}
        </div>
    );
}
