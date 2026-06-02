import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { NotificationRow } from '@/components/notifications/NotificationRow';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useNotificationActions } from '@/hooks/useNotificationActions';
import AppLayout from '@/layouts/AppLayout';

export default function NotificationsIndex({ tab, items, unreadCount }) {
    const { markAllRead, openItem } = useNotificationActions();

    const setTab = (next) => {
        router.get('/notifications', { tab: next }, { preserveScroll: true, preserveState: true });
    };

    const tabs = [
        { key: 'all', label: 'Tất cả' },
        { key: 'unread', label: `Chưa đọc${unreadCount ? ` (${unreadCount})` : ''}` },
    ];

    return (
        <AppLayout>
            <Head title="Thông báo" />

            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="Thông báo"
                    actions={
                        unreadCount > 0 && (
                            <Button variant="outline" size="sm" onClick={markAllRead}>
                                <CheckCheck className="size-4" />
                                Đánh dấu tất cả đã đọc
                            </Button>
                        )
                    }
                />

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
                                <NotificationRow
                                    key={n.id}
                                    notification={n}
                                    onClick={() => openItem(n)}
                                />
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
