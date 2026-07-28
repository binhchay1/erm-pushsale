import { useEffect, useMemo, useRef, useState } from 'react';

const normalize = (items = []) => (Array.isArray(items) ? items : Object.values(items ?? {}))
    .filter(Boolean)
    .map((item) => ({
        ...item,
        id: item.id ?? item.value,
        name: item.name ?? item.label,
        sku: item.sku ?? '',
        type: item.type ?? item.product_type ?? 'product',
        unit_price: item.unit_price ?? item.price ?? 0,
    }));

const formatVnd = (value) => `${Number(value || 0).toLocaleString('vi-VN')} đ`;

export function ProductSearchSelect({
    products = [],
    value,
    onChange,
    placeholder = '--Sản phẩm / gói sản phẩm--',
    disabled = false,
    name,
    form,
    className = '',
    showPrice = true,
    allowClear = true,
}) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const closeTimer = useRef(null);
    const searchInputRef = useRef(null);
    const catalog = useMemo(() => normalize(products), [products]);
    const selected = catalog.find((item) => String(item.id) === String(value ?? '')) ?? null;
    const filtered = useMemo(() => {
        const q = keyword.trim().toLowerCase();
        if (!q) return catalog.slice(0, 80);

        return catalog.filter((item) => [item.name, item.sku, item.type]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(q)).slice(0, 80);
    }, [catalog, keyword]);

    useEffect(() => {
        if (!open) return;
        const timer = window.setTimeout(() => searchInputRef.current?.focus(), 0);
        return () => window.clearTimeout(timer);
    }, [open]);

    const select = (item) => {
        onChange?.(item ? String(item.id) : '');
        setKeyword('');
        setOpen(false);
    };

    const openMenu = () => {
        if (disabled) return;
        if (closeTimer.current) window.clearTimeout(closeTimer.current);
        setKeyword('');
        setOpen(true);
    };

    const closeLater = () => {
        closeTimer.current = window.setTimeout(() => setOpen(false), 140);
    };

    return (
        <div className={`ps-product-search-select ps-product-search-select-v82 ${disabled ? 'is-disabled' : ''} ${open ? 'is-open' : ''} ${className}`.trim()}>
            {name ? <input type="hidden" name={name} form={form} value={value ?? ''} readOnly /> : null}
            <button
                type="button"
                className="ps-product-select-button"
                disabled={disabled}
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => (open ? setOpen(false) : openMenu())}
                aria-haspopup="listbox"
                aria-expanded={open}
            >
                <span className={selected ? 'ps-product-select-label has-value' : 'ps-product-select-label'}>
                    {selected ? `${selected.name}${selected.sku ? ` (${selected.sku})` : ''}` : placeholder}
                </span>
                <span className="ps-product-select-arrow"><i className="fa fa-caret-down" /></span>
            </button>
            {selected && allowClear && !disabled ? (
                <button type="button" className="ps-product-clear" onMouseDown={(event) => event.preventDefault()} onClick={() => select(null)} title="Bỏ chọn">
                    <i className="fa fa-times" />
                </button>
            ) : null}
            {open && !disabled ? (
                <div className="ps-product-search-menu" onMouseDown={(event) => event.preventDefault()}>
                    <div className="ps-product-search-box">
                        <i className="fa fa-search" />
                        <input
                            ref={searchInputRef}
                            type="text"
                            className="form-control ps-product-search-input"
                            value={keyword}
                            onChange={(event) => setKeyword(event.target.value)}
                            onBlur={closeLater}
                            placeholder="Tìm theo tên, mã SKU hoặc loại..."
                            autoComplete="off"
                        />
                    </div>
                    <div className="ps-product-search-results" role="listbox">
                        {filtered.length ? filtered.map((item) => (
                            <button
                                type="button"
                                key={item.id}
                                role="option"
                                aria-selected={String(item.id) === String(value ?? '')}
                                className={String(item.id) === String(value ?? '') ? 'is-active' : ''}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => select(item)}
                            >
                                <span className="ps-product-name">{item.name}</span>
                                <span className="ps-product-meta">
                                    {item.type === 'combo' ? 'Gói sản phẩm' : 'Sản phẩm'}{item.sku ? ` · ${item.sku}` : ''}{showPrice ? ` · ${formatVnd(item.unit_price)}` : ''}
                                </span>
                            </button>
                        )) : <div className="ps-product-empty">Không tìm thấy sản phẩm/gói phù hợp</div>}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export function ProductMultiAdder({ products = [], selectedIds = [], onAdd }) {
    const [keyword, setKeyword] = useState('');
    const [checked, setChecked] = useState(new Set());
    const catalog = useMemo(() => normalize(products), [products]);
    const selectedSet = useMemo(() => new Set((selectedIds ?? []).map((id) => String(id)).filter(Boolean)), [selectedIds]);
    const filtered = useMemo(() => {
        const q = keyword.trim().toLowerCase();
        const source = q
            ? catalog.filter((item) => [item.name, item.sku, item.type].filter(Boolean).join(' ').toLowerCase().includes(q))
            : catalog;

        return source.slice(0, 120);
    }, [catalog, keyword]);

    const toggle = (id) => setChecked((current) => {
        const next = new Set(current);
        next.has(String(id)) ? next.delete(String(id)) : next.add(String(id));
        return next;
    });

    const add = () => {
        const items = catalog.filter((item) => checked.has(String(item.id)) && !selectedSet.has(String(item.id)));
        if (!items.length) return;
        onAdd?.(items);
        setChecked(new Set());
        setKeyword('');
    };

    const checkedCount = [...checked].filter((id) => !selectedSet.has(String(id))).length;

    return (
        <div className="ps-product-multi-adder">
            <div className="ps-product-multi-head">
                <input className="form-control" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm sản phẩm/gói theo tên hoặc mã..." />
                <button type="button" className="btn btn-success" disabled={checkedCount === 0} onClick={add}>
                    <i className="fa fa-plus" /> Thêm {checkedCount || ''} sản phẩm
                </button>
            </div>
            <div className="ps-product-multi-list">
                {filtered.map((item) => {
                    const exists = selectedSet.has(String(item.id));
                    return (
                        <label key={item.id} className={exists ? 'is-exists' : ''}>
                            <input type="checkbox" disabled={exists} checked={checked.has(String(item.id)) || exists} onChange={() => toggle(item.id)} />
                            <span className="ps-product-name">{item.name}</span>
                            <span className="ps-product-meta">{item.type === 'combo' ? 'Gói sản phẩm' : 'Sản phẩm'}{item.sku ? ` · ${item.sku}` : ''} · {formatVnd(item.unit_price)}</span>
                        </label>
                    );
                })}
            </div>
        </div>
    );
}
