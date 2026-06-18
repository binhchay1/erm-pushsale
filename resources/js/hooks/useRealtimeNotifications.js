import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { getEcho } from '@/lib/echo';
import { getNotificationText } from '@/lib/notification-text';
import { useT } from '@/providers/I18nProvider';

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
 * Listen for real-time notifications on the user channel (Reverb) — shared across roles.
 */
export function useRealtimeNotifications() {
    const t = useT();
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
                    const { title, message } = getNotificationText(payload, t);

                    toast.info(title, {
                        description: message || undefined,
                        duration: 6000,
                        action: payload.url
                            ? {
                                  label: t('notifications.view'),
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
    }, [auth?.user?.id, reverb?.key, prefs.desktop, prefs.new_lead, prefs.landing_approval, t]);
}
