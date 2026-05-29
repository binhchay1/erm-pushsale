import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { cn } from '@/lib/utils';

export default function NotificationsIndex({ tab, items, unreadCount }) {
    const setTab = (next) => {
        router.get('/notifications', { tab: next }, { preserveScroll: true, preserveState: true });
    };

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    const openItem = (n) => {
        if (!n.is_read) {
            router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true });
        }
        if (n.url) router.visit(n.url);
    };

    const tabs = [
        { key: 'all', label: 'Tất cả' },
        { key: 'unread', label: `Chưa đọc${unreadCount ? ` (${unreadCount})` : ''}` },
    ];

    return (
        <AppLayout>
            <Head title="Thông báo" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Thông báo</h1>
                    {unreadCount > 0 && (
                        <Button variant="outline" size="sm" onClick={markAllRead}>
                            <CheckCheck className="size-4" />
                            Đánh dấu tất cả đã đọc
                        </Button>
                    )}
                </div>

                <div className="flex gap-2">
                    {tabs.map((t) => (
                        <Button
                            key={t.key}
                            variant={tab === t.key ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setTab(t.key)}
                        >
                            {t.label}
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-0">
                        {items.length ? (
                            items.map((n) => (
                                <button
                                    key={n.id}
                                    type="button"
                                    onClick={() => openItem(n)}
                                    className={cn(
                                        'flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left last:border-0 hover:bg-muted/40',
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
                                        <span className="block font-medium">{n.title}</span>
                                        {n.message && (
                                            <span className="block text-sm text-muted-foreground">{n.message}</span>
                                        )}
                                        <span className="block text-xs text-muted-foreground">{n.created_at}</span>
                                    </span>
                                </button>
                            ))
                        ) : (
                            <div className="px-4 py-16 text-center text-muted-foreground">
                                <Bell className="mx-auto mb-2 size-6 opacity-50" />
                                Không có thông báo nào
                            </div>
                        )}
                    </CardContent>
                </Card>

                <p className="text-center text-xs text-muted-foreground">
                    <Link href="/settings" className="hover:underline">
                        Tùy chỉnh loại thông báo trong Cài đặt
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
