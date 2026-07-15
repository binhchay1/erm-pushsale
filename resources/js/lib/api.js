import { translate } from '@/i18n/translate';

export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function createApiError(response, data = {}) {
    const firstValidationError = Object.values(data.errors ?? {})
        .flat()
        .find((message) => typeof message === 'string' && message.trim() !== '');
    const error = new Error(firstValidationError ?? data.message ?? translate('common.request_failed'));

    error.status = response.status;
    error.errors = data.errors ?? {};
    error.payload = data;

    return error;
}


export async function apiRequest(url, { method = 'GET', body } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = getCsrfToken();
    }
    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw createApiError(response, data);
    return data;
}

export async function apiPost(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw createApiError(response, data);
    }

    return data;
}

export async function apiGet(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw createApiError(response, data);
    }

    return data;
}
