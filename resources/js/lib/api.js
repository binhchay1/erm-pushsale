import { translate } from '@/i18n/translate';
import { toast } from 'sonner';

export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Map HTTP lỗi → message cho user.
 * 4xx (validate/quyền/khóa đơn): hiện message nghiệp vụ.
 * 5xx: không nói "lỗi 500" — message trung tính; gốc phải sửa ở backend.
 */
function createApiError(response, data = {}) {
    const firstValidationError = Object.values(data.errors ?? {})
        .flat()
        .find((message) => typeof message === 'string' && message.trim() !== '');
    const rawMessage = firstValidationError ?? data.message ?? '';
    const looksLikeHtml = typeof rawMessage === 'string' && (/^\s*</.test(rawMessage) || /<!DOCTYPE|<html|<body/i.test(rawMessage));

    const statusFallback = ({
        401: translate('common.session_expired'),
        403: translate('common.forbidden'),
        404: translate('common.not_found'),
        419: translate('common.session_expired_reload'),
        422: translate('common.validation_failed'),
        423: translate('common.order_locked'),
    })[response.status];

    let message;
    if (response.status >= 500 || looksLikeHtml) {
        message = translate('common.action_incomplete');
    } else if (String(rawMessage).trim()) {
        message = String(rawMessage);
    } else {
        message = statusFallback ?? translate('common.request_failed');
    }

    const error = new Error(message);
    error.status = response.status;
    error.errors = data.errors ?? {};
    error.payload = data;
    error.isUserError = response.status >= 400 && response.status < 500 && !looksLikeHtml;

    return error;
}

/** Toast chỉ khi có lỗi nghiệp vụ/validate; 5xx dùng message trung tính (không nhắc 500). */
export function toastApiError(error, fallbackMessage) {
    const msg = error?.message || fallbackMessage || translate('common.request_failed');
    toast.error(msg);
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
