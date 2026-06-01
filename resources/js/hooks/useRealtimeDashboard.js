import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { disconnectEcho, getEcho } from '@/lib/echo';

/**
 * Lắng nghe WebSocket cập nhật stats dashboard.
 * @param {string} channelRole - 'admin' | 'sales'
 * @param {object} initialStats
 * @param {(stats: object) => void} [onUpdate]
 */
function hasLoadedStats(value) {
    return Boolean(value?.updated_at);
}

export function useRealtimeDashboard(channelRole, initialStats, onUpdate) {
    const { auth, reverb, preferences } = usePage().props;
    const [stats, setStats] = useState(() => (hasLoadedStats(initialStats) ? initialStats : null));
    const [isReady, setIsReady] = useState(() => hasLoadedStats(initialStats));
    const [connected, setConnected] = useState(false);
    const noti = preferences?.notifications ?? {};
    const onUpdateRef = useRef(onUpdate);

    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    useEffect(() => {
        if (hasLoadedStats(initialStats)) {
            setStats(initialStats);
            setIsReady(true);
            return;
        }

        setStats(null);
        setIsReady(false);
    }, [initialStats]);

    useEffect(() => {
        if (!auth?.user || !reverb?.key) return;

        const echo = getEcho(reverb);
        if (!echo) return;

        const channelName = `dashboard.${channelRole}`;

        const channel = echo
            .private(channelName)
            .listen('.stats.updated', (payload) => {
                const next = payload?.stats ?? payload;
                if (!hasLoadedStats(next)) {
                    return;
                }

                setStats(next);
                setIsReady(true);
                onUpdateRef.current?.(next);

                if (noti.desktop) {
                    // toast.info('Số liệu vừa cập nhật', {
                    //     description: 'Dashboard đồng bộ real-time',
                    //     duration: 2800,
                    // });
                }
            })
            .listen('.lead.ingested', (payload) => {
                if (noti.new_lead !== false) {
                    toast.success('Lead mới', {
                        description: `${payload.platform ?? 'Nguồn'} · ${payload.customer_phone ?? ''}`,
                        duration: 5000,
                    });
                }
            });

        echo.connector.pusher.connection.bind('connected', () => setConnected(true));
        echo.connector.pusher.connection.bind('disconnected', () => setConnected(false));
        echo.connector.pusher.connection.bind('unavailable', () => setConnected(false));

        if (echo.connector.pusher.connection.state === 'connected') {
            setConnected(true);
        }

        return () => {
            channel.stopListening('.stats.updated');
            channel.stopListening('.lead.ingested');
            echo.leave(channelName);
        };
    }, [auth?.user?.id, channelRole, reverb?.key, noti.desktop, noti.new_lead]);

    useEffect(() => () => disconnectEcho(), []);

    return { stats, connected, isReady };
}
