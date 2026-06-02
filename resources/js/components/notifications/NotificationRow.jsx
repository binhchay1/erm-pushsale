import { cn } from '@/lib/utils';

export function NotificationRow({ notification, onClick, dense = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full border-b border-border text-left last:border-0',
                dense ? 'gap-2 px-3 py-2.5 hover:bg-muted/50' : 'items-start gap-3 px-4 py-3 hover:bg-muted/40',
                !notification.is_read && 'bg-primary/5',
            )}
        >
            <span
                className={cn(
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    notification.is_read ? 'bg-transparent' : 'bg-primary',
                )}
            />
            <span className="min-w-0 flex-1">
                <span className={cn('block font-medium', dense && 'truncate text-sm')}>
                    {notification.title}
                </span>
                {notification.message && (
                    <span className={cn('block text-muted-foreground', dense ? 'truncate text-xs' : 'text-sm')}>
                        {notification.message}
                    </span>
                )}
                <span className={cn('block text-muted-foreground', dense ? 'text-[11px]' : 'text-xs')}>
                    {notification.created_at}
                </span>
            </span>
        </button>
    );
}
