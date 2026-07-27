/**
 * Chuẩn hóa & kiểm tra số di động Việt Nam.
 * Đồng bộ logic với `App\Support\VietnamesePhone`.
 *
 * Chấp nhận: 9 số (912…), 10 số (0912…), +84 / 84 / 0084.
 */

export function phoneDigits(raw) {
    return String(raw ?? '').replace(/\D+/g, '');
}

/** Chuẩn hóa về dạng 0XXXXXXXXX (10 chữ số). Trả null nếu không hợp lệ. */
export function normalizeVietnamesePhone(raw) {
    let digits = phoneDigits(raw);
    if (!digits) return null;

    if (digits.startsWith('0084')) digits = digits.slice(4);
    else if (digits.startsWith('84')) digits = digits.slice(2);

    if (digits.length === 9 && /^[35789]/.test(digits)) {
        digits = `0${digits}`;
    } else if (digits.length !== 10 || !digits.startsWith('0')) {
        return null;
    }

    return /^0[35789]\d{8}$/.test(digits) ? digits : null;
}

export function isValidVietnamesePhone(raw) {
    return normalizeVietnamesePhone(raw) !== null;
}

/** Alias dùng chung với `validate.js`. */
export function isValidVnPhone(value) {
    return isValidVietnamesePhone(value);
}

/** Cho phép trống khi trường optional; có giá trị thì phải hợp lệ. */
export function vietnamesePhoneError(raw, { required = false } = {}) {
    const value = String(raw ?? '').trim();
    if (!value) {
        return required ? 'Số điện thoại bắt buộc.' : '';
    }
    if (!isValidVietnamesePhone(value)) {
        return 'Số điện thoại di động VN không hợp lệ. Dùng 9 số (912345678), 10 số (0912345678) hoặc +84.';
    }
    return '';
}
