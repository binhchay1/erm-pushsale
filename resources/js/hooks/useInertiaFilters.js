import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Shared Inertia filter draft + visit helper (DRY #1).
 * Unifies usePushsaleFilters / useReportSearch / ecommerce useDraft.
 */

export function cleanInertiaFilters(values = {}) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => (
            value !== '' && value !== null && value !== undefined && value !== false
        )),
    );
}

export function readQueryFilters(defaults = {}) {
    if (typeof window === 'undefined') {
        return { ...defaults };
    }

    const params = new URLSearchParams(window.location.search);
    const next = { ...defaults };
    Object.keys(defaults).forEach((key) => {
        if (!params.has(key)) return;
        const value = params.get(key);
        if (typeof defaults[key] === 'boolean') {
            next[key] = value === '1' || value === 'true';
            return;
        }
        next[key] = value ?? defaults[key];
    });

    return next;
}

function mergeRouteQuery(routeUrl, payload) {
    const [path, query = ''] = String(routeUrl || '').split('?');
    const merged = { ...payload };
    const routeParams = new URLSearchParams(query);
    routeParams.forEach((value, key) => {
        if (!Object.prototype.hasOwnProperty.call(merged, key)) {
            merged[key] = value;
        }
    });
    return { path, payload: merged };
}

/**
 * @param {string} routeUrl
 * @param {Record<string, unknown>} filters Server / initial filters
 * @param {{
 *   sync?: boolean,
 *   clean?: boolean,
 *   resetPageOnApply?: boolean,
 *   preserveState?: boolean,
 *   preserveScroll?: boolean,
 *   replace?: boolean,
 * }} [options]
 */
export function useInertiaFilters(routeUrl, filters = {}, options = {}) {
    const {
        sync = true,
        clean = false,
        resetPageOnApply = true,
        preserveState = true,
        preserveScroll = true,
        replace = true,
    } = options;

    const [draft, setDraft] = useState(filters ?? {});

    useEffect(() => {
        if (!sync) return;
        setDraft(filters ?? {});
    }, [filters, sync]);

    const set = (key, value) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const setMany = (patch = {}) => {
        setDraft((current) => ({ ...current, ...patch }));
    };

    const visit = (rawPayload = {}, visitOptions = {}) => {
        const prepared = clean ? cleanInertiaFilters(rawPayload) : rawPayload;
        const { path, payload } = mergeRouteQuery(routeUrl, prepared);
        router.get(path, payload, {
            preserveState,
            preserveScroll,
            replace,
            ...visitOptions,
        });
    };

    /** Apply draft filters (search button). Resets page unless `extra.page` is set. */
    const apply = (extra = {}, visitOptions = {}) => {
        const payload = { ...draft, ...extra };
        if (resetPageOnApply && !Object.prototype.hasOwnProperty.call(extra, 'page')) {
            payload.page = 1;
        }
        visit(payload, visitOptions);
    };

    /**
     * Immediate visit from current `filters` prop + overrides (no draft).
     * Matches legacy useReportSearch behaviour.
     */
    const search = (overrides = {}, visitOptions = {}) => {
        visit({ ...(filters ?? {}), ...overrides }, visitOptions);
    };

    return {
        draft,
        set,
        setMany,
        setDraft,
        apply,
        search,
        visit,
    };
}
