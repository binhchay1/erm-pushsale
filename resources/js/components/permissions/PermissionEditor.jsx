import { ChevronDown, ChevronRight, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Label } from '@/components/ui/label';
import { useT } from '@/providers/I18nProvider';

const LEVEL_RANK = { none: 0, view: 1, full: 2 };

export function capLevel(level, max) {
    const l = LEVEL_RANK[level] ?? 0;
    const m = LEVEL_RANK[max] ?? 0;
    const value = Math.min(l, m);
    return Object.keys(LEVEL_RANK).find((k) => LEVEL_RANK[k] === value) ?? 'none';
}

export function capMap(map = {}, grantable = {}) {
    const out = {};
    Object.entries(map).forEach(([area, level]) => {
        out[area] = capLevel(level, grantable[area] ?? 'full');
    });
    return out;
}

/**
 * Bảng phân quyền theo khu vực, có thu gọn/mở rộng.
 *
 * Props:
 * - areas: string[] danh sách key khu vực
 * - value: { area: 'none'|'view'|'full' }
 * - grantable: { area: maxLevel } (mặc định full)
 * - onChange(nextMap)
 * - title, hint: string
 * - defaultOpen: bool
 * - showLevels: bool (false => chỉ tick bật/tắt = full)
 */
export default function PermissionEditor({
    areas = [],
    value = {},
    grantable = {},
    onChange,
    title,
    hint,
    defaultOpen = false,
    showLevels = true,
}) {
    const t = useT();
    const [open, setOpen] = useState(defaultOpen);

    const visibleAreas = useMemo(
        () => areas.filter((area) => (grantable[area] ?? 'full') !== 'none'),
        [areas, grantable],
    );

    const grantedCount = useMemo(
        () => visibleAreas.filter((area) => (value[area] ?? 'none') !== 'none').length,
        [visibleAreas, value],
    );

    const levelOptions = (area) => {
        const max = grantable[area] ?? 'full';
        const rank = LEVEL_RANK[max] ?? 2;
        const opts = [{ value: 'none', label: t('permissions.level.none') }];
        if (rank >= 1) opts.push({ value: 'view', label: t('permissions.level.view') });
        if (rank >= 2) opts.push({ value: 'full', label: t('permissions.level.full') });
        return opts;
    };

    const setArea = (area, level) => {
        onChange?.({ ...value, [area]: level });
    };

    const toggleArea = (area, checked) => {
        setArea(area, checked ? capLevel('full', grantable[area] ?? 'full') : 'none');
    };

    const setAll = (level) => {
        const next = { ...value };
        visibleAreas.forEach((area) => {
            next[area] = level === 'none' ? 'none' : capLevel(level, grantable[area] ?? 'full');
        });
        onChange?.(next);
    };

    if (visibleAreas.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            >
                <div className="flex items-center gap-2">
                    <ShieldCheck className="size-4 text-primary" />
                    <div>
                        <p className="text-sm font-semibold">{title ?? t('permissions.title')}</p>
                        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                        {t('permissions.granted_count', { count: grantedCount })}
                    </span>
                    {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                </div>
            </button>

            {open && (
                <div className="space-y-3 border-t px-4 py-3">
                    <div className="flex flex-wrap gap-2 text-xs">
                        <button
                            type="button"
                            className="rounded border px-2 py-1 hover:bg-muted"
                            onClick={() => setAll('full')}
                        >
                            {t('permissions.select_all')}
                        </button>
                        {showLevels && (
                            <button
                                type="button"
                                className="rounded border px-2 py-1 hover:bg-muted"
                                onClick={() => setAll('view')}
                            >
                                {t('permissions.select_view_all')}
                            </button>
                        )}
                        <button
                            type="button"
                            className="rounded border px-2 py-1 hover:bg-muted"
                            onClick={() => setAll('none')}
                        >
                            {t('permissions.clear_all')}
                        </button>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {visibleAreas.map((area) => (
                            <div key={area} className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
                                <Label className="text-sm font-normal">{t(`permissions.area.${area}`)}</Label>
                                {showLevels ? (
                                    <select
                                        className="input-soft h-8 w-28 px-2 text-sm"
                                        value={value[area] ?? 'none'}
                                        onChange={(e) => setArea(area, e.target.value)}
                                    >
                                        {levelOptions(area).map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        type="checkbox"
                                        className="size-4 accent-primary"
                                        checked={(value[area] ?? 'none') !== 'none'}
                                        onChange={(e) => toggleArea(area, e.target.checked)}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
