import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { cn } from '@/lib/utils';

/**
 * Backward-compatible adapter. New pages should use PushsalePageShell directly.
 * The rendered DOM now follows the shared two-part contract:
 * header(title + controls) + body(content).
 */
export function PushsalePageChrome({
    title,
    description,
    filters,
    actions,
    children,
    className,
    compact = false,
}) {
    return (
        <PushsalePageShell
            title={title}
            subtitle={description}
            filters={filters}
            actions={actions}
            compact={compact}
            className={cn('ps-page-chrome', className)}
        >
            {children}
        </PushsalePageShell>
    );
}
