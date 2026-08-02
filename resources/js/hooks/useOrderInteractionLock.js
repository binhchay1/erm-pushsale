import { useEffect, useRef, useState } from 'react';

import { apiPost, apiRequest } from '@/lib/api';

const HEARTBEAT_MS = 25000;

/**
 * Soft-lease lock while a mutate dialog is open.
 * @param {{ orderId?: number|string|null, actionApiBase: string, action?: string, enabled?: boolean }} options
 */
export function useOrderInteractionLock({ orderId, actionApiBase, action = 'dialog', enabled = true }) {
    const [token, setToken] = useState(null);
    const [holder, setHolder] = useState(null);
    const [error, setError] = useState('');
    const [ready, setReady] = useState(false);
    const tokenRef = useRef(null);

    useEffect(() => {
        tokenRef.current = token;
    }, [token]);

    useEffect(() => {
        if (!enabled || !orderId || !actionApiBase) {
            setToken(null);
            setHolder(null);
            setError('');
            setReady(false);
            return undefined;
        }

        let cancelled = false;
        let heartbeatId = null;
        const base = String(actionApiBase).replace(/\/$/, '');
        const lockUrl = `${base}/${orderId}/interaction-lock`;

        (async () => {
            try {
                const data = await apiPost(lockUrl, { action });
                if (cancelled) {
                    await apiRequest(lockUrl, { method: 'DELETE', body: { token: data.token } }).catch(() => {});
                    return;
                }
                setToken(data.token);
                setHolder(data.holder ?? null);
                setError('');
                setReady(true);
                heartbeatId = window.setInterval(() => {
                    const current = tokenRef.current;
                    if (!current) return;
                    apiPost(`${lockUrl}/heartbeat`, { token: current }).catch(() => {});
                }, HEARTBEAT_MS);
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || 'Không thể khóa đơn để thao tác.');
                    setReady(false);
                    setToken(null);
                }
            }
        })();

        return () => {
            cancelled = true;
            if (heartbeatId) window.clearInterval(heartbeatId);
            const current = tokenRef.current;
            if (current) {
                apiRequest(lockUrl, { method: 'DELETE', body: { token: current } }).catch(() => {});
            }
            setToken(null);
            setReady(false);
        };
    }, [enabled, orderId, actionApiBase, action]);

    return { token, holder, error, ready };
}
