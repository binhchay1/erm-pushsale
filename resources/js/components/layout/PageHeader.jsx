import { cn } from '@/lib/utils';

export function PageHeader({ title, description, actions, icon: Icon, className }) {
    return (
        <div className={cn('flex flex-wrap items-start justify-between gap-3', className)}>
            <div className="flex items-start gap-3">
                {Icon && <Icon className="mt-1 size-6 shrink-0 text-primary" />}
                <div className="max-w-2xl">
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    {description && (
                        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                    )}
                </div>
            </div>
            {actions}
        </div>
    );
}
