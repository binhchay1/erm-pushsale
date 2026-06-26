import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;
// Khi đã xác định không kết nối được, ngừng thử lại để tránh spam lỗi console.
let echoUnavailable = false;

/**
 * Realtime là tính năng tăng cường — KHÔNG bắt buộc. Nếu Reverb không cấu hình
 * đúng / không chạy, app vẫn hoạt động bình thường, chỉ là không có cập nhật
 * tức thời. Mọi lỗi kết nối được nuốt êm để không phá trải nghiệm.
 *
 * @param {{ key: string, host: string, port: number, scheme: string }} reverb
 */
export function getEcho(reverb) {
    if (!reverb?.key || !reverb?.host || echoUnavailable) return null;

    if (echoInstance) {
        return echoInstance;
    }

    try {
        const forceTLS = reverb.scheme === 'https';
        const port = reverb.port || (forceTLS ? 443 : 8080);
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: reverb.key,
            wsHost: reverb.host,
            wsPort: port,
            wssPort: port,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            // Giảm bão reconnect khi server không reachable.
            activityTimeout: 30000,
            pongTimeout: 10000,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        const connection = echoInstance.connector?.pusher?.connection;
        // Nuốt lỗi kết nối (host sai, Reverb chưa chạy, auth 403...) — không throw.
        connection?.bind('error', () => {});
        connection?.bind('unavailable', () => {});
        connection?.bind('failed', () => {
            echoUnavailable = true;
        });
    } catch {
        echoUnavailable = true;
        echoInstance = null;
    }

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        try {
            echoInstance.disconnect();
        } catch {
            // ignore
        }
        echoInstance = null;
    }
}
