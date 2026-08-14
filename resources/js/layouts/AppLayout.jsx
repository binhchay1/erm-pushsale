import { router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useState } from 'react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { LocaleSync } from '@/components/layout/LocaleSync';
import { PageHeaderOutlet, PageHeaderProvider } from '@/components/layout/PageHeader';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/useFlashToast';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';
import { ensurePushsaleStyles } from '@/lib/uiShellStyles';
import { cn } from '@/lib/utils';

const dashboardRoutes = [
    { prefix: '/admin/dashboard', role: 'admin' },
    { prefix: '/sales/dashboard', role: 'sales' },
    { prefix: '/marketing/dashboard', role: 'marketing' },
    { prefix: '/warehouse/dashboard', role: 'warehouse' },
    { prefix: '/accounting/dashboard', role: 'accounting' },
    { prefix: '/allocator/dashboard', role: 'allocator' },
];

function dashboardRoleFromUrl(url) {
    const path = new URL(url, window.location.origin).pathname;

    return dashboardRoutes.find((route) => path.startsWith(route.prefix))?.role ?? null;
}

export default function AppLayout({ children }) {
    useFlashToast();
    useRealtimeNotifications();

    const [pendingDashboardRole, setPendingDashboardRole] = useState(null);
    const [pageTransitioning, setPageTransitioning] = useState(false);
    const [stylesReady, setStylesReady] = useState(() => document.documentElement.dataset.pushsaleStylesReady === '1' || Boolean(document.getElementById('pushsale-adminlte')));
    // Menu ERM mặc định ẩn để trang tác nghiệp/report mở full màn hình.
    // Người dùng bấm hamburger mới mở menu 252px. Không lưu localStorage để tránh
    // trạng thái cũ làm lệch layout sau deploy.
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Login và phần mềm dùng hai CSS shell khác nhau. Khi Inertia chuyển SPA sau
    // đăng nhập, nạp vendor CSS trước khi render để không xuất hiện HTML thô rồi mới F5.
    useLayoutEffect(() => {
        let active = true;
        const applyPushsaleShell = () => {
            document.body.classList.remove('public-app-body');
            document.body.classList.add('pushsale-app-body', 'hold-transition', 'skin-blue-light', 'sidebar-mini', 'fixed');
        };

        applyPushsaleShell();
        ensurePushsaleStyles().finally(() => active && setStylesReady(true));

        const restoreFromBrowserBack = (event) => {
            // Chrome/Safari can restore an Inertia page from BFCache with body classes or
            // runtime CSS links detached. Re-apply the shell instead of leaving a blank page
            // until the user presses F5.
            applyPushsaleShell();
            const stylesMissing = document.documentElement.dataset.pushsaleStylesReady !== '1'
                || !document.getElementById('pushsale-adminlte');
            if (stylesMissing || event.persisted) {
                if (stylesMissing) setStylesReady(false);
                ensurePushsaleStyles().finally(() => active && setStylesReady(true));
            }
        };

        window.addEventListener('pageshow', restoreFromBrowserBack);

        return () => {
            active = false;
            window.removeEventListener('pageshow', restoreFromBrowserBack);
            // Do not strip pushsale body classes on unmount — each page remounts AppLayout
            // and stripping causes a white/unstyled flash (especially on history back).
        };
    }, []);

    useEffect(() => {
        let transitionTimer = null;
        const clearTransitionTimer = () => {
            if (transitionTimer) {
                window.clearTimeout(transitionTimer);
                transitionTimer = null;
            }
        };

        const start = router.on('start', (event) => {
            const visit = event?.detail?.visit;
            const targetUrl = visit?.url;
            if (!targetUrl) {
                setPendingDashboardRole(null);
                return;
            }

            // Partial reloads (notifications, realtime refresh, table-only reloads) must
            // not swap the whole dashboard with a skeleton. That was the cause of the
            // continuous blinking/reset when many test notifications/toasts arrived.
            try {
                const target = new URL(targetUrl, window.location.origin);
                const current = new URL(window.location.href);
                const only = Array.isArray(visit.only) ? visit.only : [];
                if (target.pathname === current.pathname && only.length > 0) {
                    setPendingDashboardRole(null);
                    return;
                }
            } catch {
                // Fall through to the normal dashboard skeleton detection.
            }

            clearTransitionTimer();
            // Hiện overlay sớm hơn để trang báo cáo nặng không bị "treo" không phản hồi.
            transitionTimer = window.setTimeout(() => setPageTransitioning(true), 180);
            setPendingDashboardRole(dashboardRoleFromUrl(targetUrl));
        });
        const finish = router.on('finish', () => {
            clearTransitionTimer();
            setPendingDashboardRole(null);
            setPageTransitioning(false);
        });

        return () => {
            clearTransitionTimer();
            start();
            finish();
        };
    }, []);

    const setSidebarState = (open) => {
        setSidebarOpen(open);
    };

    const toggleSidebar = () => setSidebarState(!sidebarOpen);
    const closeSidebar = () => {
        setSidebarState(false);
    };

    useEffect(() => {
        if (!sidebarOpen) return undefined;

        const closeOnOutsidePointer = (event) => {
            const target = event.target;
            if (target?.closest?.('.pushsale-main-sidebar, .pushsale-third-menu, .main-header, .pushsale-header')) return;
            setSidebarState(false);
        };
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') setSidebarState(false);
        };

        document.addEventListener('mousedown', closeOnOutsidePointer);
        document.addEventListener('touchstart', closeOnOutsidePointer, { passive: true });
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsidePointer);
            document.removeEventListener('touchstart', closeOnOutsidePointer);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [sidebarOpen]);

    if (!stylesReady) {
        return <div className="pushsale-shell-loading"><i className="fa fa-spinner fa-spin" /> Đang tải giao diện…</div>;
    }

    return (
        <div
            className={cn(
                'wrapper pushsale-wrapper',
                !sidebarOpen && 'sidebar-collapse',
                sidebarOpen && 'sidebar-open',
            )}
        >
            <LocaleSync />
            <TooltipProvider>
                <AppHeader onToggleSidebar={toggleSidebar} />
                <AppSidebar collapsed={!sidebarOpen} onNavigate={closeSidebar} />

                <PageHeaderProvider>
                    <main className="content-wrapper">
                        <div className="content-inner">
                            <div className="ps-page-viewport">
                                <PageHeaderOutlet />
                                {pageTransitioning && !pendingDashboardRole && (
                                    <div className="pushsale-route-loading" aria-live="polite" aria-busy="true">
                                        <i className="fa fa-spinner fa-spin" aria-hidden="true" />
                                        <span>Đang tải dữ liệu…</span>
                                    </div>
                                )}
                                {pendingDashboardRole ? (
                                    <DashboardSkeleton role={pendingDashboardRole} />
                                ) : (
                                    children
                                )}
                            </div>
                        </div>
                    </main>
                </PageHeaderProvider>

                <button
                    type="button"
                    className="sidebar-mobile-backdrop"
                    aria-label="Đóng menu"
                    onClick={closeSidebar}
                />
            </TooltipProvider>
        </div>
    );
}
