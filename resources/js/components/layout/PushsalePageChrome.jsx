import { cn } from '@/lib/utils';

/**
 * One shared Pushsale page chrome for every admin/report page.
 *
 * Layout rule:
 * - left: page title only
 * - middle/right: filters and actions
 * - help/info lives in the global header, not inside each page body
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
        <div className={cn('ps-page-chrome', compact && 'is-compact', className)}>
            <div className="ps-page-chrome__primary">
                <div className="ps-page-chrome__title">
                    <h1>{title}</h1>
                    {description && <p>{description}</p>}
                </div>
                {(filters || actions) && (
                    <div className="ps-page-chrome__controls">
                        {filters && <div className="ps-page-chrome__filters">{filters}</div>}
                        {actions && <div className="ps-page-chrome__actions">{actions}</div>}
                    </div>
                )}
            </div>
            {children && <div className="ps-page-chrome__secondary">{children}</div>}
        </div>
    );
}
