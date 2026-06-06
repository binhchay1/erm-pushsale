import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { getEcho } from '@/lib/echo';

function shouldShowToast(type, prefs) {
    if (prefs.desktop === false) {
        return false;
    }

    if (type === 'lead') {
        return prefs.new_lead !== false;
    }

    if (type === 'landing_approval' || type === 'landing_approved') {
        return prefs.landing_approval !== false;
    }

    return true;
}

/**
 * Lắng nghe thông báo real-time trên kênh user (Reverb) — dùng chung mọi vai trò.
 */
export function useRealtimeNotifications() {
    const { auth, reverb, preferences } = usePage().props;
    const prefs = preferences?.notifications ?? {};

    useEffect(() => {
        if (!auth?.user?.id || !reverb?.key) {
            return undefined;
        }

        const echo = getEcho(reverb);
        if (!echo) {
            return undefined;
        }

        const channelName = `App.Models.User.${auth.user.id}`;

        const channel = echo
            .private(channelName)
            .listen('.notification.created', (payload) => {
                if (shouldShowToast(payload.type, prefs)) {
                    toast.info(payload.title, {
                        description: payload.message ?? undefined,
                        duration: 6000,
                        action: payload.url
                            ? {
                                  label: 'Xem',
                                  onClick: () => router.visit(payload.url),
                              }
                            : undefined,
                    });
                }

                router.reload({
                    only: ['notifications', 'notificationsUnread'],
                    preserveScroll: true,
                    preserveState: true,
                });
            });

        return () => {
            channel.stopListening('.notification.created');
            echo.leave(channelName);
        };
    }, [auth?.user?.id, reverb?.key, prefs.desktop, prefs.new_lead, prefs.landing_approval]);
}
