import { useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * Shared Pushsale page frame used by every menu page.
 *
 * Structure (matches Pushsale m-header + body):
 * 1) Header main row: title | primaryFilters | actions (search / gear / help)
 * 2) Optional advanced filter row below the header
 * 3) Optional toolbar row (list actions)
 * 4) Body content (table / form / report)
 */
export function PushsalePageShell({
    title,
    subtitle,
    icon,
    filters = null,
    primaryFilters = null,
    advancedFilters = null,
    toolbar = null,
    actions = null,
    notice = null,
    children,
    className = '',
    headerClassName = '',
    bodyClassName = '',
    compact = false,
    defaultFiltersCollapsed = false,
    collapsible = true,
    ...props
}) {
    const [filtersCollapsed, setFiltersCollapsed] = useState(defaultFiltersCollapsed);
    const resolvedPrimaryFilters = primaryFilters ?? filters;
    const hasPrimaryFilters = Boolean(resolvedPrimaryFilters);
    const hasAdvancedFilters = Boolean(advancedFilters);
    const hasActions = Boolean(actions) || (collapsible && hasAdvancedFilters);
    const hasToolbar = Boolean(toolbar);

    const mainRowLayout = cn(
        'ps-page-shell__main-row',
        !hasPrimaryFilters && !hasActions && 'is-title-only',
        !hasPrimaryFilters && hasActions && 'is-title-actions',
        hasPrimaryFilters && 'has-primary-filters',
    );

    return (
        <section
            className={cn(
                'ps-page-shell',
                compact && 'is-compact',
                hasAdvancedFilters && 'has-advanced-filters',
                filtersCollapsed && 'is-filter-collapsed',
                className,
            )}
            {...props}
        >
            <header
                className={cn(
                    'ps-page-shell__header',
                    hasAdvancedFilters && 'has-advanced-filters',
                    filtersCollapsed && 'is-filter-collapsed',
                    headerClassName,
                )}
            >
                <div className={mainRowLayout}>
                    <div className="ps-page-shell__title-col">
                        <h1 className="ps-page-shell__title">
                            {icon ? <span className="ps-page-shell__icon">{icon}</span> : null}
                            <span className="ps-page-shell__title-text">{title}</span>
                        </h1>
                        {subtitle ? <div className="ps-page-shell__subtitle">{subtitle}</div> : null}
                    </div>

                    {hasPrimaryFilters ? (
                        <div className="ps-page-shell__filters ps-page-shell__filters--primary">
                            {resolvedPrimaryFilters}
                        </div>
                    ) : null}

                    {hasActions ? (
                        <div className="ps-page-shell__actions">
                            {actions}
                            {collapsible && hasAdvancedFilters ? (
                                <button
                                    type="button"
                                    className="ps-page-shell__toggle"
                                    aria-expanded={!filtersCollapsed}
                                    title={filtersCollapsed ? 'Mở bộ lọc nâng cao' : 'Thu gọn bộ lọc nâng cao'}
                                    onClick={() => setFiltersCollapsed((value) => !value)}
                                >
                                    <i className={cn('fa', filtersCollapsed ? 'fa-angle-double-down' : 'fa-angle-double-up')} aria-hidden="true" />
                                </button>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {hasAdvancedFilters ? (
                    <div className="ps-page-shell__advanced-row" hidden={filtersCollapsed || undefined}>
                        {advancedFilters}
                    </div>
                ) : null}
            </header>

            {notice ? <div className="ps-page-shell__notice">{notice}</div> : null}

            {hasToolbar ? <div className="ps-page-shell__toolbar">{toolbar}</div> : null}

            <div className={cn('ps-page-shell__body', bodyClassName)}>
                {children}
            </div>
        </section>
    );
}

export default PushsalePageShell;
