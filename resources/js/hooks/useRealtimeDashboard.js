import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { disconnectEcho, getEcho } from '@/lib/echo';

/**
 * Listen for WebSocket dashboard stat updates.
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
                    // toast.info('Stats updated', {
                    //     description: 'Dashboard synced in real-time',
                    //     duration: 2800,
                    // });
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

    return { stats, connected, isReady };
}
