import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { LocaleSync } from '@/components/layout/LocaleSync';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/useFlashToast';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';
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

function initialCollapsedState() {
    try {
        return window.localStorage.getItem('pushsale-sidebar-collapsed') === '1';
    } catch {
        return false;
    }
}

export default function AppLayout({ children }) {
    useFlashToast();
    useRealtimeNotifications();

    const [pendingDashboardRole, setPendingDashboardRole] = useState(null);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(initialCollapsedState);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

    useEffect(() => {
        document.body.classList.add('hold-transition', 'skin-blue-light', 'sidebar-mini', 'fixed');

        const start = router.on('start', (event) => {
            setPendingDashboardRole(dashboardRoleFromUrl(event.detail.visit.url));
        });
        const finish = router.on('finish', () => {
            setPendingDashboardRole(null);
            setMobileSidebarOpen(false);
        });

        return () => {
            document.body.classList.remove('hold-transition', 'skin-blue-light', 'sidebar-mini', 'fixed');
            start();
            finish();
        };
    }, []);

    const toggleSidebar = () => {
        if (window.matchMedia('(max-width: 767px)').matches) {
            setMobileSidebarOpen((open) => !open);
            return;
        }

        setSidebarCollapsed((collapsed) => {
            const next = !collapsed;
            try {
                window.localStorage.setItem('pushsale-sidebar-collapsed', next ? '1' : '0');
            } catch {
                // localStorage có thể bị chặn; trạng thái phiên hiện tại vẫn hoạt động.
            }
            return next;
        });
    };

    return (
        <div
            className={cn(
                'wrapper pushsale-wrapper',
                sidebarCollapsed && 'sidebar-collapse',
                mobileSidebarOpen && 'sidebar-open',
            )}
        >
            <LocaleSync />
            <TooltipProvider>
                <AppHeader onToggleSidebar={toggleSidebar} />
                <AppSidebar
                    collapsed={sidebarCollapsed}
                    onNavigate={() => setMobileSidebarOpen(false)}
                />

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
                    onClick={() => setMobileSidebarOpen(false)}
                />
            </TooltipProvider>
        </div>
    );
}
