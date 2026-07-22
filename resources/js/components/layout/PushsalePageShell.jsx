import { useState } from 'react';

import { cn } from '@/lib/utils';

export function PushsalePageShell({
    title,
    subtitle,
    icon,
    filters = null,
    primaryFilters = null,
    advancedFilters = null,
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
    const showActions = Boolean(actions) || (collapsible && hasAdvancedFilters);

    return (
        <section className={cn('ps-page-shell', compact && 'is-compact', className)} {...props}>
            <header
                className={cn(
                    'ps-page-shell__header',
                    hasAdvancedFilters && 'has-advanced-filters',
                    filtersCollapsed && 'is-filter-collapsed',
                    headerClassName,
                )}
            >
                <div className="ps-page-shell__main-row">
                    <div className="ps-page-shell__title-col">
                        <h1 className="ps-page-shell__title">
                            {icon ? <span className="ps-page-shell__icon">{icon}</span> : null}
                            <span>{title}</span>
                        </h1>
                        {subtitle ? <div className="ps-page-shell__subtitle">{subtitle}</div> : null}
                    </div>

                    {hasPrimaryFilters ? (
                        <div className="ps-page-shell__filters ps-page-shell__filters--primary">
                            {resolvedPrimaryFilters}
                        </div>
                    ) : <div className="ps-page-shell__filters-spacer" />}

                    {showActions ? (
                        <div className="ps-page-shell__actions">
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
                            {actions}
                        </div>
                    ) : null}
                </div>

                {hasAdvancedFilters ? (
                    <div className="ps-page-shell__advanced-row" hidden={filtersCollapsed}>
                        {advancedFilters}
                    </div>
                ) : null}
            </header>

            {notice ? <div className="ps-page-shell__notice">{notice}</div> : null}

            <div className={cn('ps-page-shell__body', bodyClassName)}>
                {children}
            </div>
        </section>
    );
}

export default PushsalePageShell;
