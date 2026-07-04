import { cn } from '@/lib/utils';

/**
 * Hiển thị lỗi validate của 1 trường form theo style chung (chữ đỏ, nhỏ).
 * Dùng cho cả lỗi client-side lẫn lỗi trả về từ server (Inertia `errors`).
 */
export function FieldError({ message, className }) {
    if (!message) {
        return null;
    }

    return <p className={cn('text-xs text-destructive', className)}>{message}</p>;
}

/** Dấu * đỏ đánh dấu trường bắt buộc. */
export function RequiredMark() {
    return <span className="text-destructive"> *</span>;
}
