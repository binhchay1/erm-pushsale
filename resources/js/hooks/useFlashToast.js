import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

/**
 * Toast tổng: tự hiển thị flash.success / flash.error từ server cho mọi tác vụ
 * (thêm/sửa/xóa, lưu cấu hình...). Dùng ref để không toast lặp khi re-render.
 */
export function useFlashToast() {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const lastRef = useRef({ success: null, error: null });

    useEffect(() => {
        if (flash.success && flash.success !== lastRef.current.success) {
            toast.success(flash.success);
            lastRef.current.success = flash.success;
        }
        if (flash.error && flash.error !== lastRef.current.error) {
            toast.error(flash.error);
            lastRef.current.error = flash.error;
        }
    }, [flash.success, flash.error]);
}
