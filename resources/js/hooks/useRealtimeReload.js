import { useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';

import { getEcho } from '@/lib/echo';

/**
 * Reload a subset of Inertia props when a broadcast event fires.
 * Keeps server-side filtering & authorization authoritative (we never push rows
 * straight into the table) while still feeling real-time.
 *
 * @param {string} channelName  private channel to subscribe to
 * @param {string} eventName    broadcastAs name, prefixed with a dot (e.g. '.leads.changed')
 * @param {string[]} only       Inertia prop keys to reload
 * @param {{ debounce?: number, shouldReload?: (payload: any) => boolean }} [options]
 */
export function useRealtimeReload(channelName, eventName, only, options = {}) {
    const { auth, reverb } = usePage().props;
    const timer = useRef(null);
    const onlyRef = useRef(only);
    const optionsRef = useRef(options);

    onlyRef.current = only;
    optionsRef.current = options;

    useEffect(() => {
        if (!channelName || !auth?.user?.id || !reverb?.key) {
            return undefined;
        }

        const echo = getEcho(reverb);
        if (!echo) {
            return undefined;
        }

        const channel = echo.private(channelName).listen(eventName, (payload) => {
            const { shouldReload, debounce = 600 } = optionsRef.current;
            if (typeof shouldReload === 'function' && !shouldReload(payload)) {
                return;
            }

            if (timer.current) {
                clearTimeout(timer.current);
            }
            timer.current = setTimeout(() => {
                router.reload({ only: onlyRef.current, preserveScroll: true, preserveState: true });
            }, debounce);
        });

        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
            channel.stopListening(eventName);
            echo.leave(channelName);
        };
    }, [channelName, eventName, auth?.user?.id, reverb?.key]);
}
