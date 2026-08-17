import { router } from '@inertiajs/react';
import { useT } from '@/providers/I18nProvider';

import { cleanInertiaFilters } from '@/hooks/useInertiaFilters';

const DEFAULT_PER_PAGE_OPTIONS = [10, 20, 50, 100];

export function compactPaginationPages(current, last) {
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

function scrollToTarget(scrollTargetId) {
    if (!scrollTargetId) return;
    requestAnimationFrame(() => {
        document.getElementById(scrollTargetId)?.scrollIntoView({
            block: 'start',
            behavior: 'smooth',
        });
    });
}

/**
 * Unified pagination (DRY #4).
 *
 * Modes:
 * - Server/Inertia: pass `meta` + `routeUrl` (+ optional `filters`)
 * - Client pager: pass `current` + `totalPages` + `onPage`
 * - Ops sale workspace chrome: `variant="ops"`
 */
export function PushsalePagination({
    meta,
    routeUrl,
    filters = {},
    itemLabel = null,
    perPageOptions = DEFAULT_PER_PAGE_OPTIONS,
    align = 'split',
    scrollTargetId,
    variant = 'default',
    current: currentProp,
    totalPages: totalPagesProp,
    onPage,
    max = 7,
    showInfo = true,
    showPerPage = true,
    showWhenEmpty = false,
    className = '',
}) {
    const t = useT();
    const isClientPager = typeof onPage === 'function' && !meta;

    const current = Number(
        isClientPager
            ? (currentProp ?? 1)
            : (meta?.current_page ?? currentProp ?? 1),
    ) || 1;
    const last = Math.max(
        1,
        Number(
            isClientPager
                ? (totalPagesProp ?? 1)
                : (meta?.last_page ?? totalPagesProp ?? 1),
        ) || 1,
    );

    if (!isClientPager) {
        if (!meta) return null;
        // Ops workspace historically rendered even when total=0; default chrome hides empty.
        if (variant !== 'ops' && !showWhenEmpty && Number(meta.total ?? 0) === 0) return null;
    } else if (last <= 1 && !showWhenEmpty) {
        return null;
    }

    const perPage = Number(filters.per_page ?? meta?.per_page ?? 20);
    const pages = compactPaginationPages(current, last);

    const visit = (overrides = {}) => {
        if (!routeUrl) return;
        const next = cleanInertiaFilters({ ...filters, ...overrides });
        router.get(routeUrl, next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => scrollToTarget(scrollTargetId),
        });
    };

    const go = (page) => {
        const nextPage = Math.min(last, Math.max(1, Number(page)));
        if (nextPage === current) return;
        if (typeof onPage === 'function') {
            onPage(nextPage);
            return;
        }
        visit({ page: nextPage });
    };

    if (variant === 'ops') {
        const windowSize = 10;
        let start = Math.max(1, current - Math.floor(windowSize / 2));
        let end = Math.min(last, start + windowSize - 1);
        start = Math.max(1, end - windowSize + 1);
        const windowPages = [];
        for (let page = start; page <= end; page += 1) windowPages.push(page);

        return (
            <div className={`ps-sale-pagination-wrap ${className}`.trim()}>
                {showInfo ? (
                    <div className="ps-sale-pagination-info">
                        <b>{meta?.from ?? 0}</b> - <b>{meta?.to ?? 0}</b> / <b>{meta?.total ?? 0}</b>
                        {showPerPage ? (
                            <label className="ps-page-size">
                                <span>{t('common.pagination.rows')}</span>
                                <select
                                    className="form-control input-sm"
                                    value={perPage}
                                    onChange={(event) => visit({ page: 1, per_page: Number(event.target.value) })}
                                >
                                    {perPageOptions.map((value) => (
                                        <option key={value} value={value}>{value}</option>
                                    ))}
                                </select>
                            </label>
                        ) : null}
                    </div>
                ) : null}
                <ul className="pagination pagination-sm ps-sale-pagination" aria-label={t('common.pagination.label')}>
                    <li className={current <= 1 ? 'disabled' : ''}>
                        <button type="button" onClick={() => go(1)} aria-label={t('common.pagination.first_page')}>«</button>
                    </li>
                    <li className={current <= 1 ? 'disabled' : ''}>
                        <button type="button" onClick={() => go(current - 1)} aria-label={t('common.pagination.previous_page')}>‹</button>
                    </li>
                    {start > 1 && <li className="disabled"><span>…</span></li>}
                    {windowPages.map((page) => (
                        <li key={page} className={page === current ? 'active' : ''}>
                            <button type="button" onClick={() => go(page)}>{page}</button>
                        </li>
                    ))}
                    {end < last && <li className="disabled"><span>…</span></li>}
                    <li className={current >= last ? 'disabled' : ''}>
                        <button type="button" onClick={() => go(current + 1)} aria-label={t('common.pagination.next_page')}>›</button>
                    </li>
                    <li className={current >= last ? 'disabled' : ''}>
                        <button type="button" onClick={() => go(last)} aria-label={t('common.pagination.last_page')}>»</button>
                    </li>
                </ul>
            </div>
        );
    }

    if (variant === 'pager' || isClientPager) {
        const label = t('reports.pushsale.pagination');
        const windowSize = Math.max(3, Math.min(last, max));
        let start = Math.max(1, current - Math.floor(windowSize / 2));
        let end = Math.min(last, start + windowSize - 1);
        start = Math.max(1, end - windowSize + 1);
        const windowPages = Array.from({ length: end - start + 1 }, (_, index) => start + index);

        return (
            <div
                className={`ps-pager ps-pagination-v81 pushsale-pagination ${className}`.trim()}
                aria-label={label !== 'reports.pushsale.pagination' ? label : t('common.pagination.label')}
            >
                <button type="button" disabled={current <= 1} onClick={() => go(1)} title={t('common.pagination.first_page')}>«</button>
                <button type="button" disabled={current <= 1} onClick={() => go(current - 1)} title={t('common.pagination.previous_page')}>‹</button>
                {windowPages.map((page) => (
                    <button
                        key={page}
                        type="button"
                        className={page === current ? 'is-active active' : ''}
                        onClick={() => go(page)}
                        aria-current={page === current ? 'page' : undefined}
                    >
                        {page}
                    </button>
                ))}
                <button type="button" disabled={current >= last} onClick={() => go(current + 1)} title={t('common.pagination.next_page')}>›</button>
                <button type="button" disabled={current >= last} onClick={() => go(last)} title={t('common.pagination.last_page')}>»</button>
            </div>
        );
    }

    return (
        <div className={`pushsale-pagination ps-pagination-v81 is-${align} ${className}`.trim()}>
            {showInfo ? (
                <div className="ps-pagination-info">
                    <b>{meta?.from ?? 0}</b> - <b>{meta?.to ?? 0}</b> / <b>{meta?.total ?? 0}</b> {itemLabel ?? t('common.pagination.records')}
                </div>
            ) : null}
            <div className="ps-pagination-pages" role="navigation" aria-label={t('common.pagination.label')}>
                <button type="button" disabled={current <= 1} onClick={() => go(1)} title={t('common.pagination.first_page')} aria-label={t('common.pagination.first_page')}>«</button>
                <button type="button" disabled={current <= 1} onClick={() => go(current - 1)} title={t('common.pagination.previous_page')} aria-label={t('common.pagination.previous_page')}>‹</button>
                {pages.map((page) => (
                    typeof page === 'number' ? (
                        <button
                            key={page}
                            type="button"
                            className={page === current ? 'active' : ''}
                            onClick={() => go(page)}
                            aria-current={page === current ? 'page' : undefined}
                        >
                            {page}
                        </button>
                    ) : (
                        <span key={page} className="ellipsis">…</span>
                    )
                ))}
                <button type="button" disabled={current >= last} onClick={() => go(current + 1)} title={t('common.pagination.next_page')} aria-label={t('common.pagination.next_page')}>›</button>
                <button type="button" disabled={current >= last} onClick={() => go(last)} title={t('common.pagination.last_page')} aria-label={t('common.pagination.last_page')}>»</button>
            </div>
            {showPerPage ? (
                <label className="ps-pagination-size">
                    <span>{t('common.pagination.rows')}</span>
                    <select
                        value={perPage}
                        onChange={(event) => visit({ per_page: Number(event.target.value), page: 1 })}
                    >
                        {perPageOptions.map((value) => (
                            <option key={value} value={value}>{value}</option>
                        ))}
                    </select>
                </label>
            ) : null}
        </div>
    );
}
