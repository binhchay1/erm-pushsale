import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { apiPost } from '@/lib/api';
import { getEcho } from '@/lib/echo';

const POLL_MS = 15000;

/**
 * Live map of order interaction locks for the current company.
 * @param {{ actionApiBase: string, orderIds: Array<number|string> }} options
 * @returns {Record<string, {user_id:number,user_name:string,role_label?:string,role?:string}>}
 */
export function useOrderLockPresence({ actionApiBase, orderIds = [] }) {
    const { auth, reverb } = usePage().props;
    const companyId = auth?.user?.company?.id ?? auth?.user?.company_id ?? auth?.user?.companyId;
    const [locks, setLocks] = useState({});
    const idsKey = useMemo(
        () => orderIds.map((id) => String(id)).filter(Boolean).sort().join(','),
        [orderIds],
    );
    const idsRef = useRef(orderIds);
    idsRef.current = orderIds;

    useEffect(() => {
        if (!actionApiBase || !idsKey) {
            setLocks({});
            return undefined;
        }

        const base = String(actionApiBase).replace(/\/$/, '');
        let cancelled = false;

        const refresh = async () => {
            const ids = idsRef.current.map((id) => Number(id)).filter((id) => id > 0);
            if (!ids.length) {
                if (!cancelled) setLocks({});
                return;
            }
            try {
                const data = await apiPost(`${base}/interaction-locks`, { ids });
                if (!cancelled) setLocks(data.locks ?? {});
            } catch {
                // keep last known map
            }
        };

        refresh();
        const pollId = window.setInterval(refresh, POLL_MS);

        let channel = null;
        const echo = companyId && reverb?.key ? getEcho(reverb) : null;
        if (echo && companyId) {
            channel = echo.private(`company.${companyId}.order-locks`);
            channel.listen('.order.lock.acquired', (payload) => {
                const orderId = String(payload?.order_id ?? '');
                if (!orderId || !idsRef.current.map(String).includes(orderId)) return;
                setLocks((prev) => ({ ...prev, [orderId]: payload.holder }));
            });
            channel.listen('.order.lock.released', (payload) => {
                const orderId = String(payload?.order_id ?? '');
                if (!orderId) return;
                setLocks((prev) => {
                    if (!prev[orderId]) return prev;
                    const next = { ...prev };
                    delete next[orderId];
                    return next;
                });
            });
        }

        return () => {
            cancelled = true;
            window.clearInterval(pollId);
            if (channel && echo) {
                echo.leave(`company.${companyId}.order-locks`);
            }
        };
    }, [actionApiBase, idsKey, companyId, reverb?.key]);

    return locks;
}
