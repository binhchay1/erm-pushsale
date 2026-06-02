import { Deferred, Head } from '@inertiajs/react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import AppLayout from '@/layouts/AppLayout';

export function RoleDashboardShell({ role, title, children }) {
    return (
        <AppLayout>
            <Head title={title} />

            <Deferred data="stats" fallback={<DashboardSkeleton role={role} />}>
                {children}
            </Deferred>
        </AppLayout>
    );
}
