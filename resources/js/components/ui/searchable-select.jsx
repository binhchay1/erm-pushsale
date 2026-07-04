import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

function normalize(str) {
    return String(str ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase();
}

/**
 * Select có ô tìm kiếm (combobox). options: [{ value, label }].
 * Dùng cho các ô chọn đơn vị hành chính (dữ liệu lớn nên cần lọc).
 */
export function SearchableSelect({
    value,
    onChange,
    options = [],
    placeholder = 'Chọn...',
    searchPlaceholder = 'Tìm kiếm...',
    emptyText = 'Không có kết quả',
    disabled = false,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const wrapRef = useRef(null);
    const inputRef = useRef(null);

    const selected = useMemo(
        () => options.find((o) => String(o.value) === String(value)),
        [options, value],
    );

    const filtered = useMemo(() => {
        const q = normalize(query.trim());
        if (!q) return options;
        return options.filter((o) => normalize(o.label).includes(q));
    }, [options, query]);

    useEffect(() => {
        if (!open) return undefined;
        const onDocClick = (e) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        const onKey = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onDocClick);
        document.addEventListener('keydown', onKey);
        setTimeout(() => inputRef.current?.focus(), 0);
        return () => {
            document.removeEventListener('mousedown', onDocClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div ref={wrapRef} className={`relative ${className}`}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((v) => !v)}
                className="input-soft flex h-9 w-full items-center justify-between gap-2 px-3 text-left text-sm disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span className={selected ? '' : 'text-muted-foreground'}>
                    {selected ? selected.label : placeholder}
                </span>
                <ChevronsUpDown className="size-3.5 shrink-0 opacity-50" />
            </button>

            {open && (
                <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-border bg-popover shadow-lg">
                    <div className="flex items-center gap-2 border-b border-border px-2.5 py-1.5">
                        <Search className="size-3.5 shrink-0 opacity-50" />
                        <input
                            ref={inputRef}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="w-full bg-transparent text-sm outline-none"
                        />
                    </div>
                    <div className="max-h-56 overflow-y-auto py-1">
                        {filtered.length === 0 && (
                            <div className="px-3 py-2 text-sm text-muted-foreground">{emptyText}</div>
                        )}
                        {filtered.map((o) => {
                            const isSel = String(o.value) === String(value);
                            return (
                                <button
                                    key={o.value}
                                    type="button"
                                    onClick={() => {
                                        onChange(String(o.value));
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                    className={`flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent ${
                                        isSel ? 'bg-accent/60 font-medium' : ''
                                    }`}
                                >
                                    <span>{o.label}</span>
                                    {isSel && <Check className="size-3.5 shrink-0 text-primary" />}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
