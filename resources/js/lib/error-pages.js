/**
 * Nội dung hiển thị theo mã HTTP — dùng chung cho trang lỗi server và ErrorBoundary.
 */
export const ERROR_PAGES = {
    401: {
        title: 'Cần đăng nhập',
        description:
            'Phiên làm việc đã hết hạn hoặc bạn chưa đăng nhập. Vui lòng đăng nhập lại để tiếp tục.',
        tone: 'warning',
    },
    403: {
        title: 'Không có quyền truy cập',
        description:
            'Tài khoản của bạn không được phép xem trang hoặc thực hiện thao tác này. Liên hệ quản trị nếu bạn cho rằng đây là nhầm lẫn.',
        tone: 'warning',
    },
    404: {
        title: 'Không tìm thấy trang',
        description:
            'Đường dẫn không tồn tại, đã bị xóa hoặc bạn không có quyền truy cập. Kiểm tra lại URL hoặc quay về trang chủ.',
        tone: 'muted',
    },
    419: {
        title: 'Phiên đã hết hạn',
        description:
            'Trang đã quá lâu không tương tác hoặc phiên bảo mật hết hạn. Tải lại trang và thử lại thao tác vừa rồi.',
        tone: 'warning',
    },
    429: {
        title: 'Quá nhiều yêu cầu',
        description:
            'Hệ thống tạm thời giới hạn số lần thử trong thời gian ngắn. Đợi vài giây rồi thử lại.',
        tone: 'warning',
    },
    500: {
        title: 'Lỗi hệ thống',
        description:
            'Đã xảy ra sự cố phía máy chủ. Đội kỹ thuật đã được thông báo — bạn có thể thử tải lại hoặc quay về trang chủ.',
        tone: 'danger',
    },
    503: {
        title: 'Hệ thống đang bảo trì',
        description:
            'SaleOps đang được nâng cấp hoặc bảo trì ngắn. Vui lòng quay lại sau vài phút.',
        tone: 'muted',
    },
    client: {
        title: 'Lỗi giao diện',
        description:
            'Trang không hiển thị được do lỗi JavaScript. Thử tải lại; nếu vẫn lỗi, mở DevTools (F12) → Console và báo cho quản trị.',
        tone: 'danger',
    },
};

export function getErrorMeta(status) {
    return ERROR_PAGES[status] ?? ERROR_PAGES[500];
}
