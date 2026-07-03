import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { LocaleSync } from '@/components/layout/LocaleSync';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/useFlashToast';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';

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

    useEffect(() => {
        const start = router.on('start', (event) => {
            setPendingDashboardRole(dashboardRoleFromUrl(event.detail.visit.url));
        });
        const finish = router.on('finish', () => setPendingDashboardRole(null));

        return () => {
            start();
            finish();
        };
    }, []);

    const shouldShowDashboardSkeleton = Boolean(pendingDashboardRole);

    return (
        <SidebarProvider defaultOpen={false} className="pushsale-wrapper flex min-h-svh flex-col">
            <LocaleSync />
            <TooltipProvider>
                <AppHeader />
                <div className="flex min-h-0 w-full flex-1">
                    <AppSidebar />
                    <main className="content-wrapper relative flex min-w-0 flex-1 flex-col overflow-x-hidden">
                        <div className="content-inner min-w-0 flex-1 overflow-x-hidden">
                            {shouldShowDashboardSkeleton ? (
                                <DashboardSkeleton role={pendingDashboardRole} />
                            ) : (
                                children
                            )}
                        </div>
                    </main>
                </div>
            </TooltipProvider>
        </SidebarProvider>
    );
}
