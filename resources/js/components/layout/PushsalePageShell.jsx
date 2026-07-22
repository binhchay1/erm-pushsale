import { cn } from '@/lib/utils';

export function PushsalePageShell({
    title,
    subtitle,
    icon,
    filters = null,
    actions = null,
    children,
    className = '',
    headerClassName = '',
    bodyClassName = '',
    compact = false,
}) {
    return (
        <section className={cn('ps-page-shell', compact && 'is-compact', className)}>
            <header className={cn('ps-page-shell__header', headerClassName)}>
                <div className="ps-page-shell__inner">
                    <div className="ps-page-shell__title-col">
                        <h1 className="ps-page-shell__title">
                            {icon ? <span className="ps-page-shell__icon">{icon}</span> : null}
                            <span>{title}</span>
                        </h1>
                        {subtitle ? <div className="ps-page-shell__subtitle">{subtitle}</div> : null}
                    </div>
                    {(filters || actions) ? (
                        <div className="ps-page-shell__controls">
                            {filters ? <div className="ps-page-shell__filters">{filters}</div> : null}
                            {actions ? <div className="ps-page-shell__actions">{actions}</div> : null}
                        </div>
                    ) : null}
                </div>
            </header>
            <div className={cn('ps-page-shell__body', bodyClassName)}>
                {children}
            </div>
        </section>
    );
}

export default PushsalePageShell;
