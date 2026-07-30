import { useInertiaFilters } from '@/hooks/useInertiaFilters';

/**
 * @deprecated Prefer useInertiaFilters. Kept as a thin alias for report-search callers.
 * @param {string} routeUrl
 * @param {Record<string, unknown>} filters
 */
export function useReportSearch(routeUrl, filters) {
    const { search } = useInertiaFilters(routeUrl, filters, { sync: false });
    return { search };
}
