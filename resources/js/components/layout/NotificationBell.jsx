import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, Settings } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function NotificationBell() {
    const { notifications = [], notificationsUnread = 0 } = usePage().props;
    const [open, setOpen] = useState(false);

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
    };

    const openItem = (n) => {
        if (!n.is_read) {
            router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true });
        }
        setOpen(false);
        if (n.url) router.visit(n.url);
    };

    return (
        <div className="relative">
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={() => setOpen((o) => !o)}
                title="Thông báo"
                aria-label="Thông báo"
            >
                <span className="relative inline-flex">
                    <Bell className="size-4" />
                    {notificationsUnread > 0 && (
                        <span className="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[9px] font-bold text-white">
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
                            <p className="text-sm font-semibold">Thông báo</p>
                            {notificationsUnread > 0 && (
                                <button
                                    type="button"
                                    onClick={markAllRead}
                                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    <CheckCheck className="size-3.5" /> Đọc tất cả
                                </button>
                            )}
                        </div>

                        <div className="max-h-80 overflow-y-auto">
                            {notifications.length === 0 ? (
                                <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                                    Chưa có thông báo
                                </p>
                            ) : (
                                notifications.map((n) => (
                                    <button
                                        key={n.id}
                                        type="button"
                                        onClick={() => openItem(n)}
                                        className={cn(
                                            'flex w-full gap-2 border-b border-border px-3 py-2.5 text-left last:border-0 hover:bg-muted/50',
                                            !n.is_read && 'bg-primary/5'
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                n.is_read ? 'bg-transparent' : 'bg-primary'
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium">{n.title}</span>
                                            {n.message && (
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {n.message}
                                                </span>
                                            )}
                                            <span className="block text-[11px] text-muted-foreground">
                                                {n.created_at}
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>

                        <Link
                            href="/notifications"
                            onClick={() => setOpen(false)}
                            className="block border-t border-border px-3 py-2 text-center text-sm font-medium text-primary hover:bg-muted/50"
                        >
                            Xem tất cả
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}

export function SettingsLink() {
    return (
        <Button variant="ghost" size="sm" asChild>
            <Link href="/settings">
                <Settings className="size-4" />
                <span className="hidden md:inline">Cài đặt</span>
            </Link>
        </Button>
    );
}
