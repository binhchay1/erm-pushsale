import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

/**
 * Tự reload khi cửa sổ "chờ upsale" hết hạn — dự phòng nếu WebSocket trễ/mất.
 * Khi upsale gia hạn hold, `landingUpsellHoldUntil` đổi → timer được lên lịch lại.
 */
export function useLandingUpsellHoldRefresh(rows, only = ['report']) {
    const onlyRef = useRef(only);
    onlyRef.current = only;

    useEffect(() => {
        if (!rows?.length) {
            return undefined;
        }

        const deadlines = rows
            .filter((row) => row.awaitingLandingUpsell && row.landingUpsellHoldUntil)
            .map((row) => new Date(row.landingUpsellHoldUntil).getTime())
            .filter((time) => !Number.isNaN(time) && time > Date.now());

        if (!deadlines.length) {
            return undefined;
        }

        const nextDeadline = Math.min(...deadlines);
        // Buffer sau mốc hold để job queue kịp chạy & broadcast.
        const delayMs = Math.max(500, nextDeadline - Date.now() + 2000);

        const timer = setTimeout(() => {
            router.reload({
                only: onlyRef.current,
                preserveScroll: true,
                preserveState: true,
            });
        }, delayMs);

        return () => clearTimeout(timer);
    }, [rows]);
}
