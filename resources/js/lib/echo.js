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
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    const token =
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? csrf;
                    window
                        .fetch('/broadcasting/auth', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                socket_id: socketId,
                                channel_name: channel.name,
                            }),
                        })
                        .then(async (response) => {
                            if (!response.ok) {
                                // Auth fail (403/419): dừng retry để khỏi spam console.
                                echoUnavailable = true;
                                const error = new Error(`Broadcast auth ${response.status}`);
                                callback(error, null);
                                return;
                            }
                            callback(null, await response.json());
                        })
                        .catch((error) => {
                            echoUnavailable = true;
                            callback(error, null);
                        });
                },
            }),
        });

        const connection = echoInstance.connector?.pusher?.connection;
        // Nuốt lỗi kết nối (host sai, Reverb chưa chạy, auth 403...) — không throw.
        connection?.bind('error', () => {
            echoUnavailable = true;
        });
        connection?.bind('unavailable', () => {
            echoUnavailable = true;
        });
        connection?.bind('failed', () => {
            echoUnavailable = true;
        });
        connection?.bind('state_change', (states) => {
            if (states?.current === 'failed' || states?.current === 'unavailable') {
                echoUnavailable = true;
            }
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
