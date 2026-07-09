import { router } from '@inertiajs/react';
import { ChevronFirst, ChevronLast, ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';

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
    const t = useT();

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
        <div className="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 text-sm shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-muted-foreground">
                <span>
                    {t('common.pagination.showing', {
                        from: meta.from ?? 0,
                        to: meta.to ?? 0,
                        total: meta.total ?? 0,
                    })}
                </span>

                <label className="flex items-center gap-2">
                    <span>{t('common.pagination.rows_per_page')}</span>
                    <select
                        className="h-8 rounded-md border border-input bg-background px-2 text-foreground"
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
                </label>
            </div>

            <div className="flex flex-wrap items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    disabled={current <= 1}
                    onClick={() => goToPage(1)}
                    aria-label={t('common.pagination.first_page')}
                    title={t('common.pagination.first_page')}
                >
                    <ChevronFirst className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    disabled={current <= 1}
                    onClick={() => goToPage(current - 1)}
                    aria-label={t('common.pagination.previous_page')}
                    title={t('common.pagination.previous_page')}
                >
                    <ChevronLeft className="size-4" />
                </Button>

                {items.map((item) => (
                    typeof item === 'number' ? (
                        <Button
                            key={item}
                            type="button"
                            variant={item === current ? 'default' : 'outline'}
                            size="sm"
                            className="h-8 min-w-8 px-2"
                            onClick={() => goToPage(item)}
                            aria-current={item === current ? 'page' : undefined}
                        >
                            {item}
                        </Button>
                    ) : (
                        <span key={item} className="px-1 text-muted-foreground">…</span>
                    )
                ))}

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    disabled={current >= last}
                    onClick={() => goToPage(current + 1)}
                    aria-label={t('common.pagination.next_page')}
                    title={t('common.pagination.next_page')}
                >
                    <ChevronRight className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    disabled={current >= last}
                    onClick={() => goToPage(last)}
                    aria-label={t('common.pagination.last_page')}
                    title={t('common.pagination.last_page')}
                >
                    <ChevronLast className="size-4" />
                </Button>
            </div>
        </div>
    );
}
