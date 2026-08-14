import { router } from '@inertiajs/react';

const LEAD_EXCEPTION_VARIANTS = new Set(['duplicate_lead', 'late_upsell', 'orphan_upsell']);

function resolveNotificationUrl(notification) {
    const url = notification?.url ?? '';
    if (!url || notification?.type !== 'lead') {
        return url || null;
    }

    const variant = notification?.data?.variant;
    if (!LEAD_EXCEPTION_VARIANTS.has(variant)) {
        return url;
    }

    const [path] = url.split('?');
    if (path === '/admin/leads' || path === '/admin/leads/allocate') {
        return '/admin/leads/log?bucket=exceptions';
    }
    if (path === '/allocator/workspace' || path === '/allocator/leads') {
        return '/allocator/leads/log?bucket=exceptions';
    }

    return url;
}

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

        const target = resolveNotificationUrl(notification);
        if (target) {
            router.visit(target);
        }
    };

    return { markAllRead, openItem };
}
