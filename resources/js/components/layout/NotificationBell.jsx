import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import { NotificationRow } from '@/components/notifications/NotificationRow';
import { Button } from '@/components/ui/button';
import { useNotificationActions } from '@/hooks/useNotificationActions';
import { useT } from '@/providers/I18nProvider';

export function NotificationBell({ pushsaleStyle = false }) {
    const { notifications = [], notificationsUnread = 0 } = usePage().props;
    const t = useT();
    const [open, setOpen] = useState(false);
    const { markAllRead, openItem } = useNotificationActions();

    const triggerClass = pushsaleStyle
        ? 'nav-item-btn !w-full !h-full'
        : undefined;

    return (
        <div className={pushsaleStyle ? 'relative h-full' : 'relative'}>
            <Button
                type="button"
                variant={pushsaleStyle ? 'ghost' : 'ghost'}
                size={pushsaleStyle ? 'icon' : 'icon-sm'}
                className={triggerClass}
                onClick={() => setOpen((o) => !o)}
                title={t('notifications.title')}
                aria-label={t('notifications.title')}
            >
                <span className="relative inline-flex">
                    <Bell className={pushsaleStyle ? 'size-[18px]' : 'size-4'} strokeWidth={2} />
                    {notificationsUnread > 0 && (
                        <span
                            className={
                                pushsaleStyle
                                    ? 'label-warning'
                                    : 'absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[9px] font-bold text-white'
                            }
                        >
                            {notificationsUnread > 9 ? '9+' : notificationsUnread}
                        </span>
                    )}
                </span>
            </Button>

            {open && (
                <>
                    <button
                        type="button"
                        aria-hidden
                        className="fixed inset-0 z-40 cursor-default"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-border bg-background shadow-lg">
                        <div className="flex items-center justify-between border-b border-border px-3 py-2">
                            <p className="text-sm font-semibold">{t('notifications.title')}</p>
                            {notificationsUnread > 0 && (
                                <button
                                    type="button"
                                    onClick={markAllRead}
                                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    <CheckCheck className="size-3.5" /> {t('notifications.mark_all_read')}
                                </button>
                            )}
                        </div>

                        <div className="max-h-80 overflow-y-auto">
                            {notifications.length === 0 ? (
                                <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                                    {t('notifications.empty')}
                                </p>
                            ) : (
                                notifications.map((n) => (
                                    <NotificationRow
                                        key={n.id}
                                        notification={n}
                                        dense
                                        onClick={() => openItem(n, { onNavigate: () => setOpen(false) })}
                                    />
                                ))
                            )}
                        </div>

                        <Link
                            href="/notifications"
                            onClick={() => setOpen(false)}
                            className="block border-t border-border px-3 py-2 text-center text-sm font-medium text-primary hover:bg-muted/50"
                        >
                            {t('notifications.view_all')}
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}
