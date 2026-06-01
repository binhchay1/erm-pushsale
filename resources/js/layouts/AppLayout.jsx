import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/useFlashToast';

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
        <SidebarProvider>
            <TooltipProvider>
                <AppSidebar />
                <main className="relative flex min-h-svh w-full flex-1 flex-col bg-muted/30">
                    <AppHeader />
                    <div className="flex-1 p-4 md:p-6">
                        {shouldShowDashboardSkeleton ? (
                            <DashboardSkeleton role={pendingDashboardRole} />
                        ) : children}
                    </div>
                </main>
            </TooltipProvider>
        </SidebarProvider>
    );
}
