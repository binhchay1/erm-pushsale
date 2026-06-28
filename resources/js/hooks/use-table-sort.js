import { useMemo, useRef, useState } from 'react';

function compareValues(a, b, dir) {
    const mult = dir === 'asc' ? 1 : -1;

    if (a == null && b == null) return 0;
    if (a == null || a === '') return 1 * mult;
    if (b == null || b === '') return -1 * mult;

    const na = Number(a);
    const nb = Number(b);
    if (!Number.isNaN(na) && !Number.isNaN(nb) && String(a).trim() !== '' && String(b).trim() !== '') {
        return (na - nb) * mult;
    }

    return String(a).localeCompare(String(b), undefined, { sensitivity: 'base', numeric: true }) * mult;
}

/**
 * Client-side column sort for table rows — click header to toggle asc/desc.
 *
 * Realtime-safe: only `rows` and `sort` drive recomputation, so when props are
 * refreshed by a realtime/Inertia partial reload the data re-sorts automatically
 * while the chosen column + direction stay intact. Array.prototype.sort is stable,
 * so rows with equal keys keep their incoming (server) order.
 */
export function useTableSort(rows, { defaultKey = null, defaultDir = 'asc', accessors = {} } = {}) {
    const [sort, setSort] = useState({ key: defaultKey, dir: defaultDir });

    // Keep accessors in a ref so inline-defined accessor objects don't bust the memo
    // on every render (important for tables that re-render on realtime updates).
    const accessorsRef = useRef(accessors);
    accessorsRef.current = accessors;

    const sortedRows = useMemo(() => {
        const list = rows ?? [];
        if (!sort.key || !list.length) return list;

        const resolve = (row) => {
            const fn = accessorsRef.current[sort.key];
            return fn ? fn(row) : row[sort.key];
        };

        return [...list].sort((a, b) => compareValues(resolve(a), resolve(b), sort.dir));
    }, [rows, sort]);

    const toggleSort = (key) => {
        setSort((prev) => {
            if (prev.key !== key) return { key, dir: 'asc' };
            return { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' };
        });
    };

    return { sortedRows, sort, toggleSort };
}
