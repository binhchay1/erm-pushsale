import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

function firstMessage(value) {
    if (!value) return '';
    if (typeof value === 'string') return value;
    if (Array.isArray(value)) return value.filter(Boolean).join(' · ');
    if (typeof value === 'object') return Object.values(value).flat().filter(Boolean).join(' · ');
    return String(value);
}

/**
 * Global toast bridge for every Inertia response.
 * It displays backend flash.success / flash.error / flash.warning / flash.info and
 * validation bags without each page having to reimplement toast logic.
 */
export function useFlashToast() {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const errors = page.props?.errors ?? {};
    const lastRef = useRef('');

    useEffect(() => {
        const items = [
            ['success', firstMessage(flash.success || flash.status || flash.message)],
            ['error', firstMessage(flash.error)],
            ['warning', firstMessage(flash.warning)],
            ['info', firstMessage(flash.info)],
        ].filter(([, message]) => message);

        if (!items.length && Object.keys(errors).length > 0) {
            items.push(['error', firstMessage(errors) || 'Vui lòng kiểm tra lại dữ liệu.']);
        }

        items.forEach(([type, message]) => {
            const key = `${page.url}|${type}|${message}`;
            if (!message || key === lastRef.current) return;
            lastRef.current = key;
            window.requestAnimationFrame(() => {
                const fn = toast[type] ?? toast;
                fn(message, { duration: type === 'error' ? 7000 : 4500 });
            });
        });
    }, [page.url, flash.success, flash.status, flash.message, flash.error, flash.warning, flash.info, errors]);
}
