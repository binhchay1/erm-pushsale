import { router } from '@inertiajs/react';

const PER_PAGE_OPTIONS = [10, 20, 50, 100];

function pageItems(current, last) {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, last, current - 1, current, current + 1]);

    if (current <= 3) {
        pages.add(2);
        pages.add(3);
        pages.add(4);
    }

    if (current >= last - 2) {
        pages.add(last - 1);
        pages.add(last - 2);
        pages.add(last - 3);
    }

    const sorted = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    const result = [];
    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            result.push(`ellipsis-${page}`);
        }
        result.push(page);
    });

    return result;
}

export function ReportPagination({
    routeUrl,
    filters,
    meta,
    scrollTargetId,
    perPageOptions = PER_PAGE_OPTIONS,
}) {
    if (!meta || Number(meta.total ?? 0) === 0) {
        return null;
    }

    const current = Number(meta.current_page ?? 1);
    const last = Math.max(1, Number(meta.last_page ?? 1));
    const perPage = Number(meta.per_page ?? filters?.per_page ?? 20);
    const items = pageItems(current, last);

    const navigate = (overrides) => {
        router.get(
            routeUrl,
            { ...filters, ...overrides },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    if (!scrollTargetId) return;

                    requestAnimationFrame(() => {
                        document.getElementById(scrollTargetId)?.scrollIntoView({
                            block: 'start',
                            behavior: 'smooth',
                        });
                    });
                },
            },
        );
    };

    const goToPage = (page) => {
        const nextPage = Math.min(last, Math.max(1, Number(page)));
        if (nextPage !== current) {
            navigate({ page: nextPage });
        }
    };

    return (
        <div className="pushsale-pagination ps-pagination-v81 is-split">
            <div className="ps-pagination-info">
                Hiển thị <b>{meta.from ?? 0}</b> - <b>{meta.to ?? 0}</b> / <b>{meta.total ?? 0}</b> bản ghi
            </div>

            <div className="ps-pagination-pages" role="navigation" aria-label="Phân trang">
                <button type="button" disabled={current <= 1} onClick={() => goToPage(1)} aria-label="Trang đầu" title="Trang đầu">«</button>
                <button type="button" disabled={current <= 1} onClick={() => goToPage(current - 1)} aria-label="Trang trước" title="Trang trước">‹</button>

                {items.map((item) => (
                    typeof item === 'number' ? (
                        <button
                            key={item}
                            type="button"
                            className={item === current ? 'active' : ''}
                            onClick={() => goToPage(item)}
                            aria-current={item === current ? 'page' : undefined}
                        >
                            {item}
                        </button>
                    ) : (
                        <span key={item} className="ellipsis">…</span>
                    )
                ))}

                <button type="button" disabled={current >= last} onClick={() => goToPage(current + 1)} aria-label="Trang sau" title="Trang sau">›</button>
                <button type="button" disabled={current >= last} onClick={() => goToPage(last)} aria-label="Trang cuối" title="Trang cuối">»</button>
            </div>

            <label className="ps-pagination-size">
                <span>Hiển thị</span>
                <select
                    value={perPage}
                    onChange={(event) => navigate({
                        per_page: Number(event.target.value),
                        page: 1,
                    })}
                >
                    {perPageOptions.map((value) => (
                        <option key={value} value={value}>{value}</option>
                    ))}
                </select>
                <span>dòng</span>
            </label>
        </div>
    );
}
