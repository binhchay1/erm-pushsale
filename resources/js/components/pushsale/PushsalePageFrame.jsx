import { PushsalePageShell } from '@/components/layout/PushsalePageShell';

export function PushsalePageFrame({
    title,
    actions = null,
    filters = null,
    primaryFilters = null,
    advancedFilters = null,
    toolbar = null,
    children,
    className = '',
    ...props
}) {
    return (
        <PushsalePageShell
            title={title}
            filters={filters}
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            toolbar={toolbar}
            actions={actions}
            className={`pushsale-page-frame ${className}`.trim()}
            {...props}
        >
            {children}
        </PushsalePageShell>
    );
}
