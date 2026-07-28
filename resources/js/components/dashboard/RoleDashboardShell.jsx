import { Deferred, Head, Link } from '@inertiajs/react';

import { DashboardSkeleton } from '@/components/dashboard/DashboardSkeleton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';

const ROLE_QUICK_LINKS = {
    sales: [
        { href: '/sales/workspace', label: 'Sale tác nghiệp', icon: 'fa-headphones' },
        { href: '/sales/reports/work', label: 'Báo cáo làm việc', icon: 'fa-bar-chart' },
        { href: '/sales/reports/revenue', label: 'Doanh số', icon: 'fa-money' },
    ],
    marketing: [
        { href: '/marketing/campaigns', label: 'Chiến dịch', icon: 'fa-bullhorn' },
        { href: '/marketing/leads/import', label: 'Import contact', icon: 'fa-upload' },
        { href: '/admin/marketing/dashboard', label: 'Dashboard MKT', icon: 'fa-line-chart' },
    ],
    warehouse: [
        { href: '/warehouse/operations', label: 'Kho tác nghiệp', icon: 'fa-truck' },
        { href: '/warehouse/inventory', label: 'Tồn kho', icon: 'fa-cubes' },
        { href: '/warehouse/incidents', label: 'Sự cố', icon: 'fa-exclamation-triangle' },
    ],
    accounting: [
        { href: '/accounting/workspace', label: 'Kế toán tác nghiệp', icon: 'fa-calculator' },
        { href: '/accounting/reconciliation', label: 'Đối soát', icon: 'fa-exchange' },
        { href: '/accounting/expenses', label: 'Chi phí', icon: 'fa-credit-card' },
    ],
    allocator: [
        { href: '/allocator/distribution', label: 'Phân bổ data', icon: 'fa-share-alt' },
        { href: '/admin/hr/lead-distribution-rules', label: 'Luật chia số', icon: 'fa-cogs' },
        { href: '/admin/users', label: 'Nhân viên', icon: 'fa-users' },
    ],
};

export function RoleDashboardQuickLinks({ role }) {
    const links = ROLE_QUICK_LINKS[role] ?? [];
    if (!links.length) return null;

    return (
        <div className="ps-role-dash-links">
            {links.map((link) => (
                <Link key={link.href} href={link.href} className="ps-role-dash-link">
                    <i className={`fa ${link.icon}`} aria-hidden="true" />
                    <span>{link.label}</span>
                </Link>
            ))}
        </div>
    );
}

/**
 * Frame cho dashboard theo role.
 * children phải là component dùng stats (mount trong Deferred).
 */
export function RoleDashboardFrame({
    role,
    title,
    subtitle = null,
    actions = null,
    children,
}) {
    return (
        <PushsalePageShell
            title={title}
            subtitle={subtitle}
            actions={actions}
            className={`ps-role-dashboard ps-role-dashboard-${role} ps-adminlte-page`}
            collapsible={false}
        >
            <RoleDashboardQuickLinks role={role} />
            <div className="ps-role-dash-body">{children}</div>
        </PushsalePageShell>
    );
}

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
