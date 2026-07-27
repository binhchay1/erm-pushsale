import { translate as t } from '@/i18n/translate';
import { isValidVnPhone } from '@/lib/vietnamesePhone';

export { isValidVnPhone, normalizeVietnamesePhone, vietnamesePhoneError } from '@/lib/vietnamesePhone';

export function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '').trim());
}

function isBlank(value) {
    if (value === null || value === undefined) {
        return true;
    }
    if (Array.isArray(value)) {
        return value.length === 0;
    }
    return String(value).trim() === '';
}

/**
 * Chạy 1 bộ rule đơn giản phía client và trả về object {field: message}.
 *
 * rules ví dụ:
 * {
 *   phone: [{ required: true, label: 'SĐT' }, { phone: true }],
 *   name:  [{ required: true, label: 'Họ tên' }],
 *   quantity: [{ min: 1, number: true }],
 * }
 */
export function validate(values, rules) {
    const errors = {};

    for (const [field, checks] of Object.entries(rules)) {
        const value = values[field];

        for (const check of checks) {
            const label = check.label ?? field;

            if (check.required && isBlank(value)) {
                errors[field] = check.message ?? t('common.validation.required_field', { field: label });
                break;
            }

            if (check.selectRequired && isBlank(value)) {
                errors[field] = check.message ?? t('common.validation.select_required', { field: label });
                break;
            }

            // Các rule dưới đây chỉ chạy khi có giá trị (cho phép optional).
            if (isBlank(value)) {
                continue;
            }

            if (check.phone && !isValidVnPhone(value)) {
                errors[field] = check.message ?? t('common.validation.invalid_phone');
                break;
            }

            if (check.email && !isValidEmail(value)) {
                errors[field] = check.message ?? t('common.validation.invalid_email');
                break;
            }

            if (check.min !== undefined && String(value).trim().length < check.min) {
                errors[field] = check.message ?? t('common.validation.min_chars', { min: check.min });
                break;
            }

            if (check.max !== undefined && String(value).trim().length > check.max) {
                errors[field] = check.message ?? t('common.validation.max_chars', { max: check.max });
                break;
            }

            if (check.positive && !(Number(value) > 0)) {
                errors[field] = check.message ?? t('common.validation.positive_number');
                break;
            }

            if (check.nonNegative && !(Number(value) >= 0)) {
                errors[field] = check.message ?? t('common.validation.non_negative_number');
                break;
            }
        }
    }

    return errors;
}
