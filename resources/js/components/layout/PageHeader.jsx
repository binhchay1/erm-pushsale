import { cn } from '@/lib/utils';

export function PageHeader({ title, description, actions, icon: Icon, className }) {
    return (
        <div className={cn('pushsale-page-header', className)}>
            <div className="pushsale-page-header__main">
                {Icon && <Icon aria-hidden="true" />}
                <div>
                    <h1>{title}</h1>
                    {description && <p>{description}</p>}
                </div>
            </div>
            {actions && <div className="pushsale-page-header__actions">{actions}</div>}
        </div>
    );
}
