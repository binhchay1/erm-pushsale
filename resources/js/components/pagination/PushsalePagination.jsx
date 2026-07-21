import { router } from '@inertiajs/react';

const PER_PAGE_OPTIONS = [10, 20, 50, 100];

function clean(values) {
    return Object.fromEntries(Object.entries(values ?? {}).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false));
}

function compactPages(current, last) {
    const pages = [];
    if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);

    const wanted = new Set([1, last, current - 1, current, current + 1]);
    if (current <= 3) [2, 3, 4].forEach((page) => wanted.add(page));
    if (current >= last - 2) [last - 3, last - 2, last - 1].forEach((page) => wanted.add(page));

    [...wanted]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b)
        .forEach((page) => {
            const previous = pages[pages.length - 1];
            if (typeof previous === 'number' && page - previous > 1) pages.push(`ellipsis-${page}`);
            pages.push(page);
        });

    return pages;
}

export function PushsalePagination({
    meta,
    routeUrl,
    filters = {},
    itemLabel = 'bản ghi',
    perPageOptions = PER_PAGE_OPTIONS,
    align = 'split',
}) {
    if (!meta || Number(meta.total ?? 0) === 0) return null;

    const current = Number(meta.current_page ?? 1);
    const last = Math.max(1, Number(meta.last_page ?? 1));
    const perPage = Number(filters.per_page ?? meta.per_page ?? 20);
    const pages = compactPages(current, last);

    const visit = (overrides) => {
        const next = clean({ ...filters, ...overrides });
        router.get(routeUrl, next, { preserveState: true, preserveScroll: true, replace: true });
    };
    const go = (page) => {
        const nextPage = Math.min(last, Math.max(1, Number(page)));
        if (nextPage !== current) visit({ page: nextPage });
    };

    return (
        <div className={`pushsale-pagination ps-pagination-v81 is-${align}`.trim()}>
            <div className="ps-pagination-info">
                Hiển thị <b>{meta.from ?? 0}</b> - <b>{meta.to ?? 0}</b> / <b>{meta.total ?? 0}</b> {itemLabel}
            </div>
            <div className="ps-pagination-pages" role="navigation" aria-label="Phân trang">
                <button type="button" disabled={current <= 1} onClick={() => go(1)} title="Trang đầu">«</button>
                <button type="button" disabled={current <= 1} onClick={() => go(current - 1)} title="Trang trước">‹</button>
                {pages.map((page) => typeof page === 'number'
                    ? <button key={page} type="button" className={page === current ? 'active' : ''} onClick={() => go(page)} aria-current={page === current ? 'page' : undefined}>{page}</button>
                    : <span key={page} className="ellipsis">…</span>)}
                <button type="button" disabled={current >= last} onClick={() => go(current + 1)} title="Trang sau">›</button>
                <button type="button" disabled={current >= last} onClick={() => go(last)} title="Trang cuối">»</button>
            </div>
            <label className="ps-pagination-size">
                <span>Hiển thị</span>
                <select value={perPage} onChange={(event) => visit({ per_page: Number(event.target.value), page: 1 })}>
                    {perPageOptions.map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
                <span>dòng</span>
            </label>
        </div>
    );
}
