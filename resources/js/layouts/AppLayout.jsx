import { router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useState } from 'react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { LocaleSync } from '@/components/layout/LocaleSync';
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
    const [stylesReady, setStylesReady] = useState(() => document.documentElement.dataset.pushsaleStylesReady === '1' || Boolean(document.getElementById('pushsale-adminlte')));
    // V25: menu nội bộ mở mặc định trên mỗi lần vào app. Không đọc trạng thái cũ
    // từ localStorage vì các bản trước đã lưu nhầm trạng thái collapsed và tạo gutter 42px.
    const [sidebarOpen, setSidebarOpen] = useState(true);

    // Login và phần mềm dùng hai CSS shell khác nhau. Khi Inertia chuyển SPA sau
    // đăng nhập, nạp vendor CSS trước khi render để không xuất hiện HTML thô rồi mới F5.
    useLayoutEffect(() => {
        let active = true;
        document.body.classList.remove('public-app-body');
        document.body.classList.add('pushsale-app-body', 'hold-transition', 'skin-blue-light', 'sidebar-mini', 'fixed');
        ensurePushsaleStyles().finally(() => active && setStylesReady(true));

        return () => {
            active = false;
            document.body.classList.remove('pushsale-app-body', 'hold-transition', 'skin-blue-light', 'sidebar-mini', 'fixed');
        };
    }, []);

    useEffect(() => {
        const start = router.on('start', (event) => {
            setPendingDashboardRole(dashboardRoleFromUrl(event.detail.visit.url));
        });
        const finish = router.on('finish', () => {
            setPendingDashboardRole(null);
        });

        return () => {
            start();
            finish();
        };
    }, []);

    const setSidebarState = (open) => {
        setSidebarOpen(open);
    };

    const toggleSidebar = () => setSidebarState(!sidebarOpen);
    const closeSidebar = () => {
        if (typeof window !== 'undefined' && window.innerWidth < 992) {
            setSidebarState(false);
        }
    };

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

                <main className="content-wrapper">
                    <div className="content-inner">
                        {pendingDashboardRole ? (
                            <DashboardSkeleton role={pendingDashboardRole} />
                        ) : (
                            children
                        )}
                    </div>
                </main>

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
