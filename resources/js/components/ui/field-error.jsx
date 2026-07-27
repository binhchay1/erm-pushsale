import { cn } from '@/lib/utils';

/**
 * Hiển thị lỗi validate của 1 trường form theo style chung (chữ đỏ, nhỏ).
 * Dùng cho cả lỗi client-side lẫn lỗi trả về từ server (Inertia `errors`).
 */
function normalizeMessage(message) {
    if (!message) return '';
    if (typeof message === 'string') return message;
    if (Array.isArray(message)) return message.filter(Boolean).map(String).join(' ');
    if (typeof message === 'object') return String(message.message ?? message.error ?? '');
    return String(message);
}

export function FieldError({ message, className }) {
    const text = normalizeMessage(message);
    if (!text) {
        return null;
    }

    return <p className={cn('text-xs text-destructive', className)}>{text}</p>;
}

/** Dấu * đỏ đánh dấu trường bắt buộc. */
export function RequiredMark() {
    return <span className="text-destructive"> *</span>;
}
