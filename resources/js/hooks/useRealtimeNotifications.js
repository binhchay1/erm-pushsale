import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

import { getEcho } from '@/lib/echo';
import { getNotificationText } from '@/lib/notification-text';
import { useI18n } from '@/providers/I18nProvider';

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
    const { t, locale } = useI18n();
    const { auth, reverb, preferences } = usePage().props;
    const prefs = preferences?.notifications ?? {};
    const reloadTimer = useRef(null);

    useEffect(() => {
        if (!auth?.user?.id || !reverb?.key) {
            return undefined;
        }

        const echo = getEcho(reverb);
        if (!echo) {
            return undefined;
        }

        const channelName = `App.Models.User.${auth.user.id}`;
        const scheduleNotificationReload = () => {
            if (reloadTimer.current) {
                clearTimeout(reloadTimer.current);
            }

            // A test flow can create many notifications in seconds. Reloading Inertia
            // props for every single toast makes dashboards flash/repaint nonstop.
            // Debounce the bell count refresh; the toast itself still appears instantly.
            reloadTimer.current = setTimeout(() => {
                router.reload({
                    only: ['notifications', 'notificationsUnread'],
                    preserveScroll: true,
                    preserveState: true,
                });
            }, 2500);
        };

        const channel = echo
            .private(channelName)
            .listen('.notification.created', (payload) => {
                if (shouldShowToast(payload.type, prefs)) {
                    const { title, message } = getNotificationText(payload, t, locale);

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

                scheduleNotificationReload();
            });

        return () => {
            if (reloadTimer.current) {
                clearTimeout(reloadTimer.current);
                reloadTimer.current = null;
            }
            channel.stopListening('.notification.created');
            echo.leave(channelName);
        };
    }, [auth?.user?.id, reverb?.key, prefs.desktop, prefs.new_lead, prefs.landing_approval, t, locale]);
}
