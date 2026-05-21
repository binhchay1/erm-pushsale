import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { getEcho, disconnectEcho } from '@/lib/echo';

/**
 * Lắng nghe WebSocket cập nhật stats dashboard.
 * @param {string} channelRole - 'admin' | 'sales'
 * @param {object} initialStats
 * @param {(stats: object) => void} [onUpdate]
 */
export function useRealtimeDashboard(channelRole, initialStats, onUpdate) {
    const { auth, reverb, preferences } = usePage().props;
    const [stats, setStats] = useState(initialStats);
    const [connected, setConnected] = useState(false);
    const noti = preferences?.notifications ?? {};
    const onUpdateRef = useRef(onUpdate);

    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    useEffect(() => {
        setStats(initialStats);
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
                setStats(next);
                onUpdateRef.current?.(next);

                if (noti.desktop) {
                    toast.info('Số liệu vừa cập nhật', {
                        description: 'Dashboard đồng bộ real-time',
                        duration: 2800,
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
            echo.leave(channelName);
        };
    }, [auth?.user?.id, channelRole, reverb?.key, noti.desktop]);

    useEffect(() => () => disconnectEcho(), []);

    return { stats, connected };
}
