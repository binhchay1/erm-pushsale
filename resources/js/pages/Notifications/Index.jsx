import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { NotificationRow } from '@/components/notifications/NotificationRow';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useNotificationActions } from '@/hooks/useNotificationActions';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

export default function NotificationsIndex({ tab, items, unreadCount }) {
    const t = useT();
    const { markAllRead, openItem } = useNotificationActions();

    const setTab = (next) => {
        router.get('/notifications', { tab: next }, { preserveScroll: true, preserveState: true });
    };

    const tabs = [
        { key: 'all', label: t('notifications.tab_all') },
        {
            key: 'unread',
            label: `${t('notifications.tab_unread')}${unreadCount ? ` (${unreadCount})` : ''}`,
        },
    ];

    return (
        <AppLayout>
            <Head title={t('notifications.title')} />

            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={t('notifications.title')}
                    actions={
                        unreadCount > 0 && (
                            <Button variant="outline" size="sm" onClick={markAllRead}>
                                <CheckCheck className="size-4" />
                                {t('notifications.mark_all_read')}
                            </Button>
                        )
                    }
                />

                <div className="flex gap-2">
                    {tabs.map((tabItem) => (
                        <Button
                            key={tabItem.key}
                            variant={tab === tabItem.key ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setTab(tabItem.key)}
                        >
                            {tabItem.label}
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
                                {t('notifications.empty_page')}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <p className="text-center text-xs text-muted-foreground">
                    <Link href="/settings" className="hover:underline">
                        {t('notifications.settings_link')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
