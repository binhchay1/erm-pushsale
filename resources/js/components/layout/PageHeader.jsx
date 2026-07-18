import { PushsalePageChrome } from '@/components/layout/PushsalePageChrome';
import { cn } from '@/lib/utils';

export function PageHeader({ title, description, actions, icon: Icon, className }) {
    const renderedTitle = Icon ? (
        <span className="ps-page-header-title-with-icon">
            <Icon aria-hidden="true" />
            <span>{title}</span>
        </span>
    ) : title;

    return (
        <PushsalePageChrome
            title={renderedTitle}
            description={description}
            actions={actions}
            className={cn('pushsale-page-header', className)}
        />
    );
}
