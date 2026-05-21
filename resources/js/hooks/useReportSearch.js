import { router } from '@inertiajs/react';

/**
 * @param {string} routeUrl
 * @param {Record<string, unknown>} filters
 */
export function useReportSearch(routeUrl, filters) {
    const search = (overrides = {}) => {
        router.get(
            routeUrl,
            { ...filters, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return { search };
}
