import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { cn } from '@/lib/utils';

export function PageHeader({ title, description, actions, filters, icon: Icon, className, children, compact = true }) {
    const renderedIcon = Icon ? <Icon aria-hidden="true" /> : null;

    return (
        <PushsalePageShell
            title={title}
            subtitle={description}
            icon={renderedIcon}
            filters={filters ?? children}
            actions={actions}
            compact={compact}
            className={cn('pushsale-page-header ps-page-header-adapter', className)}
            bodyClassName="hidden"
        />
    );
}
