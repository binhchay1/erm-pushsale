import { Link, usePage } from '@inertiajs/react';

import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';

function brandTitle(brand) {
    const configured = String(brand?.admin_name || 'TTGROUP2.ADMIN').trim();
    const normalized = configured.includes('.') ? configured : `${configured}.ADMIN`;

    return normalized.replace(/\s+/g, '').toUpperCase();
}

function dashboardForRole(role) {
    return {
        admin: '/admin/dashboard',
        sales: '/sales/dashboard',
        marketing: '/marketing/dashboard',
        warehouse: '/warehouse/dashboard',
        accounting: '/accounting/dashboard',
        allocator: '/allocator/dashboard',
    }[role] ?? '/';
}

export function AppHeader({ onToggleSidebar }) {
    const { brand, auth } = usePage().props;
    const dashboardUrl = dashboardForRole(auth?.user?.role);

    return (
        <header className="main-header pushsale-main-header">
            <div className="pushsale-header-brand">
                <button
                    type="button"
                    className="sidebar-toggle pushsale-sidebar-toggle"
                    onClick={onToggleSidebar}
                    aria-label="Thu gọn hoặc mở menu"
                >
                    <i className="fa fa-bars" aria-hidden="true" />
                </button>
                <Link href={dashboardUrl} className="logo pushsale-logo" title={brandTitle(brand)}>
                    {brandTitle(brand)}
                </Link>
            </div>

            <div className="pushsale-header-spacer" />

            <div className="pushsale-header-tools">
                <span className="pushsale-security-score">Điểm bảo mật: 1/18</span>
                <LanguageToggle pushsaleStyle />
                <NotificationBell pushsaleStyle />
                <span className="pushsale-header-icon" aria-hidden="true" title="Thông báo hệ thống">
                    <i className="fa fa-bullhorn" />
                </span>
                <UserMenu variant="pushsale" />
            </div>
        </header>
    );
}
