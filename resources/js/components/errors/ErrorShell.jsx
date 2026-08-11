import { Link } from '@inertiajs/react';
import { ArrowLeft, Home, LayoutDashboard } from 'lucide-react';

import DonutErrorIllustration from '@/components/errors/DonutErrorIllustration';
import { Button } from '@/components/ui/button';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';

const PANEL_TONE = {
    401: 'from-amber-500/10 via-transparent to-transparent border-amber-200/80',
    403: 'from-orange-500/10 via-transparent to-transparent border-orange-200/80',
    404: 'from-rose-500/10 via-transparent to-transparent border-rose-200/80',
    419: 'from-violet-500/10 via-transparent to-transparent border-violet-200/80',
    429: 'from-sky-500/10 via-transparent to-transparent border-sky-200/80',
    500: 'from-amber-500/10 via-transparent to-transparent border-amber-200/80',
    503: 'from-blue-500/10 via-transparent to-transparent border-blue-200/80',
    client: 'from-red-500/10 via-transparent to-transparent border-red-200/80',
};

const COPY = {
    401: {
        viTitle: 'Bạn cần đăng nhập lại',
        enTitle: 'Please sign in again',
        viDesc: 'Phiên làm việc đã hết hạn hoặc bạn chưa đăng nhập. Vui lòng đăng nhập lại để tiếp tục sử dụng hệ thống.',
        enDesc: 'Your session has expired or you are not signed in. Please sign in again to continue using the system.',
        viHint: 'Nếu bạn vừa thao tác xong rồi bị chuyển sang đây, phiên đăng nhập có thể đã hết hạn.',
        enHint: 'If you were redirected here right after an action, your session may have expired.',
    },
    403: {
        viTitle: 'Bạn không có quyền truy cập trang này',
        enTitle: 'You do not have permission to access this page',
        viDesc: 'Tài khoản hiện tại không được cấp quyền cho nội dung hoặc thao tác này. Vui lòng liên hệ quản trị viên nếu bạn nghĩ đây là nhầm lẫn.',
        enDesc: 'Your current account is not permitted to access this page or action. Please contact the administrator if you think this is a mistake.',
        viHint: 'Kiểm tra lại vai trò hoặc quyền của tài khoản trong hệ thống.',
        enHint: 'Please review the user role or permission assigned in the system.',
    },
    404: {
        viTitle: 'Trang bạn tìm đang bị thất lạc',
        enTitle: 'The page you are looking for is missing',
        viDesc: 'Đường dẫn có thể đã thay đổi, đã bị xóa hoặc hiện không còn khả dụng. Hãy kiểm tra lại URL hoặc quay về trang chính.',
        enDesc: 'The URL may have changed, been removed, or is no longer available. Please check the address or go back to the main page.',
        viHint: 'Nếu bạn vào từ menu hệ thống thì có thể route này chưa được cấu hình đúng.',
        enHint: 'If you opened this from the system menu, the route may not be configured correctly yet.',
    },
    419: {
        viTitle: 'Phiên bảo mật đã hết hạn',
        enTitle: 'Your security session has expired',
        viDesc: 'Trang đang mở quá lâu hoặc phiên bảo mật đã hết hạn. Vui lòng tải lại trang rồi thực hiện lại thao tác.',
        enDesc: 'The page stayed open too long or the security session expired. Please reload the page and try your action again.',
        viHint: 'Đây thường là lỗi tạm thời và có thể xử lý bằng cách tải lại trang.',
        enHint: 'This is usually temporary and can be fixed by reloading the page.',
    },
    429: {
        viTitle: 'Bạn đang thao tác quá nhanh',
        enTitle: 'You are sending requests too quickly',
        viDesc: 'Hệ thống đang tạm giới hạn số lần thử để bảo vệ dịch vụ. Vui lòng đợi một lúc rồi thử lại.',
        enDesc: 'The system is temporarily rate limiting requests to protect the service. Please wait a moment and try again.',
        viHint: 'Nếu đang import hoặc đồng bộ dữ liệu, hãy thử lại sau ít phút.',
        enHint: 'If you are importing or syncing data, please retry after a short wait.',
    },
    500: {
        viTitle: 'Trang hiện đang gặp trục trặc',
        enTitle: 'This page is currently having a problem',
        viDesc: 'Hệ thống vừa gặp lỗi trong quá trình xử lý. Vui lòng thử tải lại trang. Nếu lỗi vẫn còn, hãy liên hệ ban quản trị để được hỗ trợ.',
        enDesc: 'The system encountered an issue while processing your request. Please reload the page. If the issue persists, contact the administrator for assistance.',
        viHint: 'Bạn không cần gửi mã log. Chỉ cần báo lại trang đang thao tác và thời điểm lỗi xảy ra.',
        enHint: 'You do not need to send raw logs. Just report the page and the time when the issue occurred.',
    },
    503: {
        viTitle: 'Hệ thống đang được bảo trì',
        enTitle: 'The system is under maintenance',
        viDesc: 'Chúng tôi đang nâng cấp hoặc bảo trì hệ thống trong thời gian ngắn. Vui lòng quay lại sau ít phút.',
        enDesc: 'We are upgrading or performing brief maintenance on the system. Please come back in a few minutes.',
        viHint: 'Nếu đây là thời điểm làm việc cao điểm, hãy liên hệ ban quản trị để kiểm tra lịch bảo trì.',
        enHint: 'If this occurs during working hours, please contact the administrator to verify the maintenance window.',
    },
    client: {
        viTitle: 'Trang chưa hiển thị được',
        enTitle: 'The page could not be rendered',
        viDesc: 'Ứng dụng vừa gặp lỗi hiển thị ở trình duyệt. Vui lòng tải lại trang hoặc quay lại màn hình trước đó.',
        enDesc: 'The application encountered a browser-side rendering error. Please reload the page or return to the previous screen.',
        viHint: 'Nếu lỗi lặp lại, hãy liên hệ ban quản trị và mô tả thao tác vừa thực hiện.',
        enHint: 'If the issue keeps happening, contact the administrator and describe the action you were taking.',
    },
};

function copyFor(status) {
    return COPY[status] ?? COPY[500];
}

/**
 * Error display shell — shared by HTTP error pages and ErrorBoundary.
 */
export function ErrorShell({
    status = 500,
    title,
    description,
    message,
    brandName = 'ERM SaleOps',
    brandTagline,
    homeUrl = '/login',
    showLogin = false,
    children,
}) {
    const t = useT();
    const key = status === 'client' ? 'client' : status;
    const copy = copyFor(key);
    const panelTone = PANEL_TONE[key] ?? PANEL_TONE[500];
    const code = status === 'client' ? 'ERR' : String(status);

    const primaryTitle = title ?? copy.viTitle;
    const secondaryTitle = copy.enTitle;
    const primaryDesc = description ?? copy.viDesc;
    const secondaryDesc = copy.enDesc;

    const showMessage = Boolean(message) && key !== 500 && key !== 503 && key !== 'client';

    return (
        <div className="erm-error-page fixed inset-0 z-[9999] flex min-h-dvh w-screen items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.95),_rgba(248,250,252,0.92)_38%,_rgba(236,242,250,0.96)_100%)] px-4 py-8">
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
                <div className="absolute -left-16 top-10 h-44 w-44 rounded-full bg-primary/10 blur-3xl" />
                <div className="absolute right-[-2rem] top-16 h-60 w-60 rounded-full bg-rose-200/30 blur-3xl" />
                <div className="absolute bottom-[-2rem] left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-sky-100/60 blur-3xl" />
            </div>

            <div className="relative z-10 mx-auto w-full max-w-5xl rounded-[32px] border border-white/80 bg-white/92 p-4 shadow-[0_26px_80px_rgba(15,23,42,0.12)] backdrop-blur md:p-6">
                <div className={cn('absolute inset-x-0 top-0 h-32 rounded-t-[32px] bg-gradient-to-b', panelTone)} aria-hidden />

                <div className="relative grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                    <div className="order-2 space-y-5 px-2 pb-2 pt-1 lg:order-1 lg:px-4 lg:py-4">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-md shadow-primary/15">
                                <LayoutDashboard className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-semibold text-slate-900">{brandName}</p>
                                <p className="text-xs text-slate-500">{brandTagline || 'CRM / ERP / Sales Operations Platform'}</p>
                            </div>
                        </div>

                        <div className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                            {code}
                        </div>

                        <div className="space-y-3">
                            <div>
                                <h1 className="text-3xl font-black tracking-tight text-slate-900 md:text-4xl">{primaryTitle}</h1>
                                <p className="mt-2 text-base font-semibold text-primary md:text-lg">{secondaryTitle}</p>
                            </div>

                            <div className="space-y-2 text-sm leading-7 text-slate-600 md:text-[15px]">
                                <p>{primaryDesc}</p>
                                <p className="text-slate-500">{secondaryDesc}</p>
                            </div>
                        </div>

                        {showMessage && (
                            <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {message}
                            </div>
                        )}

                        <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p className="text-sm font-semibold text-slate-800">Gợi ý xử lý / Suggested next step</p>
                            <p className="mt-1 text-sm leading-6 text-slate-600">{copy.viHint}</p>
                            <p className="text-sm leading-6 text-slate-500">{copy.enHint}</p>
                        </div>

                        <div className="flex flex-wrap gap-3 pt-1">
                            {children ?? (
                                <>
                                    <Button asChild>
                                        <Link href={homeUrl}>
                                            <Home className="size-4" />
                                            {showLogin ? t('errors.login') : t('errors.back_home')}
                                        </Link>
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                        <ArrowLeft className="size-4" />
                                        {t('common.back')}
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="order-1 flex items-center justify-center rounded-[28px] border border-slate-100 bg-[linear-gradient(180deg,rgba(255,255,255,0.85),rgba(248,250,252,0.9))] p-4 shadow-inner lg:order-2 lg:min-h-[520px]">
                        <div className="w-full max-w-[380px] text-center">
                            <DonutErrorIllustration status={key} />
                            <p className="mt-3 text-sm font-semibold text-slate-800">Oops! Có vẻ có thứ gì đó đang bị thiếu hoặc chưa sẵn sàng.</p>
                            <p className="mt-1 text-sm text-slate-500">Oops! It looks like something is missing or not ready right now.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
