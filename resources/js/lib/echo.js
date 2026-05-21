import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * @param {{ key: string, host: string, port: number, scheme: string }} reverb
 */
export function getEcho(reverb) {
    if (!reverb?.key) return null;

    if (echoInstance) {
        return echoInstance;
    }

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
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}
