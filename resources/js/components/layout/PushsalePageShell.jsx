import { PageHeader } from '@/components/layout/PageHeader';
import { cn } from '@/lib/utils';

/**
 * Shared Pushsale page frame used by every menu page.
 *
 * Header (title | primaryFilters | actions | advancedFilters) được đẩy qua
 * PageHeader nên nó render ở outlet của AppLayout, dùng chung DOM/CSS
 * `.m-header-wrap > .m-header` với các trang template.
 *
 * Phần còn lại giữ nguyên tại chỗ: notice → toolbar → body.
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
    pageCode = undefined,
    ...props
}) {
    const hasToolbar = Boolean(toolbar);

    return (
        <section className={cn('ps-page-shell', compact && 'is-compact', className)} {...props}>
            <PageHeader
                title={title}
                subtitle={subtitle}
                icon={icon}
                primaryFilters={primaryFilters ?? filters}
                actions={actions}
                advanced={advancedFilters}
                pageCode={pageCode}
                className={headerClassName}
                defaultCollapsed={defaultFiltersCollapsed}
                collapsible={collapsible}
            />

            {notice ? <div className="ps-page-shell__notice">{notice}</div> : null}

            {hasToolbar ? <div className="ps-page-shell__toolbar">{toolbar}</div> : null}

            <div className={cn('ps-page-shell__body', bodyClassName)}>
                {children}
            </div>
        </section>
    );
}

export default PushsalePageShell;
