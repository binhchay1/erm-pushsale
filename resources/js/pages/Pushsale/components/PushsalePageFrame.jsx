export function PushsalePageFrame({ title, actions = null, filters = null, children, className = '', ...props }) {
    return (
        <div className={`pushsale-page-frame ${className}`.trim()} {...props}>
            <div className="pushsale-page-titlebar">
                <div className="pushsale-page-title">{title}</div>
                {actions && <div className="pushsale-page-actions">{actions}</div>}
            </div>
            {filters && <div className="pushsale-page-filters">{filters}</div>}
            <div className="pushsale-page-content">{children}</div>
        </div>
    );
}
