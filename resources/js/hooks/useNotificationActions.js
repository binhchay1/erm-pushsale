import { router } from '@inertiajs/react';

export function useNotificationActions() {
    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
    };

    const openItem = (notification, { onNavigate } = {}) => {
        if (!notification.is_read) {
            router.post(`/notifications/${notification.id}/read`, {}, {
                preserveScroll: true,
                preserveState: true,
            });
        }

        onNavigate?.();

        if (notification.url) {
            router.visit(notification.url);
        }
    };

    return { markAllRead, openItem };
}
